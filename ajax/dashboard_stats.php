<?php
require_once __DIR__ . '/../functions.php';
requireLogin();
maybeProxyToFastApi('/ajax/dashboard_stats.php');
header('Content-Type: application/json');

$user = getCurrentUser();
$userId = (int)$user['id'];
$role = $user['role'];
$range = $_GET['range'] ?? '7d';
$fromParam = $_GET['from'] ?? '';
$toParam = $_GET['to'] ?? '';

$days = match ($range) {
    '24h' => 1,
    '30d' => 30,
    '90d' => 90,
    default => 7,
};

$from = $fromParam ?: date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
$to = $toParam ?: date('Y-m-d');
$fromAt = $from . ' 00:00:00';
$toAt = $to . ' 23:59:59';

$pdo = getDB();
if (in_array($role, ['admin', 'manager'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sms_received WHERE received_at BETWEEN ? AND ?");
    $stmt->execute([$fromAt, $toAt]);
    $rangeSms = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(profit_amount),0) FROM profit_log WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$fromAt, $toAt]);
    $rangeProfit = (float)$stmt->fetchColumn();
} else {
    $userIds = getDescendantUserIds($userId);
    if (!empty($userIds)) {
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $params = array_merge($userIds, [$fromAt, $toAt]);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM profit_log WHERE user_id IN ($ph) AND created_at BETWEEN ? AND ?");
        $stmt->execute($params);
        $rangeSms = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(profit_amount),0) FROM profit_log WHERE user_id IN ($ph) AND created_at BETWEEN ? AND ?");
        $stmt->execute($params);
        $rangeProfit = (float)$stmt->fetchColumn();
    } else {
        $rangeSms = 0;
        $rangeProfit = 0.0;
    }
}

$stats = getDashboardStats($user);
$stats['range_sms'] = $rangeSms;
$stats['range_profit'] = number_format($rangeProfit, 6, '.', '');
$stats['range_label'] = $range === '24h' ? 'Last 24 Hours' : strtoupper($range);
$stats['applied_from'] = $from;
$stats['applied_to'] = $to;

$response = ['status' => 'success', 'data' => $stats];

// Recent OTPs
if (isset($_GET['recent'])) {
    $pdo = getDB();
    $limit = max(5, min(50, (int)($_GET['limit'] ?? 10)));
    if (in_array($role, ['admin','manager'])) {
        $stmt = $pdo->query("
            SELECT sr.*, pl.profit_amount as profit
            FROM sms_received sr
            LEFT JOIN numbers n ON sr.number = n.number
            LEFT JOIN profit_log pl ON pl.sms_received_id = sr.id
            ORDER BY sr.received_at DESC LIMIT {$limit}
        ");
    } else {
        $userIds = getDescendantUserIds($userId);
        if (empty($userIds)) {
            $response['recent'] = [];
            echo json_encode($response);
            exit;
        }
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $pdo->prepare("
            SELECT sr.*, pl.profit_amount as profit
            FROM sms_received sr
            JOIN numbers n ON sr.number = n.number
            JOIN profit_log pl ON pl.sms_received_id = sr.id AND pl.user_id IN ($ph)
            WHERE n.assigned_to IN ($ph)
            ORDER BY sr.received_at DESC LIMIT {$limit}
        ");
        $params = array_merge($userIds, $userIds);
        $stmt->execute($params);
    }
    $response['recent'] = $stmt->fetchAll();
}

echo json_encode($response);
