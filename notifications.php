<?php
require_once __DIR__ . '/functions.php';
requireLogin();
$pageTitle = 'Notifications';
$user   = getCurrentUser();
$userId = (int)$user['id'];
$pdo    = getDB();

// Mark all as read
if (isset($_GET['mark_all'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$userId]);
    redirect(APP_URL . '/notifications.php');
}
if (isset($_GET['mark'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([(int)$_GET['mark'], $userId]);
    redirect(APP_URL . '/notifications.php');
}
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM notifications WHERE id=? AND user_id=?")->execute([(int)$_GET['delete'], $userId]);
    redirect(APP_URL . '/notifications.php');
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h2><i class="ri-notification-line me-2"></i>Notifications</h2>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Notifications</li>
    </ol></nav>
  </div>
  <?php if (!empty($notifications)): ?>
  <a href="?mark_all=1" class="btn btn-outline-secondary btn-sm">
    <i class="ri-check-double-line me-1"></i>Mark All Read
  </a>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-body p-0">
    <?php if (empty($notifications)): ?>
      <div class="text-center text-muted py-5">
        <i class="ri-notification-off-line" style="font-size:3rem;opacity:.3;"></i>
        <p class="mt-2 mb-0">No notifications yet.</p>
      </div>
    <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($notifications as $n): ?>
        <li class="list-group-item d-flex justify-content-between align-items-start py-3 <?= !$n['is_read'] ? 'bg-light' : '' ?>">
          <div class="d-flex gap-3 align-items-start">
            <div style="margin-top:2px;">
              <?php if (!$n['is_read']): ?>
                <span class="badge rounded-pill bg-primary">New</span>
              <?php else: ?>
                <i class="ri-checkbox-circle-line text-muted"></i>
              <?php endif; ?>
            </div>
            <div>
              <div style="font-size:.875rem;"><?= h($n['message']) ?></div>
              <small class="text-muted"><?= h($n['created_at']) ?></small>
            </div>
          </div>
          <div class="d-flex gap-2 ms-3 flex-shrink-0">
            <?php if (!$n['is_read']): ?>
            <a href="?mark=<?= (int)$n['id'] ?>" class="btn btn-xs btn-outline-secondary" title="Mark read">
              <i class="ri-check-line"></i>
            </a>
            <?php endif; ?>
            <a href="?delete=<?= (int)$n['id'] ?>" class="btn btn-xs btn-outline-danger" title="Delete"
               onclick="return confirm('Delete this notification?')">
              <i class="ri-delete-bin-line"></i>
            </a>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
