<?php
require_once __DIR__ . '/functions.php';
requireLogin();
$pageTitle = 'Credit Notes';
$user   = getCurrentUser();
$userId = (int)$user['id'];
$role   = $user['role'];
$pdo    = getDB();

// Only admin/manager can create credit notes for others
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole('manager');
    $action      = $_POST['action'] ?? '';
    $targetUser  = (int)($_POST['user_id'] ?? 0);
    $amount      = (float)($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $currency    = trim($_POST['currency'] ?? 'USD');

    if ($action === 'add') {
        if ($amount <= 0) { flashMessage('danger', 'Amount must be positive.'); }
        else {
            $pdo->prepare("INSERT INTO credit_notes (user_id, amount, currency, description) VALUES (?,?,?,?)")
                ->execute([$targetUser ?: $userId, $amount, $currency, $description]);
            flashMessage('success', 'Credit note created.');
        }
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM credit_notes WHERE id=?")->execute([$id]);
        flashMessage('success', 'Credit note deleted.');
    }
    redirect(APP_URL . '/credit_notes.php');
}

// Scope
if (in_array($role, ['admin','manager'])) {
    $stmt = $pdo->query("SELECT cn.*, u.username FROM credit_notes cn JOIN users u ON cn.user_id=u.id ORDER BY cn.created_at DESC");
} else {
    $stmt = $pdo->prepare("SELECT cn.*, u.username FROM credit_notes cn JOIN users u ON cn.user_id=u.id WHERE cn.user_id=? ORDER BY cn.created_at DESC");
    $stmt->execute([$userId]);
}
$notes = $stmt->fetchAll();

// User list for admin/manager
$users = [];
if (in_array($role, ['admin','manager'])) {
    $users = $pdo->query("SELECT id, username, role FROM users WHERE status='active' ORDER BY username")->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h2><i class="ri-file-list-3-line me-2"></i>Credit Notes</h2>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Credit Notes</li>
    </ol></nav>
  </div>
  <?php if (in_array($role, ['admin','manager'])): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCreditModal">
    <i class="ri-add-line me-1"></i>New Credit Note
  </button>
  <?php endif; ?>
</div>

<!-- Summary -->
<?php
$totalCredit = array_sum(array_column($notes, 'amount'));
?>
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="stat-card bg-stat-3">
      <div><div class="stat-val">$<?= number_format($totalCredit, 2) ?></div><div class="stat-label">Total Credits</div></div>
      <div class="stat-icon"><i class="ri-coins-line"></i></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card bg-stat-2">
      <div><div class="stat-val"><?= count($notes) ?></div><div class="stat-label">Total Notes</div></div>
      <div class="stat-icon"><i class="ri-file-list-line"></i></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="creditTable">
        <thead>
          <tr>
            <th>#</th>
            <?php if (in_array($role, ['admin','manager'])): ?><th>User</th><?php endif; ?>
            <th>Amount</th>
            <th>Currency</th>
            <th>Description</th>
            <th>Created At</th>
            <?php if (in_array($role, ['admin','manager'])): ?><th>Actions</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($notes)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No credit notes found.</td></tr>
          <?php else: foreach ($notes as $n): ?>
          <tr>
            <td><?= (int)$n['id'] ?></td>
            <?php if (in_array($role, ['admin','manager'])): ?>
            <td><?= h($n['username']) ?></td>
            <?php endif; ?>
            <td class="text-success fw-bold">$<?= number_format((float)$n['amount'], 2) ?></td>
            <td><?= h($n['currency']) ?></td>
            <td><?= h($n['description'] ?? '–') ?></td>
            <td><small class="text-muted"><?= h($n['created_at']) ?></small></td>
            <?php if (in_array($role, ['admin','manager'])): ?>
            <td>
              <form method="POST" class="d-inline" onsubmit="return confirm('Delete this credit note?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                <button type="submit" class="btn btn-xs btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Modal -->
<?php if (in_array($role, ['admin','manager'])): ?>
<div class="modal fade" id="addCreditModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">New Credit Note</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">User *</label>
            <select name="user_id" class="form-select select2" required>
              <option value="">Select user…</option>
              <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= h($u['username']) ?> (<?= h($u['role']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3">
            <div class="col-8">
              <label class="form-label">Amount *</label>
              <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
            </div>
            <div class="col-4">
              <label class="form-label">Currency</label>
              <select name="currency" class="form-select">
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
                <option value="GBP">GBP</option>
              </select>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Reason for credit…"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i>Create</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
$extraJs = <<<'JS'
<script>
$(document).ready(function() {
    $('#creditTable').DataTable({ pageLength: 25, order: [[0,'desc']] });
    if ($.fn.select2) $('[name="user_id"]').select2({ theme:'bootstrap-5', width:'100%', dropdownParent:$('#addCreditModal') });
});
</script>
JS;
include __DIR__ . '/includes/footer.php';
?>
