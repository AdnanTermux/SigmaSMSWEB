<?php
require_once __DIR__ . '/../functions.php';
requireLogin();
maybeProxyToFastApi('/ajax/aj_users.php');
header('Content-Type: application/json');

$user   = getCurrentUser();
$userId = (int)$user['id'];
$role   = $user['role'];
$pdo    = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Role-based allowed child roles
function allowedChildRoles(string $role): array {
    return match($role) {
        'admin'    => ['manager','reseller','sub_reseller'],
        'manager'  => ['reseller','sub_reseller'],
        'reseller' => ['sub_reseller'],
        default    => [],
    };
}

switch ($action) {

    case 'add':
        requirePostWithCsrf();
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $newRole  = $_POST['role'] ?? '';

        if (empty($username) || empty($password)) jsonResponse(['status'=>'error','message'=>'Username and password required'], 400);
        if (!in_array($newRole, allowedChildRoles($role))) jsonResponse(['status'=>'error','message'=>'You cannot create this role'], 403);
        if (strlen($password) < 6) jsonResponse(['status'=>'error','message'=>'Password must be at least 6 characters'], 400);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, parent_id, status) VALUES (?,?,?,?,?,'active')");
            $stmt->execute([$username, $email ?: null, password_hash($password, PASSWORD_DEFAULT), $newRole, $userId]);
            jsonResponse(['status'=>'success','message'=>"User '{$username}' created"]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') jsonResponse(['status'=>'error','message'=>'Username already exists'], 409);
            throw $e;
        }
        break;

    case 'edit':
        requirePostWithCsrf();
        $id       = (int)($_POST['id'] ?? 0);
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $status   = in_array($_POST['status']??'', ['active','blocked','pending']) ? $_POST['status'] : null;

        // Check scope
        if (!canManageUser($pdo, $userId, $role, $id)) jsonResponse(['status'=>'error','message'=>'Unauthorized'], 403);

        $updates = ['email = ?'];
        $params  = [$email ?: null];
        if ($password) {
            if (strlen($password) < 6) jsonResponse(['status'=>'error','message'=>'Password min 6 chars'], 400);
            $updates[] = 'password = ?';
            $params[]  = password_hash($password, PASSWORD_DEFAULT);
        }
        if ($status) { $updates[] = 'status = ?'; $params[] = $status; }
        $params[] = $id;

        $pdo->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
        jsonResponse(['status'=>'success','message'=>'User updated']);
        break;

    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if (!canManageUser($pdo, $userId, $role, $id)) jsonResponse(['status'=>'error','message'=>'Unauthorized'], 403);
        $stmt = $pdo->prepare("SELECT id, username, email, role, status FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        if (!$u) jsonResponse(['status'=>'error','message'=>'User not found'], 404);
        jsonResponse(['status'=>'success','data'=>$u]);
        break;

    case 'toggle_block':
        requirePostWithCsrf();
        $id    = (int)($_POST['id'] ?? 0);
        $block = ($_POST['block_action'] ?? '') === 'block' ? 'blocked' : 'active';
        if (!canManageUser($pdo, $userId, $role, $id)) jsonResponse(['status'=>'error','message'=>'Unauthorized'], 403);
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$block, $id]);
        jsonResponse(['status'=>'success','message'=>'User status updated to '.$block]);
        break;

    case 'delete':
        requirePostWithCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id === $userId) jsonResponse(['status'=>'error','message'=>'Cannot delete yourself'], 400);
        if (!canManageUser($pdo, $userId, $role, $id)) jsonResponse(['status'=>'error','message'=>'Unauthorized'], 403);
        // Unassign numbers from this user
        $pdo->prepare("UPDATE numbers SET assigned_to=NULL, assigned_at=NULL WHERE assigned_to=?")->execute([$id]);
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        jsonResponse(['status'=>'success','message'=>'User deleted']);
        break;

    default:
        jsonResponse(['status'=>'error','message'=>'Unknown action'], 400);
}

function canManageUser(PDO $pdo, int $managerId, string $managerRole, int $targetId): bool {
    if ($managerRole === 'admin') return true;
    $stmt = $pdo->prepare("SELECT id, parent_id FROM users WHERE id = ?");
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();
    if (!$target) return false;
    if ($managerRole === 'manager') {
        // Can manage direct children
        return (int)$target['parent_id'] === $managerId;
    }
    if ($managerRole === 'reseller') {
        return (int)$target['parent_id'] === $managerId;
    }
    return false;
}
