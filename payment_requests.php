<?php
require_once __DIR__ . '/functions.php';
requireLogin();
$pageTitle = 'Payment Requests';
$user   = getCurrentUser();
$userId = (int)$user['id'];
$role   = $user['role'];
$pdo    = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'submit_request') {
        $amount   = (float)($_POST['amount'] ?? 0);
        $currency = trim($_POST['currency'] ?? 'USD');
        if ($amount <= 0) { flashMessage('danger', 'Amount must be positive.'); }
        else {
            $pdo->prepare("INSERT INTO payment_requests (user_id, amount, currency) VALUES (?,?,?)")
                ->execute([$userId, $amount, $currency]);
            flashMessage('success', 'Payment request submitted.');
        }
    }

    if (in_array($role, ['admin','manager'])) {
        if ($action === 'approve' && $id) {
            $pdo->prepare("UPDATE payment_requests SET status='approved' WHERE id=?")->execute([$id]);
            // Notify requester
            $req = $pdo->prepare("SELECT * FROM payment_requests WHERE id=?");
            $req->execute([$id]);
            $r = $req->fetch();
            if ($r) addNotification((int)$r['user_id'], "Your payment request #{$id} for {$r['currency']} {$r['amount']} has been approved.");
            flashMessage('success', 'Request approved.');
        }
        if ($action === 'reject' && $id) {
            $pdo->prepare("UPDATE payment_requests SET status='rejected' WHERE id=?")->execute([$id]);
            $req = $pdo->prepare("SELECT * FROM payment_requests WHERE id=?");
            $req->execute([$id]);
            $r = $req->fetch();
            if ($r) addNotification((int)$r['user_id'], "Your payment request #{$id} for {$r['currency']} {$r['amount']} has been rejected.");
            flashMessage('warning', 'Request rejected.');
        }
    }

    if ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM payment_requests WHERE id=? AND user_id=? AND status='pending'")->execute([$id, $userId]);
        flashMessage('success', 'Request cancelled.');
    }
    redirect(APP_URL . '/payment_requests.php');
}

// Scope
if (in_array($role, ['admin','manager'])) {
    $stmt = $pdo->query("SELECT pr.*, u.username FROM payment_requests pr JOIN users u ON pr.user_id=u.id ORDER BY pr.created_at DESC");
} else {
    $stmt = $pdo->prepare("SELECT pr.*, u.username FROM payment_requests pr JOIN users u ON pr.user_id=u.id WHERE pr.user_id=? ORDER BY pr.created_at DESC");
    $stmt->execute([$userId]);
}
$requests = $stmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h2><i class="ri-bank-card-line me-2"></i>Payment Requests</h2>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Payment Requests</li>
    </ol></nav>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#submitPayModal">
    <i class="ri-add-line me-1"></i>New Request
  </button>
</div>

<!-- Stats -->
<?php
$pending  = count(array_filter($requests, fn($r) => $r['status']==='pending'));
$approved = array_sum(array_map(fn($r) => $r['status']==='approved' ? (float)$r['amount'] : 0, $requests));
$rejected = count(array_filter($requests, fn($r) => $r['status']==='rejected'));
?>
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="stat-card bg-stat-4">
      <div><div class="stat-val"><?= $pending ?></div><div class="stat-label">Pending</div></div>
      <div class="stat-icon"><i class="ri-time-line"></i></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card bg-stat-3">
      <div><div class="stat-val">$<?= number_format($approved, 2) ?></div><div class="stat-label">Approved Total</div></div>
      <div class="stat-icon"><i class="ri-check-double-line"></i></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card bg-stat-6">
      <div><div class="stat-val"><?= $rejected ?></div><div class="stat-label">Rejected</div></div>
      <div class="stat-icon"><i class="ri-close-circle-line"></i></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="payTable">
        <thead>
          <tr>
            <th>#</th>
            <?php if (in_array($role, ['admin','manager'])): ?><th>User</th><?php endif; ?>
            <th>Amount</th>
            <th>Currency</th>
            <th>Status</th>
            <th>Created At</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($requests)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No payment requests found.</td></tr>
          <?php else: foreach ($requests as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <?php if (in_array($role, ['admin','manager'])): ?>
            <td><?= h($r['username']) ?></td>
            <?php endif; ?>
            <td class="fw-bold">$<?= number_format((float)$r['amount'], 2) ?></td>
            <td><?= h($r['currency']) ?></td>
            <td><?= match($r['status']) {
                'pending'  => '<span class="badge bg-warning text-dark">Pending</span>',
                'approved' => '<span class="badge bg-success">Approved</span>',
                'rejected' => '<span class="badge bg-danger">Rejected</span>',
                default    => h($r['status'])
            } ?></td>
            <td><small class="text-muted"><?= h($r['created_at']) ?></small></td>
            <td>
              <?php if ($r['status'] === 'pending'): ?>
                <?php if (in_array($role, ['admin','manager'])): ?>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button type="submit" class="btn btn-xs btn-success">Approve</button>
                </form>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="reject">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button type="submit" class="btn btn-xs btn-outline-danger">Reject</button>
                </form>
                <?php elseif ($r['user_id'] == $userId): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Cancel this request?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button type="submit" class="btn btn-xs btn-outline-secondary">Cancel</button>
                </form>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-muted">–</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Submit Request Modal -->
<div class="modal fade" id="submitPayModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <input type="hidden" name="action" value="submit_request">
        <div class="modal-header"><h5 class="modal-title">New Payment Request</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
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
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="ri-send-plane-line me-1"></i>Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraJs = <<<'JS'
<script>
$(document).ready(function() {
    $('#payTable').DataTable({ pageLength: 25, order: [[0,'desc']] });
});
</script>
JS;
include __DIR__ . '/includes/footer.php';
?>
