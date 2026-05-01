<?php
/**
 * Sigma SMS A2P — Web Installer
 *
 * Visit this file ONCE in your browser to set up the database and admin account.
 * DELETE this file after successful installation!
 *
 * Railway: Use environment variables MYSQLHOST, MYSQLDATABASE, MYSQLUSER, MYSQLPASSWORD
 */

// Prevent running if config already exists and DB is set up
// (comment out this block if you need to re-run the installer)
if (file_exists(__DIR__ . '/config.php')) {
    $cfg = file_get_contents(__DIR__ . '/config.php');
    if (strpos($cfg, 'DB_HOST') !== false && strpos($cfg, 'localhost') === false) {
        // Config exists with non-default host — show warning but allow re-install
    }
}

$error   = '';
$success = '';
$step    = 1;

// ── PHP version check ────────────────────────────────────────────────────────
if (PHP_VERSION_ID < 80000) {
    die('<div style="font-family:sans-serif;padding:2rem;color:#dc2626;background:#fef2f2;border-radius:8px;max-width:600px;margin:2rem auto;">
        <h2>PHP Version Error</h2>
        <p>Sigma SMS A2P requires PHP 8.0 or higher. You are running PHP ' . PHP_VERSION . '.</p>
    </div>');
}

// ── Extension check — must happen BEFORE any PDO usage ───────────────────────
$missingExts = [];
foreach (['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'] as $ext) {
    if (!extension_loaded($ext)) $missingExts[] = $ext;
}
// pdo_mysql is the critical one — block install if missing
$pdoMysqlMissing = !extension_loaded('pdo_mysql') || !extension_loaded('pdo');

// ── Auto-detect values from Railway environment variables ────────────────────
$envDbHost = getenv('MYSQLHOST')     ?: getenv('DB_HOST')     ?: 'localhost';
$envDbName = getenv('MYSQLDATABASE') ?: getenv('DB_NAME')     ?: 'sigma_sms_a2p';
$envDbUser = getenv('MYSQLUSER')     ?: getenv('DB_USER')     ?: 'root';
$envDbPass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASS')     ?: '';
$envDbPort = getenv('MYSQLPORT')     ?: getenv('DB_PORT')     ?: '3306';

// Auto-detect app URL
$proto    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path     = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$guessUrl = "$proto://$host$path";

// ── Handle form submission ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost    = trim($_POST['db_host']     ?? $envDbHost);
    $dbPort    = trim($_POST['db_port']     ?? $envDbPort);
    $dbName    = trim($_POST['db_name']     ?? $envDbName);
    $dbUser    = trim($_POST['db_user']     ?? $envDbUser);
    $dbPass    = $_POST['db_pass']          ?? $envDbPass;
    $appUrl    = rtrim(trim($_POST['app_url'] ?? $guessUrl), '/');
    $adminUser = trim($_POST['admin_user']  ?? 'admin');
    $adminPass = $_POST['admin_pass']       ?? '';
    $adminEmail= trim($_POST['admin_email'] ?? '');

    // Validate
    if (empty($adminUser)) {
        $error = 'Admin username is required.';
    } elseif (strlen($adminPass) < 6) {
        $error = 'Admin password must be at least 6 characters.';
    } elseif (empty($appUrl)) {
        $error = 'App URL is required.';
    } else {
        try {
            // ── Check extensions before attempting PDO ───────────────────────
            if (!extension_loaded('pdo')) {
                throw new RuntimeException('PHP extension <strong>pdo</strong> is not loaded. Enable it in php.ini.');
            }
            if (!extension_loaded('pdo_mysql')) {
                throw new RuntimeException('PHP extension <strong>pdo_mysql</strong> is not loaded. See fix instructions below.');
            }
            $dsn = "mysql:host=$dbHost;port=$dbPort;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // Create database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");

            // Read and execute schema.sql statement by statement
            $schemaFile = __DIR__ . '/schema.sql';
            if (!file_exists($schemaFile)) {
                throw new RuntimeException('schema.sql not found. Please ensure it is in the same directory as install.php.');
            }

            $schema = file_get_contents($schemaFile);

            // Remove comments and split by semicolons
            $schema = preg_replace('/--[^\n]*\n/', "\n", $schema);
            $schema = preg_replace('/\/\*.*?\*\//s', '', $schema);
            $statements = array_filter(
                array_map('trim', explode(';', $schema)),
                fn($s) => !empty($s) && strlen(trim($s)) > 3
            );

            $tableErrors = [];
            foreach ($statements as $stmt) {
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    $code = $e->getCode();
                    // 42S01 = table already exists, 42S21 = column already exists — safe to ignore
                    if (!in_array($code, ['42S01', '42S21', '23000'])) {
                        $tableErrors[] = $e->getMessage();
                    }
                }
            }

            // Insert or update admin user
            $hash = password_hash($adminPass, PASSWORD_BCRYPT);

            $existsStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
            $existsStmt->execute();
            $adminRow = $existsStmt->fetch();

            if ($adminRow) {
                $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?")
                    ->execute([$adminUser, $adminEmail ?: null, $hash, $adminRow['id']]);
            } else {
                $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')")
                    ->execute([$adminUser, $adminEmail ?: null, $hash]);
            }

            // Ensure default settings exist
            $defaultSettings = [
                'last_fetch'  => '2000-01-01 00:00:00',
                'site_name'   => 'Sigma SMS A2P',
                'otp_api_url' => 'https://tempnum.net/api/public/otps',
            ];
            foreach ($defaultSettings as $key => $val) {
                $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)")
                    ->execute([$key, $val]);
            }

            // Write config.php — full production version
            $dbPassEscaped = addslashes($dbPass);
            $generatedAt   = date('Y-m-d H:i:s');
            $configContent = <<<PHP
<?php
/**
 * Sigma SMS A2P — Configuration
 * Generated by installer on $generatedAt
 * DO NOT expose this file publicly.
 */

// ── Database ──────────────────────────────────────────────────────────────────
// Railway env vars take precedence over hardcoded values below.
define('DB_HOST',    getenv('MYSQLHOST')     ?: '$dbHost');
define('DB_PORT',    getenv('MYSQLPORT')     ?: '$dbPort');
define('DB_NAME',    getenv('MYSQLDATABASE') ?: '$dbName');
define('DB_USER',    getenv('MYSQLUSER')     ?: '$dbUser');
define('DB_PASS',    getenv('MYSQLPASSWORD') ?: '$dbPassEscaped');
define('DB_CHARSET', 'utf8mb4');

// ── Application ───────────────────────────────────────────────────────────────
define('APP_NAME',    'Sigma SMS A2P');
define('APP_VERSION', '1.0.0');

if (!defined('APP_URL')) {
    \$_appUrl = getenv('APP_URL') ?: '$appUrl';
    define('APP_URL', rtrim(\$_appUrl, '/'));
}

// ── OTP API ───────────────────────────────────────────────────────────────────
define('OTP_API_URL',        'https://tempnum.net/api/public/otps');
define('OTP_FETCH_INTERVAL', 60);

// ── Session / Timezone / Errors ───────────────────────────────────────────────
define('SESSION_LIFETIME', 86400);
date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ── PDO connection (singleton) ────────────────────────────────────────────────
function getDB(): PDO {
    static \$pdo = null;
    if (\$pdo !== null) return \$pdo;
    if (!extension_loaded('pdo_mysql')) {
        http_response_code(500);
        die('<h2 style="font-family:sans-serif;color:#dc2626;padding:2rem">Missing PHP extension: pdo_mysql<br><small>Run: sudo apt-get install php-mysql</small></h2>');
    }
    \$dsn = 'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset='.DB_CHARSET;
    try {
        \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException \$e) {
        http_response_code(500);
        \$msg = htmlspecialchars(\$e->getMessage());
        die('<h2 style="font-family:sans-serif;color:#dc2626;padding:2rem">DB connection failed: '.\$msg.'<br><small><a href="'.APP_URL.'/install.php">Re-run installer</a></small></h2>');
    }
    return \$pdo;
}

// ── Session ───────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    \$_secure = (!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off')
             || ((\$_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => \$_secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
PHP;

            if (file_put_contents(__DIR__ . '/config.php', $configContent) === false) {
                throw new RuntimeException('Could not write config.php. Check file permissions.');
            }

            $loginUrl = $appUrl . '/login.php';
            $success  = "Installation complete!";
            $step     = 2;

        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install — Sigma SMS A2P</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
body { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.install-card { max-width: 600px; width: 100%; background: #fff; border-radius: 16px; box-shadow: 0 25px 60px rgba(0,0,0,.3); padding: 2.25rem; }
.install-card h2 { color: #0f172a; font-weight: 800; font-size: 1.5rem; }
.install-card h5 { color: #4f46e5; font-weight: 700; font-size: .9rem; text-transform: uppercase; letter-spacing: .06em; margin-bottom: .75rem; }
.form-label { font-size: .82rem; font-weight: 600; color: #374151; }
.form-control { border-radius: 8px; font-size: .875rem; }
.form-control:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.15); }
.btn-install { background: linear-gradient(135deg, #4f46e5, #0ea5e9); border: none; color: #fff; padding: .75rem; font-weight: 600; width: 100%; border-radius: 10px; font-size: .95rem; transition: opacity .2s; }
.btn-install:hover { opacity: .9; color: #fff; }
.section-divider { border: none; border-top: 1px solid #e2e8f0; margin: 1.5rem 0; }
.env-badge { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: .4rem .75rem; font-size: .78rem; color: #065f46; display: inline-flex; align-items: center; gap: .4rem; }
.ext-missing { background: #fef2f2 !important; border-color: #fecaca !important; color: #991b1b !important; }
.success-box { text-align: center; padding: 1.5rem 0; }
.success-icon { font-size: 4rem; margin-bottom: 1rem; }
</style>
</head>
<body>
<div class="install-card">

  <?php if ($step === 2): ?>
  <!-- ── Success Screen ── -->
  <div class="success-box">
    <div class="success-icon">✅</div>
    <h2 class="mb-2">Installation Complete!</h2>
    <p class="text-muted mb-4">Your Sigma SMS A2P panel is ready to use.</p>

    <div class="alert alert-warning text-start mb-4" style="border-radius:10px;">
      <strong>⚠️ Important:</strong> Delete <code>install.php</code> from your server immediately to prevent unauthorized re-installation.
    </div>

    <a href="<?= htmlspecialchars($appUrl . '/login.php') ?>" class="btn btn-install d-inline-block" style="width:auto;padding:.75rem 2rem;">
      Go to Login →
    </a>

    <div class="mt-3 text-muted" style="font-size:.82rem;">
      Default credentials: <strong><?= htmlspecialchars($adminUser) ?></strong> / <em>your chosen password</em>
    </div>
  </div>

  <?php else: ?>
  <!-- ── Install Form ── -->
  <div class="d-flex align-items-center gap-3 mb-4">
    <div style="width:48px;height:48px;background:linear-gradient(135deg,#4f46e5,#0ea5e9);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;">🔐</div>
    <div>
      <h2 class="mb-0">Sigma SMS A2P</h2>
      <p class="text-muted mb-0" style="font-size:.85rem;">Web Installer — run once, then delete this file</p>
    </div>
  </div>

  <!-- PHP info -->
  <div class="d-flex gap-2 mb-4 flex-wrap">
    <span class="env-badge">✓ PHP <?= PHP_VERSION ?></span>
    <?php foreach (['pdo' => 'PDO', 'pdo_mysql' => 'PDO MySQL', 'curl' => 'cURL', 'json' => 'JSON', 'mbstring' => 'mbstring'] as $ext => $label): ?>
    <span class="env-badge <?= extension_loaded($ext) ? '' : 'ext-missing' ?>">
      <?= extension_loaded($ext) ? '✓' : '✗' ?> <?= $label ?>
    </span>
    <?php endforeach; ?>
    <?php if ($envDbHost !== 'localhost'): ?>
    <span class="env-badge">🚂 Railway env detected</span>
    <?php endif; ?>
  </div>

  <?php if ($pdoMysqlMissing): ?>
  <!-- ── Missing extension warning ── -->
  <div class="alert alert-danger mb-4" style="border-radius:10px;">
    <h6 class="fw-bold mb-2">⚠️ Missing PHP Extension: <code>pdo_mysql</code></h6>
    <p class="mb-2" style="font-size:.875rem;">This extension is required to connect to MySQL. The installer cannot proceed until it is enabled.</p>
    <hr style="border-color:rgba(220,53,69,.3);">
    <p class="mb-1 fw-semibold" style="font-size:.82rem;">How to fix:</p>
    <div style="font-size:.82rem;">
      <p class="mb-1"><strong>Ubuntu/Debian:</strong></p>
      <code style="display:block;background:#1e293b;color:#e2e8f0;padding:.5rem .75rem;border-radius:6px;margin-bottom:.75rem;">sudo apt-get install php<?= PHP_MAJOR_VERSION ?>.<?= PHP_MINOR_VERSION ?>-mysql &amp;&amp; sudo service apache2 restart</code>
      <p class="mb-1"><strong>CentOS/RHEL:</strong></p>
      <code style="display:block;background:#1e293b;color:#e2e8f0;padding:.5rem .75rem;border-radius:6px;margin-bottom:.75rem;">sudo yum install php-mysqlnd &amp;&amp; sudo systemctl restart httpd</code>
      <p class="mb-1"><strong>Windows (XAMPP/WAMP):</strong> Open <code>php.ini</code>, uncomment <code>extension=pdo_mysql</code>, restart Apache.</p>
      <p class="mb-1"><strong>Docker:</strong> Add <code>RUN docker-php-ext-install pdo pdo_mysql</code> to your Dockerfile.</p>
      <p class="mb-0"><strong>cPanel/Shared hosting:</strong> Go to PHP Selector → enable <code>pdo_mysql</code> → Save.</p>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="alert alert-danger mb-4" style="border-radius:10px;font-size:.875rem;">
    <strong>Error:</strong> <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="">

    <!-- Database -->
    <h5>Database Configuration</h5>
    <div class="row g-3 mb-3">
      <div class="col-8">
        <label class="form-label">DB Host</label>
        <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($_POST['db_host'] ?? $envDbHost) ?>" required>
      </div>
      <div class="col-4">
        <label class="form-label">Port</label>
        <input type="text" name="db_port" class="form-control" value="<?= htmlspecialchars($_POST['db_port'] ?? $envDbPort) ?>">
      </div>
      <div class="col-12">
        <label class="form-label">Database Name</label>
        <input type="text" name="db_name" class="form-control" value="<?= htmlspecialchars($_POST['db_name'] ?? $envDbName) ?>" required>
      </div>
      <div class="col-6">
        <label class="form-label">DB Username</label>
        <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($_POST['db_user'] ?? $envDbUser) ?>" required>
      </div>
      <div class="col-6">
        <label class="form-label">DB Password</label>
        <input type="password" name="db_pass" class="form-control" placeholder="Leave blank if none" value="<?= htmlspecialchars($_POST['db_pass'] ?? ($envDbPass ? '••••••' : '')) ?>">
      </div>
    </div>

    <hr class="section-divider">

    <!-- Application -->
    <h5>Application Settings</h5>
    <div class="mb-3">
      <label class="form-label">App URL <small class="text-muted fw-normal">(no trailing slash)</small></label>
      <input type="text" name="app_url" class="form-control" value="<?= htmlspecialchars($_POST['app_url'] ?? $guessUrl) ?>" required>
      <div class="form-text">e.g. <code>https://your-app.railway.app</code> or <code>http://localhost:8080</code></div>
    </div>

    <hr class="section-divider">

    <!-- Admin Account -->
    <h5>Admin Account</h5>
    <div class="row g-3 mb-4">
      <div class="col-6">
        <label class="form-label">Username *</label>
        <input type="text" name="admin_user" class="form-control" value="<?= htmlspecialchars($_POST['admin_user'] ?? 'admin') ?>" required>
      </div>
      <div class="col-6">
        <label class="form-label">Password * <small class="text-muted fw-normal">(min 6 chars)</small></label>
        <input type="password" name="admin_pass" class="form-control" required>
      </div>
      <div class="col-12">
        <label class="form-label">Email <small class="text-muted fw-normal">(optional)</small></label>
        <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" placeholder="admin@example.com">
      </div>
    </div>

    <button type="submit" class="btn btn-install" <?= $pdoMysqlMissing ? 'disabled title="Fix missing extensions first"' : '' ?>>
      <?= $pdoMysqlMissing ? '⚠️ Fix Missing Extensions First' : 'Install Now →' ?>
    </button>
  </form>
  <?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
