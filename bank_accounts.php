<?php
require_once __DIR__ . '/functions.php';
requireLogin();
$pageTitle = 'Bank Accounts';
$user   = getCurrentUser();
$userId = (int)$user['id'];
$pdo    = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action         = $_POST['action'] ?? '';
    $bankName       = trim($_POST['bank_name'] ?? '');
    $accountNumber  = trim($_POST['account_number'] ?? '');
    $routingNumber  = trim($_POST['routing_number'] ?? '');
    $id             = (int)($_POST['id'] ?? 0);

    if ($action === 'add') {
        if (empty($bankName) || empty($accountNumber)) {
            flashMessage('danger', 'Bank name and account number are required.');
        } else {
            $pdo->prepare("INSERT INTO bank_accounts (user_id, bank_name, account_number, routing_number) VALUES (?,?,?,?)")
                ->execute([$userId, $bankName, $accountNumber, $routingNumber]);
            flashMessage('success', 'Bank account added.');
        }
    }
    if ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM bank_accounts WHERE id=? AND user_id=?")->execute([$id, $userId]);
        flashMessage('success', 'Bank account removed.');
    }
    redirect(APP_URL . '/bank_accounts.php');
}

$stmt = $pdo->prepare("SELECT * FROM bank_accounts WHERE user_id=? ORDER BY id DESC");
$stmt->execute([$userId]);
$accounts = $stmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h2><i class="ri-bank-line me-2"></i>Bank Accounts</h2>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Bank Accounts</li>
    </ol></nav>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBankModal">
    <i class="ri-add-line me-1"></i>Add Account
  </button>
</div>

<?php if (empty($accounts)): ?>
  <div class="card"><div class="card-body text-center text-muted py-5">
    <i class="ri-bank-line" style="font-size:3rem;opacity:.3;"></i>
    <p class="mt-2 mb-0">No bank accounts added yet.</p>
  </div></div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($accounts as $acc): ?>
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h6 class="fw-bold mb-1"><i class="ri-bank-line me-1 text-primary"></i><?= h($acc['bank_name']) ?></h6>
              <div class="mb-1" style="font-size:.875rem;">
                <span class="text-muted">Account:</span>
                <code>••••<?= h(substr($acc['account_number'], -4)) ?></code>
              </div>
              <?php if ($acc['routing_number']): ?>
              <div style="font-size:.875rem;">
                <span class="text-muted">Routing:</span>
                <code><?= h($acc['routing_number']) ?></code>
              </div>
              <?php endif; ?>
            </div>
            <form method="POST" onsubmit="return confirm('Remove this bank account?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$acc['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Add Modal -->
<div class="modal fade" id="addBankModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Add Bank Account</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Bank Name *</label>
            <input type="text" name="bank_name" class="form-control" required placeholder="e.g. Chase Bank">
          </div>
          <div class="mb-3">
            <label class="form-label">Account Number *</label>
            <input type="text" name="account_number" class="form-control" required placeholder="1234567890">
          </div>
          <div class="mb-3">
            <label class="form-label">Routing Number</label>
            <input type="text" name="routing_number" class="form-control" placeholder="021000021">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i>Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
