<?php
require_once __DIR__ . '/functions.php';
requireLogin();
$user   = getCurrentUser();
$role   = $user['role'];
$userId = (int)$user['id'];
if (!in_array($role, ['reseller','sub_reseller'])) {
    redirect(APP_URL . '/numbers.php');
}
$pageTitle = 'My Numbers';
$countries = allCountries();
$pdo = getDB();
include __DIR__ . '/includes/header.php';

// Get sub-resellers for assign modal
$subResellers = [];
if ($role === 'reseller') {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE parent_id = ? AND role='sub_reseller' AND status='active'");
    $stmt->execute([$userId]);
    $subResellers = $stmt->fetchAll();
}

// Build numbers query
if ($role === 'sub_reseller') {
    $stmt = $pdo->prepare("SELECT n.*, u.username as assignee FROM numbers n LEFT JOIN users u ON n.assigned_to=u.id WHERE n.assigned_to = ? AND n.status='active' ORDER BY n.created_at DESC");
    $stmt->execute([$userId]);
} else {
    $userIds = getDescendantUserIds($userId);
    $ph = implode(',', array_fill(0,count($userIds),'?'));
    $stmt = $pdo->prepare("SELECT n.*, u.username as assignee FROM numbers n LEFT JOIN users u ON n.assigned_to=u.id WHERE n.assigned_to IN ($ph) AND n.status='active' ORDER BY n.created_at DESC");
    $stmt->execute($userIds);
}
$numbers = $stmt->fetchAll();
?>

<div class="page-header">
  <h2><i class="ri-sim-card-line me-2"></i>My Numbers</h2>
  <nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">My Numbers</li>
  </ol></nav>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><?= count($numbers) ?> number(s) assigned</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="myNumTable">
        <thead>
          <tr>
            <th>Number</th>
            <th>Country</th>
            <th>Service</th>
            <th>Rate (USD)</th>
            <th>Assigned To</th>
            <th>Assigned At</th>
            <?php if ($role === 'reseller'): ?>
            <th>Actions</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($numbers)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No numbers assigned to you yet.</td></tr>
          <?php else: foreach ($numbers as $n): ?>
          <tr>
            <td><code><?= h($n['number']) ?></code></td>
            <td><?= h($n['country'] ?? '–') ?></td>
            <td><span class="badge bg-info text-dark"><?= h($n['service'] ?? '–') ?></span></td>
            <td>$<?= number_format((float)$n['rate'], 6) ?></td>
            <td><?= h($n['assignee'] ?? '–') ?></td>
            <td><small class="text-muted"><?= h($n['assigned_at'] ?? '–') ?></small></td>
            <?php if ($role === 'reseller'): ?>
            <td>
              <div class="d-flex gap-1">
                <?php if ($n['assigned_to'] == $userId): ?>
                  <!-- Assigned to self — can reassign to sub-reseller -->
                  <button class="btn btn-xs btn-outline-success" onclick="openAssignSub(<?= (int)$n['id'] ?>)">
                    <i class="ri-user-add-line"></i> Assign Sub
                  </button>
                <?php else: ?>
                  <!-- Assigned to sub-reseller — can unassign -->
                  <button class="btn btn-xs btn-outline-warning" onclick="unassignSub(<?= (int)$n['id'] ?>)">
                    <i class="ri-link-unlink"></i> Unassign
                  </button>
                <?php endif; ?>
              </div>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($role === 'reseller'): ?>
<!-- Assign to Sub-Reseller Modal -->
<div class="modal fade" id="assignSubModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Assign to Sub-Reseller</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="assignSubNumId">
        <div class="mb-3">
          <label class="form-label">Select Sub-Reseller (Client)</label>
          <select class="form-select select2" id="assignSubUserId">
            <option value="">Choose…</option>
            <?php foreach ($subResellers as $sr): ?>
              <option value="<?= (int)$sr['id'] ?>"><?= h($sr['username']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($subResellers)): ?>
            <div class="form-text text-warning">You have no sub-resellers. <a href="<?= APP_URL ?>/users.php">Create one first</a>.</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" onclick="submitAssignSub()">Assign</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
$appUrl = APP_URL;
$extraJs = <<<JS
<script>
var APP_URL = "{$appUrl}";
$(document).ready(function() {
    $('#myNumTable').DataTable({ pageLength:25, order:[[5,'desc']] });
    if ($.fn.select2) {
        $('#assignSubUserId').select2({ theme:'bootstrap-5', width:'100%', dropdownParent:\$('#assignSubModal') });
    }
});

function openAssignSub(numId) {
    $('#assignSubNumId').val(numId);
    new bootstrap.Modal(document.getElementById('assignSubModal')).show();
}

function submitAssignSub() {
    $.post(APP_URL+'/ajax/aj_numbers.php', {
        action:'assign', id:$('#assignSubNumId').val(), assigned_to:$('#assignSubUserId').val()
    }, function(r) {
        showToast(r.message, r.status==='success'?'success':'danger');
        if (r.status==='success') setTimeout(()=>location.reload(), 1000);
    });
}

function unassignSub(numId) {
    if (!confirm('Unassign this number from sub-reseller?')) return;
    $.post(APP_URL+'/ajax/aj_numbers.php', {action:'unassign', id:numId}, function(r) {
        showToast(r.message, r.status==='success'?'success':'danger');
        if (r.status==='success') setTimeout(()=>location.reload(), 1000);
    });
}
</script>
JS;
include __DIR__ . '/includes/footer.php';
?>
