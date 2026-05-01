<?php
require_once __DIR__ . '/functions.php';
requireLogin();
$user   = getCurrentUser();
$role   = $user['role'];
$userId = (int)$user['id'];
if ($role === 'sub_reseller') redirect(APP_URL . '/dashboard.php');
$pageTitle = ($role === 'reseller') ? 'My Clients' : 'Manage Users';
include __DIR__ . '/includes/header.php';

$allowedRoles = match($role) {
    'admin'    => ['manager','reseller','sub_reseller'],
    'manager'  => ['reseller','sub_reseller'],
    'reseller' => ['sub_reseller'],
    default    => [],
};
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h2><i class="ri-team-line me-2"></i><?= h($pageTitle) ?></h2>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
      <li class="breadcrumb-item active"><?= h($pageTitle) ?></li>
    </ol></nav>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
    <i class="ri-user-add-line me-1"></i>Create User
  </button>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="usersTable" width="100%">
        <thead>
          <tr>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Parent</th>
            <th>Created At</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="ri-user-add-line me-1"></i>Create User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Username *</label>
          <input type="text" class="form-control" id="newUsername" placeholder="johndoe">
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" id="newEmail" placeholder="john@example.com">
        </div>
        <div class="mb-3">
          <label class="form-label">Password *</label>
          <input type="password" class="form-control" id="newPassword" placeholder="Min 6 characters">
        </div>
        <div class="mb-3">
          <label class="form-label">Role *</label>
          <select class="form-select" id="newRole">
            <?php foreach ($allowedRoles as $r): ?>
              <option value="<?= h($r) ?>"><?= ucfirst(str_replace('_',' ',$r)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitAddUser()">Create User</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="ri-edit-line me-1"></i>Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="editUserId">
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" id="editEmail">
        </div>
        <div class="mb-3">
          <label class="form-label">New Password <small class="text-muted">(leave blank to keep current)</small></label>
          <input type="password" class="form-control" id="editPassword">
        </div>
        <div class="mb-3">
          <label class="form-label">Status</label>
          <select class="form-select" id="editUserStatus">
            <option value="active">Active</option>
            <option value="blocked">Blocked</option>
            <option value="pending">Pending</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitEditUser()">Save</button>
      </div>
    </div>
  </div>
</div>

<?php
$appUrl = APP_URL;
$extraJs = <<<JS
<script>
var APP_URL = "{$appUrl}";
var usersTable;

$(document).ready(function() {
    usersTable = $('#usersTable').DataTable({
        processing: true, serverSide: true,
        ajax: { url: APP_URL+'/ajax/dt_users.php', type:'POST' },
        columns: [null,null,null,null,null,null,null].map(()=>({data:null,defaultContent:''})),
        pageLength: 25, order:[[5,'desc']],
        dom: "<'row'<'col-sm-6'l><'col-sm-6'f>><'row'<'col-12 mb-2'B>><'row'<'col-12'tr>><'row'<'col-sm-5'i><'col-sm-7'p>>",
        buttons: [
            {extend:'copy',className:'btn btn-sm btn-outline-secondary'},
            {extend:'csv',className:'btn btn-sm btn-outline-secondary'},
            {extend:'excel',className:'btn btn-sm btn-outline-secondary'},
        ],
    });
});

function submitAddUser() {
    $.post(APP_URL+'/ajax/aj_users.php', {
        action:'add', username:$('#newUsername').val(), email:$('#newEmail').val(),
        password:$('#newPassword').val(), role:$('#newRole').val()
    }, function(r) {
        if (r.status==='success') {
            bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
            usersTable.ajax.reload();
            showToast(r.message,'success');
            $('#newUsername,#newEmail,#newPassword').val('');
        } else showToast(r.message,'danger');
    });
}

function editUser(id) {
    $.get(APP_URL+'/ajax/aj_users.php?action=get&id='+id, function(r) {
        if (r.status!=='success') return showToast(r.message,'danger');
        var d = r.data;
        $('#editUserId').val(d.id);
        $('#editEmail').val(d.email||'');
        $('#editPassword').val('');
        $('#editUserStatus').val(d.status);
        new bootstrap.Modal(document.getElementById('editUserModal')).show();
    });
}

function submitEditUser() {
    $.post(APP_URL+'/ajax/aj_users.php', {
        action:'edit', id:$('#editUserId').val(), email:$('#editEmail').val(),
        password:$('#editPassword').val(), status:$('#editUserStatus').val()
    }, function(r) {
        if (r.status==='success') {
            bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
            usersTable.ajax.reload();
            showToast(r.message,'success');
        } else showToast(r.message,'danger');
    });
}

function toggleBlock(id, action) {
    if (!confirm('Are you sure?')) return;
    $.post(APP_URL+'/ajax/aj_users.php', {action:'toggle_block', id:id, block_action:action}, function(r) {
        usersTable.ajax.reload();
        showToast(r.message, r.status==='success'?'success':'danger');
    });
}

function deleteUser(id, name) {
    if (!confirm('Delete user "'+name+'"? This cannot be undone.')) return;
    $.post(APP_URL+'/ajax/aj_users.php', {action:'delete', id:id}, function(r) {
        usersTable.ajax.reload();
        showToast(r.message, r.status==='success'?'success':'danger');
    });
}
</script>
JS;
include __DIR__ . '/includes/footer.php';
?>
