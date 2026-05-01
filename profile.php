<?php
require_once __DIR__ . '/functions.php';
requireLogin();
$pageTitle = 'Profile & API Token';
$user   = getCurrentUser();
$userId = (int)$user['id'];
$pdo    = getDB();
$newToken = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $updates  = ['email = ?'];
        $params   = [$email ?: null];
        if ($password) {
            if (strlen($password) < 6) {
                flashMessage('danger', 'Password must be at least 6 characters');
                redirect(APP_URL . '/profile.php');
            }
            $updates[] = 'password = ?';
            $params[]  = password_hash($password, PASSWORD_DEFAULT);
        }
        $params[] = $userId;
        $pdo->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
        flashMessage('success', 'Profile updated successfully');
        redirect(APP_URL . '/profile.php');
    }

    if ($action === 'generate_token') {
        $newToken = generateApiToken($userId);
        flashMessage('success', 'New API token generated. Copy it now — it will not be shown again in full.');
    }

    if ($action === 'revoke_token') {
        $pdo->prepare("DELETE FROM api_tokens WHERE user_id = ?")->execute([$userId]);
        flashMessage('success', 'API token revoked');
        redirect(APP_URL . '/profile.php');
    }
}

$tokenRow = getTokenForUser($userId);
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h2><i class="ri-user-settings-line me-2"></i>Profile & API Token</h2>
  <nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Profile</li>
  </ol></nav>
</div>

<div class="row g-4">
  <!-- Profile Card -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><i class="ri-user-line me-1"></i>Profile Information</div>
      <div class="card-body">
        <form method="POST" action="">
          <input type="hidden" name="action" value="update_profile">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" value="<?= h($user['username']) ?>" disabled>
            <div class="form-text">Username cannot be changed.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
            <input type="text" class="form-control" value="<?= ucfirst(str_replace('_',' ',$user['role'])) ?>" disabled>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= h($user['email'] ?? '') ?>" placeholder="your@email.com">
          </div>
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current">
          </div>
          <div class="mb-3">
            <label class="form-label">Account Created</label>
            <input type="text" class="form-control" value="<?= h($user['created_at']) ?>" disabled>
          </div>
          <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i>Save Changes</button>
        </form>
      </div>
    </div>
  </div>

  <!-- API Token Card -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><i class="ri-key-line me-1"></i>API Token</div>
      <div class="card-body">
        <p class="text-muted" style="font-size:.875rem;">
          Use your API token to retrieve OTPs programmatically via the REST API.
          Keep it secret — anyone with this token can access your OTPs.
        </p>

        <?php if ($newToken): ?>
          <!-- Show new token once -->
          <div class="alert alert-warning">
            <strong><i class="ri-alert-line me-1"></i>Copy this token now!</strong>
            It will not be shown in full again.
          </div>
          <div class="token-box mb-3" id="tokenDisplay"><?= h($newToken) ?></div>
          <button class="btn btn-sm btn-outline-secondary mb-3" onclick="copyToClipboard('<?= h($newToken) ?>')">
            <i class="ri-clipboard-line me-1"></i>Copy Token
          </button>
        <?php elseif ($tokenRow): ?>
          <div class="mb-3">
            <label class="form-label">Your Token</label>
            <div class="token-box">••••••••••••••••••••••••••••••••••••••••••••<?= h(substr($tokenRow['token'], -4)) ?></div>
            <div class="form-text">
              Last used: <?= $tokenRow['last_used_at'] ? h($tokenRow['last_used_at']) : 'Never' ?><br>
              Created: <?= h($tokenRow['created_at']) ?>
            </div>
          </div>
        <?php else: ?>
          <p class="text-muted">No API token generated yet.</p>
        <?php endif; ?>

        <div class="d-flex gap-2 flex-wrap">
          <form method="POST" action="" class="d-inline">
            <input type="hidden" name="action" value="generate_token">
            <button type="submit" class="btn btn-primary" onclick="return confirm('This will invalidate any existing token. Continue?')">
              <i class="ri-refresh-line me-1"></i><?= $tokenRow ? 'Regenerate Token' : 'Generate Token' ?>
            </button>
          </form>
          <?php if ($tokenRow): ?>
          <form method="POST" action="" class="d-inline">
            <input type="hidden" name="action" value="revoke_token">
            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Revoke token? You will need to generate a new one.')">
              <i class="ri-delete-bin-line me-1"></i>Revoke Token
            </button>
          </form>
          <?php endif; ?>
        </div>

        <hr>
        <h6 class="fw-bold">API Usage</h6>
        <p style="font-size:.82rem;" class="text-muted mb-2">Fetch your OTPs:</p>
        <div class="token-box" style="font-size:.8rem;">
          GET <?= APP_URL ?>/api/otps.php?token=YOUR_TOKEN&from=2026-01-01&to=2026-12-31
        </div>
        <div class="mt-2" style="font-size:.8rem;">
          <strong>Response:</strong><br>
          <code>{"status":"success","data":[{"number":"+959...","otp":"123456",...}]}</code>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
