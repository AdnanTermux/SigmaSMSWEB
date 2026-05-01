<?php
/**
 * Sigma SMS A2P — DataTable: Profit Reports
 * Server-side processing with grouping and footer totals.
 */
require_once __DIR__ . '/../functions.php';
requireLogin();
header('Content-Type: application/json');

$user   = getCurrentUser();
$userId = (int)$user['id'];
$role   = $user['role'];
$pdo    = getDB();

$draw   = (int)($_POST['draw'] ?? 1);
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 25);

$dateFrom = trim($_POST['date_from'] ?? '');
$dateTo   = trim($_POST['date_to']   ?? '');
$service  = trim($_POST['service']   ?? '');
$groupBy  = trim($_POST['group_by']  ?? 'day');

$where  = ['1=1'];
$params = [];

if (!in_array($role, ['admin', 'manager'])) {
    $userIds = getDescendantUserIds($userId);
    if (empty($userIds)) {
        echo json_encode([
            'draw'            => $draw,
            'recordsTotal'    => 0,
            'recordsFiltered' => 0,
            'data'            => [],
            'footer'          => ['total_profit' => '$0.000000', 'total_sms' => 0],
        ]);
        exit;
    }
    $ph      = implode(',', array_fill(0, count($userIds), '?'));
    $where[] = "pl.user_id IN ($ph)";
    $params  = array_merge($params, $userIds);
}

if ($dateFrom) { $where[] = 'pl.created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
if ($dateTo)   { $where[] = 'pl.created_at <= ?'; $params[] = $dateTo   . ' 23:59:59'; }
if ($service)  { $where[] = 'sr.service = ?';      $params[] = $service; }

$whereStr = implode(' AND ', $where);

$groupExpr = match($groupBy) {
    'service' => 'sr.service',
    'country' => 'sr.country',
    'number'  => 'n.number',
    default   => 'DATE(pl.created_at)',
};

$selectSql = "SELECT $groupExpr as grp_label, COUNT(*) as total_sms, SUM(pl.profit_amount) as total_profit, MIN(pl.created_at) as first_date, MAX(pl.created_at) as last_date";
$joinSql   = "FROM profit_log pl
              JOIN sms_received sr ON sr.id = pl.sms_received_id
              JOIN numbers n ON n.id = pl.number_id
              WHERE $whereStr";

$countSql = "SELECT COUNT(*) FROM (SELECT $groupExpr $joinSql GROUP BY $groupExpr) t";
$dataSql  = "$selectSql $joinSql GROUP BY $groupExpr ORDER BY last_date DESC LIMIT $length OFFSET $start";

// Total unfiltered
$totalCount = (int)$pdo->query(
    "SELECT COUNT(DISTINCT $groupExpr) FROM profit_log pl
     JOIN sms_received sr ON sr.id = pl.sms_received_id
     JOIN numbers n ON n.id = pl.number_id"
)->fetchColumn();

$cntStmt = $pdo->prepare($countSql);
$cntStmt->execute($params);
$recordsFiltered = (int)$cntStmt->fetchColumn();

$dataStmt = $pdo->prepare($dataSql);
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll();

// Totals
$totStmt = $pdo->prepare("SELECT COUNT(*) as ts, SUM(pl.profit_amount) as tp $joinSql");
$totStmt->execute($params);
$tot = $totStmt->fetch();

$data = [];
foreach ($rows as $r) {
    $data[] = [
        h($r['grp_label'] ?? '–'),
        number_format((int)$r['total_sms']),
        '<span class="text-success fw-bold">$' . number_format((float)$r['total_profit'], 6) . '</span>',
        h($r['first_date'] ?? ''),
        h($r['last_date'] ?? ''),
    ];
}

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => (int)$totalCount,
    'recordsFiltered' => $recordsFiltered,
    'data'            => $data,
    'footer'          => [
        'total_profit' => '$' . number_format((float)($tot['tp'] ?? 0), 6),
        'total_sms'    => (int)($tot['ts'] ?? 0),
    ],
]);
