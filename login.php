<?php
/**
 * Sigma SMS A2P — Login Page
 * Glass-morphism design with animated entrance.
 */
require_once __DIR__ . '/functions.php';
if (isLoggedIn()) {
    redirect(APP_URL . '/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        $login    = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($login) || empty($password)) {
            $error = 'Please enter your username/email and password.';
        } else {
            $pdo  = getDB();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status != 'blocked'");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                redirect(APP_URL . '/dashboard.php');
            } else {
                $error = 'Invalid credentials or account is blocked.';
            }
        }
    }
}

$flash    = getFlash();
$siteName = getSetting('site_name', APP_NAME);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?= h($siteName) ?></title>
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Remixicon -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.min.css">
    <style>
        /* ── Background ── */
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f0c29 0%, #1e3a5f 50%, #0d1b2a 100%);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            overflow: hidden;
            position: relative;
        }

        /* Animated background orbs */
        body::before, body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: .35;
            animation: float 8s ease-in-out infinite;
            pointer-events: none;
        }
        body::before {
            width: 500px; height: 500px;
            background: radial-gradient(circle, #4f46e5, transparent);
            top: -100px; left: -100px;
        }
        body::after {
            width: 400px; height: 400px;
            background: radial-gradient(circle, #0ea5e9, transparent);
            bottom: -80px; right: -80px;
            animation-delay: -4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50%       { transform: translateY(-30px) scale(1.05); }
        }

        /* Particle dots */
        .particles {
            position: fixed; inset: 0; pointer-events: none; overflow: hidden;
        }
        .particle {
            position: absolute;
            width: 3px; height: 3px;
            background: rgba(255,255,255,.4);
            border-radius: 50%;
            animation: rise linear infinite;
        }
        @keyframes rise {
            0%   { transform: translateY(100vh) scale(0); opacity: 0; }
            10%  { opacity: 1; }
            90%  { opacity: .5; }
            100% { transform: translateY(-10vh) scale(1); opacity: 0; }
        }

        /* ── Card ── */
        .login-wrapper {
            width: 100%;
            max-width: 440px;
            padding: 1rem;
            animation: slideUp .6s cubic-bezier(.16,1,.3,1) both;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            background: rgba(255,255,255,.08);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 20px;
            padding: 2.5rem 2.25rem;
            box-shadow: 0 25px 60px rgba(0,0,0,.4), inset 0 1px 0 rgba(255,255,255,.1);
        }

        /* ── Brand ── */
        .brand-area {
            text-align: center;
            margin-bottom: 2rem;
        }
        .brand-icon {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, #4f46e5, #0ea5e9);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: .9rem;
            box-shadow: 0 8px 24px rgba(79,70,229,.4);
            animation: iconPulse 3s ease-in-out infinite;
        }
        @keyframes iconPulse {
            0%, 100% { box-shadow: 0 8px 24px rgba(79,70,229,.4); }
            50%       { box-shadow: 0 8px 40px rgba(79,70,229,.7); }
        }
        .brand-title {
            color: #fff;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: -.02em;
        }
        .brand-sub {
            color: rgba(255,255,255,.55);
            font-size: .82rem;
            margin: .25rem 0 0;
        }

        /* ── Form ── */
        .form-label {
            color: rgba(255,255,255,.8);
            font-size: .82rem;
            font-weight: 600;
            margin-bottom: .4rem;
            letter-spacing: .02em;
        }
        .input-group-glass {
            position: relative;
        }
        .input-icon {
            position: absolute;
            left: .9rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,.4);
            font-size: 1rem;
            z-index: 5;
            pointer-events: none;
        }
        .form-control-glass {
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 10px;
            color: #fff;
            padding: .7rem .9rem .7rem 2.5rem;
            font-size: .9rem;
            transition: border-color .2s, background .2s, box-shadow .2s;
            width: 100%;
        }
        .form-control-glass::placeholder { color: rgba(255,255,255,.3); }
        .form-control-glass:focus {
            outline: none;
            background: rgba(255,255,255,.12);
            border-color: rgba(79,70,229,.7);
            box-shadow: 0 0 0 3px rgba(79,70,229,.25);
            color: #fff;
        }
        .form-control-glass:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 100px rgba(30,58,95,.9) inset;
            -webkit-text-fill-color: #fff;
        }

        /* ── Button ── */
        .btn-signin {
            width: 100%;
            padding: .75rem;
            background: linear-gradient(135deg, #4f46e5, #0ea5e9);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: .95rem;
            font-weight: 600;
            letter-spacing: .02em;
            cursor: pointer;
            transition: transform .15s, box-shadow .15s, opacity .15s;
            box-shadow: 0 4px 20px rgba(79,70,229,.4);
            position: relative;
            overflow: hidden;
        }
        .btn-signin::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,.15), transparent);
            opacity: 0;
            transition: opacity .2s;
        }
        .btn-signin:hover { transform: translateY(-1px); box-shadow: 0 8px 30px rgba(79,70,229,.5); }
        .btn-signin:hover::after { opacity: 1; }
        .btn-signin:active { transform: translateY(0); }
        .btn-signin:disabled { opacity: .7; cursor: not-allowed; transform: none; }

        /* ── Alert ── */
        .alert-glass {
            background: rgba(220,53,69,.15);
            border: 1px solid rgba(220,53,69,.35);
            border-radius: 10px;
            color: #fca5a5;
            font-size: .85rem;
            padding: .7rem 1rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: .5rem;
            animation: shakeIn .4s ease;
        }
        .alert-glass-success {
            background: rgba(25,135,84,.15);
            border-color: rgba(25,135,84,.35);
            color: #86efac;
        }
        @keyframes shakeIn {
            0%,100% { transform: translateX(0); }
            20%,60% { transform: translateX(-6px); }
            40%,80% { transform: translateX(6px); }
        }

        /* ── Footer note ── */
        .login-note {
            text-align: center;
            color: rgba(255,255,255,.3);
            font-size: .75rem;
            margin-top: 1.5rem;
        }
        .login-note a { color: rgba(255,255,255,.5); text-decoration: none; }
        .login-note a:hover { color: rgba(255,255,255,.8); }

        /* ── Divider ── */
        .form-divider {
            border: none;
            border-top: 1px solid rgba(255,255,255,.1);
            margin: 1.5rem 0;
        }
    </style>
</head>
<body>

<!-- Animated particles -->
<div class="particles" id="particles"></div>

<div class="login-wrapper">
    <div class="login-card">

        <!-- Brand -->
        <div class="brand-area">
            <div class="brand-icon">🔐</div>
            <h1 class="brand-title"><?= h($siteName) ?></h1>
            <p class="brand-sub">A2P OTP Management Panel</p>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
        <div class="alert-glass">
            <i class="ri-error-warning-line"></i>
            <?= h($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($flash): ?>
        <div class="alert-glass <?= $flash['type'] === 'success' ? 'alert-glass-success' : '' ?>">
            <i class="ri-<?= $flash['type'] === 'success' ? 'checkbox-circle' : 'error-warning' ?>-line"></i>
            <?= h($flash['msg']) ?>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="" id="loginForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

            <div class="mb-4">
                <label class="form-label" for="loginInput">
                    <i class="ri-user-line me-1"></i>Username or Email
                </label>
                <div class="input-group-glass">
                    <i class="ri-user-3-line input-icon"></i>
                    <input
                        type="text"
                        id="loginInput"
                        name="login"
                        class="form-control-glass"
                        placeholder="Enter username or email"
                        value="<?= h($_POST['login'] ?? '') ?>"
                        required
                        autofocus
                        autocomplete="username"
                    >
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label" for="passwordInput">
                    <i class="ri-lock-line me-1"></i>Password
                </label>
                <div class="input-group-glass" style="position:relative;">
                    <i class="ri-lock-2-line input-icon"></i>
                    <input
                        type="password"
                        id="passwordInput"
                        name="password"
                        class="form-control-glass"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                        style="padding-right:2.8rem;"
                    >
                    <button type="button" onclick="togglePwd()" style="position:absolute;right:.8rem;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,.4);cursor:pointer;padding:0;font-size:1rem;" id="pwdToggle">
                        <i class="ri-eye-line" id="pwdIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-signin" id="signinBtn">
                <i class="ri-login-circle-line me-2"></i>Sign In
            </button>
        </form>

        <hr class="form-divider">

        <p class="login-note">
            Default credentials: <strong style="color:rgba(255,255,255,.5);">admin</strong> /
            <strong style="color:rgba(255,255,255,.5);">password</strong>
            — change immediately after login.
        </p>
    </div>
</div>

<!-- Bootstrap JS (for any future use) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Generate floating particles
(function() {
    var container = document.getElementById('particles');
    for (var i = 0; i < 25; i++) {
        var p = document.createElement('div');
        p.className = 'particle';
        p.style.left = Math.random() * 100 + 'vw';
        p.style.animationDuration = (8 + Math.random() * 12) + 's';
        p.style.animationDelay = (-Math.random() * 20) + 's';
        p.style.width = p.style.height = (2 + Math.random() * 3) + 'px';
        p.style.opacity = (0.2 + Math.random() * 0.5).toString();
        container.appendChild(p);
    }
})();

// Toggle password visibility
function togglePwd() {
    var inp  = document.getElementById('passwordInput');
    var icon = document.getElementById('pwdIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'ri-eye-off-line';
    } else {
        inp.type = 'password';
        icon.className = 'ri-eye-line';
    }
}

// Button loading state on submit
document.getElementById('loginForm').addEventListener('submit', function() {
    var btn = document.getElementById('signinBtn');
    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle;margin-right:.5rem;"></span>Signing in…';
});
</script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
</body>
</html>
