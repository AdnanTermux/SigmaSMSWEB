<?php
require_once __DIR__ . '/../functions.php';
requireLogin();
header('Content-Type: application/json');

$pdo  = getDB();
$q    = trim($_GET['q'] ?? '');
$stmt = $pdo->prepare("SELECT DISTINCT service FROM sms_received WHERE service LIKE ? AND service != '' ORDER BY service LIMIT 20");
$stmt->execute(["%$q%"]);
$services = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode(array_map(fn($s) => ['id' => $s, 'text' => ucfirst($s)], $services));
