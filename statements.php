<?php
require_once __DIR__ . '/functions.php';
requireLogin();
requireRole('manager');
$pageTitle = 'Statements';
$user   = getCurrentUser();
$userId = (int)$user['id'];
$role   = $user['role'];
$pdo    = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action'] ?? '';
    $targetUser  = (int)($_POST['user_id'] ?? 0);
    $periodStart = trim($_POST['period_start'] ?? '');
    $periodEnd   = trim($_POST['period_end'] ?? '');

    if ($action === 'generate') {
        // Calculate total earnings from profit_log for this period
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(profit_amount),0) FROM profit_log WHERE user_id=? AND DATE(created_at) BETWEEN ? AND ?");
        $stmt->execute([$targetUser, $periodStart, $periodEnd]);
        $total = (float)$stmt->fetchColumn();

        $pdo->prepare("INSERT INTO statements (user_id, period_start, period_end, total_earnings) VALUES (?,?,?,?)")
            ->execute([$targetUser, $periodStart, $periodEnd, $total]);
        flashMessage('success', "Statement generated. Total earnings: \${$total}");
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM statements WHERE id=?")->execute([$id]);
        flashMessage('success', 'Statement deleted.');
    }
    redirect(APP_URL . '/statements.php');
}

$stmt = $pdo->query("SELECT s.*, u.username FROM statements s JOIN users u ON s.user_id=u.id ORDER BY s.id DESC");
$statements = $stmt->fetchAll();

$users = $pdo->query("SELECT id, username FROM users WHERE status='active' ORDER BY username")->fetchAll();
include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h2><i class="ri-file-chart-line me-2"></i>Statements</h2>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Statements</li>
    </ol></nav>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#genStmtModal">
    <i class="ri-file-add-line me-1"></i>Generate Statement
  </button>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="stmtTable">
        <thead>
          <tr>
            <th>#</th>
            <th>User</th>
            <th>Period Start</th>
            <th>Period End</th>
            <th>Total Earnings</th>
            <th>Currency</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($statements)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No statements generated yet.</td></tr>
          <?php else: foreach ($statements as $s): ?>
          <tr>
            <td><?= (int)$s['id'] ?></td>
            <td><?= h($s['username']) ?></td>
            <td><?= h($s['period_start']) ?></td>
            <td><?= h($s['period_end']) ?></td>
            <td class="text-success fw-bold">$<?= number_format((float)$s['total_earnings'], 2) ?></td>
            <td><?= h($s['currency']) ?></td>
            <td>
              <form method="POST" class="d-inline" onsubmit="return confirm('Delete statement?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button type="submit" class="btn btn-xs btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Generate Statement Modal -->
<div class="modal fade" id="genStmtModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <input type="hidden" name="action" value="generate">
        <div class="modal-header"><h5 class="modal-title">Generate Statement</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">User *</label>
            <select name="user_id" class="form-select select2" required>
              <option value="">Select user…</option>
              <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= h($u['username']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Period Start *</label>
              <input type="text" name="period_start" class="form-control flatpickr-date" required placeholder="YYYY-MM-DD">
            </div>
            <div class="col-6">
              <label class="form-label">Period End *</label>
              <input type="text" name="period_end" class="form-control flatpickr-date" required placeholder="YYYY-MM-DD">
            </div>
          </div>
          <div class="alert alert-info mt-3 mb-0" style="font-size:.825rem;">
            <i class="ri-information-line me-1"></i>Earnings will be auto-calculated from profit logs for the selected period.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="ri-file-chart-line me-1"></i>Generate</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraJs = <<<'JS'
<script>
$(document).ready(function() {
    $('#stmtTable').DataTable({ pageLength: 25, order: [[0,'desc']] });
    if ($.fn.select2) $('[name="user_id"]').select2({ theme:'bootstrap-5', width:'100%', dropdownParent:$('#genStmtModal') });
    if (typeof flatpickr !== 'undefined') flatpickr('.flatpickr-date', { dateFormat:'Y-m-d' });
});
</script>
JS;
include __DIR__ . '/includes/footer.php';
?>
