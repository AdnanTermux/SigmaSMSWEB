<?php
require_once __DIR__ . '/../functions.php';
requireLogin();
maybeProxyToFastApi('/ajax/dt_numbers.php');
requireRole('manager'); // Only admin/manager use the full numbers management table
header('Content-Type: application/json');

$user   = getCurrentUser();
$userId = (int)$user['id'];
$role   = $user['role'];
$pdo    = getDB();

$draw   = (int)($_POST['draw'] ?? 1);
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 25);
$search = trim($_POST['search']['value'] ?? '');

$where  = ['n.status = "active"'];
$params = [];

// Managers see only numbers they created; admin sees all
if ($role === 'manager') {
    $where[] = 'n.created_by = ?';
    $params[] = $userId;
}

if ($search) {
    $where[] = '(n.number LIKE ? OR n.service LIKE ? OR n.country LIKE ? OR u2.username LIKE ?)';
    $params  = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
}

$whereStr = implode(' AND ', $where);
$joinSql  = "FROM numbers n
             LEFT JOIN users u1 ON n.created_by = u1.id
             LEFT JOIN users u2 ON n.assigned_to = u2.id
             WHERE $whereStr";

$totalStmt = $pdo->query("SELECT COUNT(*) FROM numbers WHERE status='active'");
$recordsTotal = (int)$totalStmt->fetchColumn();

$cntStmt = $pdo->prepare("SELECT COUNT(*) $joinSql");
$cntStmt->execute($params);
$recordsFiltered = (int)$cntStmt->fetchColumn();

$dataStmt = $pdo->prepare("SELECT n.*, u1.username as creator, u2.username as assignee $joinSql ORDER BY n.created_at DESC LIMIT $length OFFSET $start");
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll();

$data = [];
foreach ($rows as $r) {
    $actions = '<div class="d-flex gap-1">';
    $actions .= '<button class="btn btn-xs btn-outline-primary" onclick="editNumber('.(int)$r['id'].')"><i class="ri-edit-line"></i></button>';
    if ($r['assigned_to']) {
        $actions .= '<button class="btn btn-xs btn-outline-warning" onclick="unassignNumber('.(int)$r['id'].')"><i class="ri-link-unlink"></i></button>';
    } else {
        $actions .= '<button class="btn btn-xs btn-outline-success" onclick="assignNumber('.(int)$r['id'].')"><i class="ri-user-add-line"></i></button>';
    }
    $actions .= '<button class="btn btn-xs btn-outline-danger" onclick="deleteNumber('.(int)$r['id'].','.json_encode($r['assigned_to']).')"><i class="ri-delete-bin-line"></i></button>';
    $actions .= '</div>';

    $data[] = [
        '<code>' . h($r['number']) . '</code>',
        h($r['country'] ?? '–'),
        '<span class="badge bg-info text-dark">' . h($r['service'] ?? '–') . '</span>',
        '$' . number_format((float)$r['rate'], 6),
        ($r['status'] === 'active') ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>',
        $r['assignee'] ? h($r['assignee']) : '<span class="text-muted">Unassigned</span>',
        h($r['creator'] ?? '–'),
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
