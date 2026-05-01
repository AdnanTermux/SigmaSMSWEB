<?php
/**
 * Sigma SMS A2P — Public OTP API Endpoint
 * GET /api/otps.php?token=xxx&from=YYYY-MM-DD&to=YYYY-MM-DD
 */

require_once __DIR__ . '/../functions.php';

// Always return JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Authorization, X-Token');

// Support token in query string or Authorization header
$token = '';
if (!empty($_GET['token'])) {
    $token = trim($_GET['token']);
} elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    // Bearer <token>
    $auth = $_SERVER['HTTP_AUTHORIZATION'];
    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        $token = trim($m[1]);
    }
} elseif (!empty($_SERVER['HTTP_X_TOKEN'])) {
    $token = trim($_SERVER['HTTP_X_TOKEN']);
}

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'API token required']);
    exit;
}

// Authenticate token
$user = getUserByToken($token);
if (!$user) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired token']);
    exit;
}

$userId = (int)$user['id'];
$pdo    = getDB();

// Date filters
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');

// Validate dates
if ($from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid "from" date format. Use YYYY-MM-DD']);
    exit;
}
if ($to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid "to" date format. Use YYYY-MM-DD']);
    exit;
}

// Pagination
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = min(500, max(1, (int)($_GET['limit'] ?? 100)));
$offset = ($page - 1) * $limit;

// Build query based on user role
$userIds = getDescendantUserIds($userId);
if (empty($userIds)) {
    echo json_encode(['status' => 'success', 'total' => 0, 'page' => $page, 'limit' => $limit, 'data' => []]);
    exit;
}

$ph     = implode(',', array_fill(0, count($userIds), '?'));
$where  = ["n.assigned_to IN ($ph)", "n.status = 'active'"];
$params = $userIds;

if ($from) { $where[] = 'sr.received_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to)   { $where[] = 'sr.received_at <= ?'; $params[] = $to   . ' 23:59:59'; }

// Optional filters
$service = trim($_GET['service'] ?? '');
$country = strtoupper(trim($_GET['country'] ?? ''));
$number  = trim($_GET['number'] ?? '');
if ($service) { $where[] = 'sr.service = ?'; $params[] = $service; }
if ($country) { $where[] = 'sr.country = ?'; $params[] = $country; }
if ($number)  { $where[] = 'sr.number = ?';  $params[] = $number; }

$whereStr = implode(' AND ', $where);

// Count
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM sms_received sr
    JOIN numbers n ON sr.number = n.number
    WHERE $whereStr
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Data
$dataStmt = $pdo->prepare("
    SELECT
        sr.id,
        sr.number,
        sr.service,
        sr.country,
        sr.otp,
        sr.message,
        sr.received_at,
        n.rate,
        COALESCE(pl.profit_amount, 0) as profit,
        u.username as assigned_to
    FROM sms_received sr
    JOIN numbers n ON sr.number = n.number
    LEFT JOIN profit_log pl ON pl.sms_received_id = sr.id AND pl.user_id IN ($ph)
    LEFT JOIN users u ON n.assigned_to = u.id
    WHERE $whereStr
    ORDER BY sr.received_at DESC
    LIMIT $limit OFFSET $offset
");

// Need to pass userIds again for the pl join
$allParams = array_merge($userIds, $params);
$dataStmt->execute($allParams);
$rows = $dataStmt->fetchAll();

// Clean output
$data = array_map(function($r) {
    return [
        'id'          => (int)$r['id'],
        'number'      => $r['number'],
        'service'     => $r['service'],
        'country'     => $r['country'],
        'otp'         => $r['otp'],
        'message'     => $r['message'],
        'received_at' => $r['received_at'],
        'rate'        => number_format((float)$r['rate'], 6),
        'profit'      => number_format((float)$r['profit'], 6),
        'assigned_to' => $r['assigned_to'],
    ];
}, $rows);

echo json_encode([
    'status'      => 'success',
    'total'       => $total,
    'page'        => $page,
    'limit'       => $limit,
    'total_pages' => (int)ceil($total / $limit),
    'data'        => $data,
], JSON_PRETTY_PRINT);
