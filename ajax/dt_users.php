<?php
require_once __DIR__ . '/../functions.php';
requireLogin();
maybeProxyToFastApi('/ajax/dt_users.php');
header('Content-Type: application/json');

$user   = getCurrentUser();
$userId = (int)$user['id'];
$role   = $user['role'];
$pdo    = getDB();

$draw   = (int)($_POST['draw'] ?? 1);
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 25);
$search = trim($_POST['search']['value'] ?? '');

$where  = ['1=1'];
$params = [];

// Scope by role
if ($role === 'admin') {
    // Admin sees all except themselves
    $where[] = 'u.id != ?';
    $params[] = $userId;
} elseif ($role === 'manager') {
    $where[] = 'u.parent_id = ?';
    $params[] = $userId;
} elseif ($role === 'reseller') {
    $where[] = 'u.parent_id = ?';
    $where[] = "u.role = 'sub_reseller'";
    $params[] = $userId;
} else {
    echo json_encode(['draw'=>$draw,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]]);
    exit;
}

if ($search) {
    $where[] = '(u.username LIKE ? OR u.email LIKE ?)';
    $params  = array_merge($params, ["%$search%","%$search%"]);
}

$whereStr = implode(' AND ', $where);
$joinSql  = "FROM users u LEFT JOIN users p ON u.parent_id = p.id WHERE $whereStr";

$totalStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE id != $userId");
$recordsTotal = (int)$totalStmt->fetchColumn();

$cntStmt = $pdo->prepare("SELECT COUNT(*) $joinSql");
$cntStmt->execute($params);
$recordsFiltered = (int)$cntStmt->fetchColumn();

$dataStmt = $pdo->prepare("SELECT u.*, p.username as parent_name $joinSql ORDER BY u.created_at DESC LIMIT $length OFFSET $start");
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll();

$data = [];
foreach ($rows as $r) {
    $roleBadge   = roleLabel($r['role']);
    $statusBadge = statusLabel($r['status']);

    $actions = '<div class="d-flex gap-1">';
    $actions .= '<button class="btn btn-xs btn-outline-primary" onclick="editUser('.(int)$r['id'].')"><i class="ri-edit-line"></i></button>';

    if ($r['status'] === 'blocked') {
        $actions .= '<button class="btn btn-xs btn-outline-success" onclick="toggleBlock('.(int)$r['id'].',\'unblock\')"><i class="ri-lock-unlock-line"></i></button>';
    } else {
        $actions .= '<button class="btn btn-xs btn-outline-warning" onclick="toggleBlock('.(int)$r['id'].',\'block\')"><i class="ri-lock-line"></i></button>';
    }
    $actions .= '<button class="btn btn-xs btn-outline-danger" onclick="deleteUser('.(int)$r['id'].','.json_encode($r['username']).')"><i class="ri-delete-bin-line"></i></button>';
    $actions .= '</div>';

    $data[] = [
        h($r['username']),
        h($r['email'] ?? '–'),
        $roleBadge,
        $statusBadge,
        h($r['parent_name'] ?? '–'),
        h($r['created_at']),
        $actions,
    ];
}

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data'            => $data,
]);
