<?php
require_once __DIR__ . '/../functions.php';

// Allow web trigger (admin/manager) or CLI (cron)
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    requireLogin();
    if (!canAccess('manager')) {
        jsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 403);
    }
    requirePostWithCsrf();
    header('Content-Type: application/json');
}

$pdo = getDB();

// Enforce 60-second interval
$lastFetch = getSetting('last_fetch', '2000-01-01 00:00:00');
$elapsed   = time() - strtotime($lastFetch);
if ($elapsed < OTP_FETCH_INTERVAL) {
    $wait = OTP_FETCH_INTERVAL - $elapsed;
    $msg  = ['status' => 'throttled', 'message' => "Please wait {$wait}s before next fetch.", 'new_count' => 0];
    if ($isCli) { echo json_encode($msg) . "\n"; exit; }
    jsonResponse($msg);
}

// Fetch from external API
$ch = curl_init(OTP_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'SigmaSMS/1.0',
]);
$response = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr   = curl_error($ch);
curl_close($ch);

if ($curlErr || $httpCode !== 200) {
    $msg = ['status' => 'error', 'message' => "API request failed: $curlErr (HTTP $httpCode)", 'new_count' => 0];
    if ($isCli) { echo json_encode($msg) . "\n"; exit; }
    jsonResponse($msg, 502);
}

$json = json_decode($response, true);
if (!isset($json['data']) || !is_array($json['data'])) {
    $msg = ['status' => 'error', 'message' => 'Invalid API response', 'new_count' => 0];
    if ($isCli) { echo json_encode($msg) . "\n"; exit; }
    jsonResponse($msg, 502);
}

// Insert records
$insertSms = $pdo->prepare("
    INSERT IGNORE INTO sms_received (number, service, country, otp, message, received_at)
    VALUES (?, ?, ?, ?, ?, ?)
");
$getNumber = $pdo->prepare("
    SELECT id, assigned_to, rate FROM numbers WHERE number = ? AND status = 'active'
");
$insertProfit = $pdo->prepare("
    INSERT IGNORE INTO profit_log (user_id, number_id, sms_received_id, rate_applied, profit_amount, currency)
    VALUES (?, ?, ?, ?, ?, 'USD')
");

try {
    $pdo->beginTransaction();
    $newCount = 0;
    foreach ($json['data'] as $record) {
        $number     = trim($record['number'] ?? '');
        $service    = trim($record['service'] ?? '');
        $country    = strtoupper(trim($record['country'] ?? ''));
        $otp        = trim($record['otp'] ?? '');
        $message    = trim($record['message'] ?? '');
        $receivedAt = trim($record['received_at'] ?? '');

        if (empty($number) || empty($otp) || empty($receivedAt)) continue;

        $insertSms->execute([$number, $service, $country, $otp, $message, $receivedAt]);
        if ($insertSms->rowCount() === 0) continue;

        $newCount++;
        $smsId = (int)$pdo->lastInsertId();

        $getNumber->execute([$number]);
        $numRow = $getNumber->fetch();
        if ($numRow && $numRow['assigned_to']) {
            $rate = (float)$numRow['rate'];
            $insertProfit->execute([
                (int)$numRow['assigned_to'],
                (int)$numRow['id'],
                $smsId,
                $rate,
                $rate,
            ]);

            addNotification(
                (int)$numRow['assigned_to'],
                "New OTP received on {$number}: {$otp}"
            );
        }
    }
    setSetting('last_fetch', date('Y-m-d H:i:s'));
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $msg = ['status' => 'error', 'message' => 'Fetch failed. Please retry.', 'new_count' => 0];
    if ($isCli) { echo json_encode($msg) . "\n"; exit; }
    jsonResponse($msg, 500);
}

$result = ['status' => 'success', 'message' => "Fetch complete. {$newCount} new SMS inserted.", 'new_count' => $newCount];
if ($isCli) { echo json_encode($result) . "\n"; exit; }
jsonResponse($result);
