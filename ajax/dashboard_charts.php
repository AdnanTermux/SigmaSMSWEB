<?php
require_once __DIR__ . '/../functions.php';
requireLogin();
maybeProxyToFastApi('/ajax/dashboard_charts.php');
header('Content-Type: application/json');

$user   = getCurrentUser();
$userId = (int)$user['id'];
$role   = $user['role'];
$type   = $_GET['type'] ?? 'sms';
$pdo    = getDB();
$range = $_GET['range'] ?? '7d';
$days = match ($range) {
    '24h' => 1,
    '30d' => 30,
    '90d' => 90,
    default => 7,
};

if ($type === 'sms') {
    // Last 7 days SMS count
    if (in_array($role, ['admin','manager'])) {
        $stmt = $pdo->query("
            SELECT DATE(received_at) as day, COUNT(*) as cnt
            FROM sms_received
            WHERE received_at >= DATE_SUB(CURDATE(), INTERVAL " . max(0, $days - 1) . " DAY)
            GROUP BY DATE(received_at)
            ORDER BY day ASC
        ");
        $rows = $stmt->fetchAll();
    } else {
        $userIds = getDescendantUserIds($userId);
        if (empty($userIds)) { echo json_encode(['categories'=>[],'data'=>[]]); exit; }
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $pdo->prepare("
            SELECT DATE(pl.created_at) as day, COUNT(*) as cnt
            FROM profit_log pl
            WHERE pl.user_id IN ($ph) AND pl.created_at >= DATE_SUB(CURDATE(), INTERVAL " . max(0, $days - 1) . " DAY)
            GROUP BY DATE(pl.created_at)
            ORDER BY day ASC
        ");
        $stmt->execute($userIds);
        $rows = $stmt->fetchAll();
    }

    // Build full range array
    $map = [];
    foreach ($rows as $r) $map[$r['day']] = (int)$r['cnt'];
    $categories = [];
    $data = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $categories[] = date('M d', strtotime($d));
        $data[] = $map[$d] ?? 0;
    }
    echo json_encode(['categories' => $categories, 'data' => $data]);

} elseif ($type === 'services') {
    // Top 5 services
    if (in_array($role, ['admin','manager'])) {
        $stmt = $pdo->query("
            SELECT service, COUNT(*) as cnt
            FROM sms_received
            WHERE service IS NOT NULL AND service != ''
            GROUP BY service ORDER BY cnt DESC LIMIT 5
        ");
    } else {
        $userIds = getDescendantUserIds($userId);
        if (empty($userIds)) { echo json_encode(['labels'=>[],'data'=>[]]); exit; }
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $pdo->prepare("
            SELECT sr.service, COUNT(*) as cnt
            FROM sms_received sr
            JOIN numbers n ON sr.number = n.number
            WHERE n.assigned_to IN ($ph)
              AND sr.service IS NOT NULL AND sr.service != ''
            GROUP BY sr.service ORDER BY cnt DESC LIMIT 5
        ");
        $stmt->execute($userIds);
    }
    $rows = $stmt->fetchAll();
    $labels = array_map(fn($r) => ucfirst($r['service']), $rows);
    $data   = array_map(fn($r) => (int)$r['cnt'], $rows);
    echo json_encode(['labels' => $labels, 'data' => $data]);
} elseif ($type === 'countries') {
    if (in_array($role, ['admin','manager'])) {
        $stmt = $pdo->query("
            SELECT country, COUNT(*) as cnt
            FROM sms_received
            WHERE country IS NOT NULL AND country != ''
            GROUP BY country ORDER BY cnt DESC LIMIT 7
        ");
    } else {
        $userIds = getDescendantUserIds($userId);
        if (empty($userIds)) { echo json_encode(['labels'=>[],'data'=>[]]); exit; }
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $pdo->prepare("
            SELECT sr.country, COUNT(*) as cnt
            FROM sms_received sr
            JOIN numbers n ON sr.number = n.number
            WHERE n.assigned_to IN ($ph)
              AND sr.country IS NOT NULL AND sr.country != ''
            GROUP BY sr.country ORDER BY cnt DESC LIMIT 7
        ");
        $stmt->execute($userIds);
    }
    $rows = $stmt->fetchAll();
    $labels = array_map(fn($r) => strtoupper($r['country']), $rows);
    $data   = array_map(fn($r) => (int)$r['cnt'], $rows);
    echo json_encode(['labels' => $labels, 'data' => $data]);
}
