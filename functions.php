<?php
/**
 * Sigma SMS A2P OTP Panel - Core Functions
 */

require_once __DIR__ . '/config.php';

// ─── Authentication ────────────────────────────────────────────────────────────

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
        if (!$user) {
            session_destroy();
            header('Location: ' . APP_URL . '/login.php');
            exit;
        }
    }
    return $user;
}

function getUserRole(): string {
    $user = getCurrentUser();
    return $user ? $user['role'] : '';
}

// Role hierarchy: higher index = higher privilege
const ROLE_HIERARCHY = ['sub_reseller' => 0, 'reseller' => 1, 'manager' => 2, 'admin' => 3];

function getRoleLevel(string $role): int {
    return ROLE_HIERARCHY[$role] ?? -1;
}

function canAccess(string $required_role): bool {
    $userRole = getUserRole();
    return getRoleLevel($userRole) >= getRoleLevel($required_role);
}

function requireRole(string $role): void {
    requireLogin();
    if (!canAccess($role)) {
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    }
}

function isAdmin(): bool { return getUserRole() === 'admin'; }
function isManager(): bool { return in_array(getUserRole(), ['admin', 'manager']); }
function isReseller(): bool { return in_array(getUserRole(), ['admin', 'manager', 'reseller']); }

// ─── CSRF ──────────────────────────────────────────────────────────────────────

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function requirePostWithCsrf(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
    }
    if (!verifyCsrf()) {
        jsonResponse(['status' => 'error', 'message' => 'Invalid CSRF token'], 419);
    }
}

function maybeProxyToFastApi(string $targetPath): void {
    $enabled = getenv('USE_FASTAPI_AJAX') ?: '0';
    $baseUrl = rtrim(getenv('FASTAPI_BASE_URL') ?: '', '/');
    if ($enabled !== '1' || $baseUrl === '') {
        return;
    }

    $url = $baseUrl . $targetPath;
    if (!empty($_SERVER['QUERY_STRING'])) {
        $url .= '?' . $_SERVER['QUERY_STRING'];
    }

    $ch = curl_init($url);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($_POST));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($response === false || $err) {
        jsonResponse(['status' => 'error', 'message' => 'FastAPI proxy failed'], 502);
    }

    http_response_code($httpCode ?: 200);
    header('Content-Type: application/json');
    echo $response;
    exit;
}

// ─── Users ─────────────────────────────────────────────────────────────────────

function getUserById(int $id): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, username, email, role, status, parent_id, created_at FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getSubResellers(int $userId): array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE parent_id = ? AND role = 'sub_reseller'");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getDescendantUserIds(int $userId): array {
    // Returns all user IDs in the hierarchy below this user
    $pdo = getDB();
    $ids = [$userId];
    $queue = [$userId];
    while (!empty($queue)) {
        $placeholders = implode(',', array_fill(0, count($queue), '?'));
        $stmt = $pdo->prepare("SELECT id FROM users WHERE parent_id IN ($placeholders)");
        $stmt->execute($queue);
        $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $ids = array_merge($ids, $children);
        $queue = $children;
    }
    return array_unique($ids);
}

// ─── Numbers ───────────────────────────────────────────────────────────────────

function getAssignedNumberIds(int $userId, bool $includeSubResellers = true): array {
    $pdo = getDB();
    if ($includeSubResellers) {
        $userIds = getDescendantUserIds($userId);
    } else {
        $userIds = [$userId];
    }
    if (empty($userIds)) return [];
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $pdo->prepare("SELECT id FROM numbers WHERE assigned_to IN ($placeholders) AND status = 'active'");
    $stmt->execute($userIds);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getAssignedNumbers(int $userId, bool $includeSubResellers = true): array {
    $pdo = getDB();
    if ($includeSubResellers) {
        $userIds = getDescendantUserIds($userId);
    } else {
        $userIds = [$userId];
    }
    if (empty($userIds)) return [];
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $pdo->prepare("SELECT n.*, u.username as assigned_username FROM numbers n LEFT JOIN users u ON n.assigned_to = u.id WHERE n.assigned_to IN ($placeholders) AND n.status = 'active'");
    $stmt->execute($userIds);
    return $stmt->fetchAll();
}

// ─── Notifications ─────────────────────────────────────────────────────────────

function addNotification(int $userId, string $message): void {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->execute([$userId, $message]);
}

function getUnreadNotificationCount(int $userId): int {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

// ─── API Tokens ────────────────────────────────────────────────────────────────

function generateApiToken(int $userId): string {
    $pdo = getDB();
    $token = bin2hex(random_bytes(32)); // 64 char hex
    // Delete old token if exists
    $pdo->prepare("DELETE FROM api_tokens WHERE user_id = ?")->execute([$userId]);
    // Insert new token
    $pdo->prepare("INSERT INTO api_tokens (user_id, token) VALUES (?, ?)")->execute([$userId, $token]);
    return $token;
}

function getUserByToken(string $token): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT u.* FROM users u
        JOIN api_tokens t ON t.user_id = u.id
        WHERE t.token = ? AND u.status = 'active'
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch() ?: null;
    if ($user) {
        // Update last_used_at
        $pdo->prepare("UPDATE api_tokens SET last_used_at = NOW() WHERE token = ?")->execute([$token]);
    }
    return $user;
}

function getTokenForUser(int $userId): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM api_tokens WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

// ─── Settings ──────────────────────────────────────────────────────────────────

function getSetting(string $key, string $default = ''): string {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return ($val !== false) ? $val : $default;
}

function setSetting(string $key, string $value): void {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$key, $value, $value]);
}

// ─── Dashboard Stats ───────────────────────────────────────────────────────────

function getDashboardStats(array $user): array {
    $pdo = getDB();
    $userId = (int)$user['id'];
    $role = $user['role'];

    if (in_array($role, ['admin', 'manager'])) {
        // System-wide stats
        $todaySms = $pdo->query("SELECT COUNT(*) FROM sms_received WHERE DATE(received_at) = CURDATE()")->fetchColumn();
        $weekSms  = $pdo->query("SELECT COUNT(*) FROM sms_received WHERE received_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $todayProfit = $pdo->query("SELECT COALESCE(SUM(profit_amount),0) FROM profit_log WHERE DATE(created_at) = CURDATE()")->fetchColumn();
        $weekProfit  = $pdo->query("SELECT COALESCE(SUM(profit_amount),0) FROM profit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $totalNumbers = $pdo->query("SELECT COUNT(*) FROM numbers WHERE status='active'")->fetchColumn();
        $totalUsers   = $pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
    } else {
        // Own stats
        $userIds = getDescendantUserIds($userId);
        if (empty($userIds)) {
            return [
                'today_sms'     => 0,
                'week_sms'      => 0,
                'today_profit'  => '0.000000',
                'week_profit'   => '0.000000',
                'total_numbers' => 0,
                'total_users'   => 0,
            ];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM profit_log pl WHERE pl.user_id IN ($placeholders) AND DATE(pl.created_at) = CURDATE()");
        $stmt->execute($userIds);
        $todaySms = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM profit_log pl WHERE pl.user_id IN ($placeholders) AND pl.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute($userIds);
        $weekSms = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(profit_amount),0) FROM profit_log WHERE user_id IN ($placeholders) AND DATE(created_at) = CURDATE()");
        $stmt->execute($userIds);
        $todayProfit = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(profit_amount),0) FROM profit_log WHERE user_id IN ($placeholders) AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute($userIds);
        $weekProfit = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM numbers WHERE assigned_to IN ($placeholders) AND status='active'");
        $stmt->execute($userIds);
        $totalNumbers = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE parent_id = ? AND status='active'");
        $stmt->execute([$userId]);
        $totalUsers = $stmt->fetchColumn();
    }

    return [
        'today_sms'    => (int)$todaySms,
        'week_sms'     => (int)$weekSms,
        'today_profit' => number_format((float)$todayProfit, 6),
        'week_profit'  => number_format((float)$weekProfit, 6),
        'total_numbers' => (int)$totalNumbers,
        'total_users'   => (int)$totalUsers,
    ];
}

// ─── Helpers ───────────────────────────────────────────────────────────────────

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function flashMessage(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function roleLabel(string $role): string {
    return match($role) {
        'admin'         => '<span class="badge bg-danger">Admin</span>',
        'manager'       => '<span class="badge bg-warning text-dark">Manager</span>',
        'reseller'      => '<span class="badge bg-primary">Reseller</span>',
        'sub_reseller'  => '<span class="badge bg-info text-dark">Sub-Reseller</span>',
        default         => '<span class="badge bg-secondary">'.h($role).'</span>',
    };
}

function statusLabel(string $status): string {
    return match($status) {
        'active'   => '<span class="badge bg-success">Active</span>',
        'blocked'  => '<span class="badge bg-danger">Blocked</span>',
        'pending'  => '<span class="badge bg-warning text-dark">Pending</span>',
        'inactive' => '<span class="badge bg-secondary">Inactive</span>',
        default    => '<span class="badge bg-secondary">'.h($status).'</span>',
    };
}

function countryName(string $code): string {
    $countries = [
        'MM' => 'Myanmar', 'US' => 'United States', 'GB' => 'United Kingdom',
        'IN' => 'India', 'PH' => 'Philippines', 'ID' => 'Indonesia',
        'VN' => 'Vietnam', 'TH' => 'Thailand', 'MY' => 'Malaysia',
        'SG' => 'Singapore', 'AU' => 'Australia', 'CA' => 'Canada',
        'DE' => 'Germany', 'FR' => 'France', 'JP' => 'Japan',
        'KR' => 'South Korea', 'BR' => 'Brazil', 'MX' => 'Mexico',
        'RU' => 'Russia', 'CN' => 'China', 'NG' => 'Nigeria',
        'ZA' => 'South Africa', 'EG' => 'Egypt', 'TR' => 'Turkey',
        'SA' => 'Saudi Arabia', 'AE' => 'UAE', 'PK' => 'Pakistan',
        'BD' => 'Bangladesh', 'LK' => 'Sri Lanka', 'NP' => 'Nepal',
    ];
    return $countries[strtoupper($code)] ?? $code;
}

function allCountries(): array {
    return [
        'MM' => 'Myanmar', 'US' => 'United States', 'GB' => 'United Kingdom',
        'IN' => 'India', 'PH' => 'Philippines', 'ID' => 'Indonesia',
        'VN' => 'Vietnam', 'TH' => 'Thailand', 'MY' => 'Malaysia',
        'SG' => 'Singapore', 'AU' => 'Australia', 'CA' => 'Canada',
        'DE' => 'Germany', 'FR' => 'France', 'JP' => 'Japan',
        'KR' => 'South Korea', 'BR' => 'Brazil', 'MX' => 'Mexico',
        'RU' => 'Russia', 'CN' => 'China', 'NG' => 'Nigeria',
        'ZA' => 'South Africa', 'EG' => 'Egypt', 'TR' => 'Turkey',
        'SA' => 'Saudi Arabia', 'AE' => 'UAE', 'PK' => 'Pakistan',
        'BD' => 'Bangladesh', 'LK' => 'Sri Lanka', 'NP' => 'Nepal',
    ];
}
