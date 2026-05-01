<?php
/**
 * Sigma SMS A2P — DataTable: SMS Reports
 * Server-side processing with filters, grouping, and footer totals.
 */
require_once __DIR__ . '/../functions.php';
requireLogin();
header('Content-Type: application/json');

$user   = getCurrentUser();
$userId = (int)$user['id'];
$role   = $user['role'];
$pdo    = getDB();

// DataTable params
$draw   = (int)($_POST['draw'] ?? 1);
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 25);
$search = trim($_POST['search']['value'] ?? '');

// Custom filters
$dateFrom = trim($_POST['date_from'] ?? '');
$dateTo   = trim($_POST['date_to']   ?? '');
$service  = trim($_POST['service']   ?? '');
$country  = trim($_POST['country']   ?? '');
$number   = trim($_POST['number']    ?? '');
$groupBy  = $_POST['group_by'] ?? [];
if (!is_array($groupBy)) $groupBy = [];

$validGroupMap = [
    'DATE(sr.received_at)' => 'Date',
    'sr.service'           => 'Service',
    'sr.country'           => 'Country',
    'sr.number'            => 'Number',
];
$allowedGroupKeys = array_keys($validGroupMap);

// Build WHERE clause
$where  = ['1=1'];
$params = [];

// Role-based number restriction
if (!in_array($role, ['admin', 'manager'])) {
    $userIds = getDescendantUserIds($userId);
    if (empty($userIds)) {
        echo json_encode([
            'draw'            => $draw,
            'recordsTotal'    => 0,
            'recordsFiltered' => 0,
            'data'            => [],
            'footer'          => ['total_profit' => '0.000000', 'total_sms' => 0],
        ]);
        exit;
    }
    $ph      = implode(',', array_fill(0, count($userIds), '?'));
    $where[] = "n.assigned_to IN ($ph)";
    $params  = array_merge($params, $userIds);
}

if ($dateFrom) { $where[] = 'sr.received_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
if ($dateTo)   { $where[] = 'sr.received_at <= ?'; $params[] = $dateTo   . ' 23:59:59'; }
if ($service)  { $where[] = 'sr.service = ?';       $params[] = $service; }
if ($country)  { $where[] = 'sr.country = ?';       $params[] = $country; }
if ($number)   { $where[] = 'sr.number LIKE ?';     $params[] = "%$number%"; }
if ($search) {
    $where[] = '(sr.number LIKE ? OR sr.otp LIKE ? OR sr.service LIKE ? OR sr.message LIKE ?)';
    $params  = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}

$whereStr = implode(' AND ', $where);

$joinSql = "
    FROM sms_received sr
    LEFT JOIN numbers n ON sr.number = n.number
    LEFT JOIN profit_log pl ON pl.sms_received_id = sr.id
    LEFT JOIN users u ON n.assigned_to = u.id
    WHERE $whereStr
";

// Group by
$groupByFields = [];
foreach ($groupBy as $g) {
    if (in_array($g, $allowedGroupKeys)) {
        $groupByFields[] = $g;
    }
}

if (!empty($groupByFields)) {
    $groupByStr = implode(', ', $groupByFields);
    $selectSql  = "SELECT $groupByStr, COUNT(*) as total_sms, COALESCE(SUM(pl.profit_amount),0) as total_profit, MAX(sr.received_at) as last_received";
    $countSql   = "SELECT COUNT(*) FROM (SELECT $groupByStr $joinSql GROUP BY $groupByStr) t";
    $dataSql    = "$selectSql $joinSql GROUP BY $groupByStr ORDER BY last_received DESC LIMIT $length OFFSET $start";
} else {
    $selectSql = "SELECT sr.*, n.rate, n.assigned_to, u.username as assigned_username, COALESCE(pl.profit_amount,0) as profit_amount";
    $countSql  = "SELECT COUNT(*) $joinSql";
    $dataSql   = "$selectSql $joinSql ORDER BY sr.received_at DESC LIMIT $length OFFSET $start";
}

// Total count (unfiltered)
$recordsTotal = (int)$pdo->query("SELECT COUNT(*) FROM sms_received")->fetchColumn();

// Filtered count
$cntStmt = $pdo->prepare($countSql);
$cntStmt->execute($params);
$recordsFiltered = (int)$cntStmt->fetchColumn();

// Data
$dataStmt = $pdo->prepare($dataSql);
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll();

// Footer totals
$totStmt = $pdo->prepare("SELECT COUNT(*) as total_sms, COALESCE(SUM(pl.profit_amount),0) as total_profit $joinSql");
$totStmt->execute($params);
$totals = $totStmt->fetch();

// Format rows
$data = [];
foreach ($rows as $r) {
    if (!empty($groupByFields)) {
        $row = [];
        foreach ($groupByFields as $gf) {
            // Extract column alias from expression
            $col = preg_replace('/[^a-z_]/i', '', $gf);
            $row[] = h($r[$col] ?? '–');
        }
        $row[] = number_format((int)$r['total_sms']);
        $row[] = '$' . number_format((float)$r['total_profit'], 6);
        $row[] = h($r['last_received'] ?? '');
    } else {
        $row = [
            h($r['received_at'] ?? ''),
            '<code>' . h($r['number'] ?? '') . '</code>',
            '<span class="badge bg-info text-dark">' . h($r['service'] ?? '–') . '</span>',
            h($r['country'] ?? '–'),
            '<strong class="text-primary">' . h($r['otp'] ?? '') . '</strong>',
            '<small class="text-muted">' . h(mb_substr($r['message'] ?? '', 0, 80)) . '</small>',
            '$' . number_format((float)($r['rate'] ?? 0), 6),
            '<span class="text-success fw-semibold">$' . number_format((float)$r['profit_amount'], 6) . '</span>',
            h($r['assigned_username'] ?? '–'),
        ];
    }
    $data[] = $row;
}

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data'            => $data,
    'footer'          => [
        'total_profit' => '$' . number_format((float)($totals['total_profit'] ?? 0), 6),
        'total_sms'    => (int)($totals['total_sms'] ?? 0),
    ],
]);
