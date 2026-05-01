<?php
require_once __DIR__ . '/../functions.php';
requireLogin();
maybeProxyToFastApi('/ajax/aj_numbers.php');
// Managers and resellers can perform number actions (resellers can assign to sub-resellers)
if (!canAccess('reseller')) {
    jsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 403);
}
header('Content-Type: application/json');

$user   = getCurrentUser();
$userId = (int)$user['id'];
$role   = $user['role'];
$pdo    = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'add':
        requirePostWithCsrf();
        $number  = trim($_POST['number'] ?? '');
        $country = strtoupper(trim($_POST['country'] ?? ''));
        $service = trim($_POST['service'] ?? '');
        $rate    = (float)($_POST['rate'] ?? 0);
        $status  = in_array($_POST['status']??'active', ['active','inactive']) ? $_POST['status'] : 'active';

        if (empty($number)) jsonResponse(['status'=>'error','message'=>'Number is required'], 400);

        try {
            $stmt = $pdo->prepare("INSERT INTO numbers (number, country, service, rate, status, created_by) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$number, $country, $service, $rate, $status, $userId]);
            jsonResponse(['status'=>'success','message'=>'Number added successfully']);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') jsonResponse(['status'=>'error','message'=>'Number already exists'], 409);
            throw $e;
        }
        break;

    case 'bulk_add':
        $lines   = explode("\n", $_POST['numbers'] ?? '');
        $country = strtoupper(trim($_POST['country'] ?? ''));
        $service = trim($_POST['service'] ?? '');
        $rate    = (float)($_POST['rate'] ?? 0);
        $added   = 0;
        $stmt    = $pdo->prepare("INSERT IGNORE INTO numbers (number, country, service, rate, created_by) VALUES (?,?,?,?,?)");
        foreach ($lines as $line) {
            $num = trim($line);
            if (empty($num)) continue;
            $stmt->execute([$num, $country, $service, $rate, $userId]);
            $added += $stmt->rowCount();
        }
        jsonResponse(['status'=>'success','message'=>"$added numbers added."]);
        break;

    case 'edit':
        requirePostWithCsrf();
        $id      = (int)($_POST['id'] ?? 0);
        $country = strtoupper(trim($_POST['country'] ?? ''));
        $service = trim($_POST['service'] ?? '');
        $rate    = (float)($_POST['rate'] ?? 0);
        $status  = in_array($_POST['status']??'active', ['active','inactive']) ? $_POST['status'] : 'active';

        // Check ownership
        $num = $pdo->prepare("SELECT * FROM numbers WHERE id = ?")->execute([$id]) ? null : null;
        $numStmt = $pdo->prepare("SELECT * FROM numbers WHERE id = ?");
        $numStmt->execute([$id]);
        $numRow = $numStmt->fetch();
        if (!$numRow) jsonResponse(['status'=>'error','message'=>'Number not found'], 404);
        if ($role === 'manager' && $numRow['created_by'] != $userId) jsonResponse(['status'=>'error','message'=>'Unauthorized'], 403);

        $pdo->prepare("UPDATE numbers SET country=?, service=?, rate=?, status=? WHERE id=?")
            ->execute([$country, $service, $rate, $status, $id]);
        jsonResponse(['status'=>'success','message'=>'Number updated']);
        break;

    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM numbers WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(['status'=>'error','message'=>'Not found'], 404);
        jsonResponse(['status'=>'success','data'=>$row]);
        break;

    case 'assign':
        requirePostWithCsrf();
        $id         = (int)($_POST['id'] ?? 0);
        $assignedTo = (int)($_POST['assigned_to'] ?? 0);

        $numStmt = $pdo->prepare("SELECT * FROM numbers WHERE id = ?");
        $numStmt->execute([$id]);
        $numRow = $numStmt->fetch();
        if (!$numRow) jsonResponse(['status'=>'error','message'=>'Number not found'], 404);
        if ($role === 'manager' && $numRow['created_by'] != $userId) jsonResponse(['status'=>'error','message'=>'Unauthorized'], 403);

        // Verify the target user is in valid scope
        $targetStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
        $targetStmt->execute([$assignedTo]);
        $target = $targetStmt->fetch();
        if (!$target) jsonResponse(['status'=>'error','message'=>'Target user not found'], 404);

        $pdo->prepare("UPDATE numbers SET assigned_to=?, assigned_at=NOW() WHERE id=?")
            ->execute([$assignedTo, $id]);
        addNotification($assignedTo, "Number {$numRow['number']} has been assigned to you.");
        jsonResponse(['status'=>'success','message'=>'Number assigned to '.$target['username']]);
        break;

    case 'unassign':
        $id = (int)($_POST['id'] ?? 0);
        $numStmt = $pdo->prepare("SELECT * FROM numbers WHERE id = ?");
        $numStmt->execute([$id]);
        $numRow = $numStmt->fetch();
        if (!$numRow) jsonResponse(['status'=>'error','message'=>'Number not found'], 404);
        if ($role === 'manager' && $numRow['created_by'] != $userId) jsonResponse(['status'=>'error','message'=>'Unauthorized'], 403);

        $pdo->prepare("UPDATE numbers SET assigned_to=NULL, assigned_at=NULL WHERE id=?")->execute([$id]);
        jsonResponse(['status'=>'success','message'=>'Number unassigned']);
        break;

    case 'delete':
        requirePostWithCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $numStmt = $pdo->prepare("SELECT * FROM numbers WHERE id = ?");
        $numStmt->execute([$id]);
        $numRow = $numStmt->fetch();
        if (!$numRow) jsonResponse(['status'=>'error','message'=>'Number not found'], 404);

        // Check delete permission
        if ($role === 'manager' && $numRow['created_by'] != $userId) {
            jsonResponse(['status'=>'error','message'=>'Unauthorized: not your number'], 403);
        }
        // Cannot delete if assigned to someone else
        if ($numRow['assigned_to'] && $numRow['assigned_to'] != $userId) {
            jsonResponse(['status'=>'error','message'=>'Unassign from user first before deleting'], 409);
        }

        // Soft delete
        $pdo->prepare("UPDATE numbers SET status='inactive', assigned_to=NULL WHERE id=?")->execute([$id]);
        jsonResponse(['status'=>'success','message'=>'Number deleted (soft)']);
        break;

    case 'resellers_list':
        // Return list of resellers for assign dropdown
        $where  = ["u.role IN ('reseller','sub_reseller')", "u.status = 'active'"];
        $params = [];
        if ($role === 'manager') {
            $where[] = '(u.parent_id = ? OR u.id = ?)';
            $params  = [$userId, $userId];
        }
        $stmt = $pdo->prepare("SELECT u.id, u.username, u.role FROM users u WHERE ".implode(' AND ',$where)." ORDER BY u.username");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        jsonResponse(['status'=>'success','data'=>$rows]);
        break;

    default:
        jsonResponse(['status'=>'error','message'=>'Unknown action'], 400);
}
