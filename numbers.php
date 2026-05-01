<?php
require_once __DIR__ . '/functions.php';
requireLogin();
requireRole('manager');
$pageTitle = 'Manage Numbers';
$user = getCurrentUser();
$countries = allCountries();
include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h2><i class="ri-sim-card-line me-2"></i>Manage Numbers</h2>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Numbers</li>
    </ol></nav>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#bulkModal">
      <i class="ri-upload-line me-1"></i>Bulk Import
    </button>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
      <i class="ri-add-line me-1"></i>Add Number
    </button>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="numbersTable" width="100%">
        <thead>
          <tr>
            <th>Number</th>
            <th>Country</th>
            <th>Service</th>
            <th>Rate (USD)</th>
            <th>Status</th>
            <th>Assigned To</th>
            <th>Created By</th>
            <th>Created At</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Number Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="ri-add-circle-line me-1"></i>Add Number</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Phone Number *</label>
          <input type="text" class="form-control" id="addNumber" placeholder="+959661902830">
        </div>
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label">Country</label>
            <select class="form-select select2" id="addCountry">
              <option value="">Select country</option>
              <?php foreach ($countries as $code => $name): ?>
                <option value="<?= h($code) ?>"><?= h($name) ?> (<?= h($code) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Service</label>
            <input type="text" class="form-control" id="addService" placeholder="viber, telegram…">
          </div>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-6">
            <label class="form-label">Rate (USD)</label>
            <input type="number" class="form-control" id="addRate" step="0.000001" min="0" placeholder="0.005500">
          </div>
          <div class="col-6">
            <label class="form-label">Status</label>
            <select class="form-select" id="addStatus">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitAdd()">Add Number</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Number Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="ri-edit-line me-1"></i>Edit Number</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="editId">
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label">Country</label>
            <select class="form-select select2" id="editCountry">
              <option value="">Select country</option>
              <?php foreach ($countries as $code => $name): ?>
                <option value="<?= h($code) ?>"><?= h($name) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Service</label>
            <input type="text" class="form-control" id="editService">
          </div>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-6">
            <label class="form-label">Rate (USD)</label>
            <input type="number" class="form-control" id="editRate" step="0.000001" min="0">
          </div>
          <div class="col-6">
            <label class="form-label">Status</label>
            <select class="form-select" id="editStatus">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitEdit()">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="ri-user-add-line me-1"></i>Assign Number</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="assignId">
        <div class="mb-3">
          <label class="form-label">Assign to Reseller</label>
          <select class="form-select select2" id="assignUserId">
            <option value="">Select user…</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" onclick="submitAssign()">Assign</button>
      </div>
    </div>
  </div>
</div>

<!-- Bulk Import Modal -->
<div class="modal fade" id="bulkModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="ri-upload-line me-1"></i>Bulk Import Numbers</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Numbers (one per line)</label>
          <textarea class="form-control" id="bulkNumbers" rows="8" placeholder="+959661902830&#10;+959661902831&#10;+959661902832"></textarea>
        </div>
        <div class="row g-3">
          <div class="col-4">
            <label class="form-label">Default Country</label>
            <select class="form-select select2" id="bulkCountry">
              <option value="">Select…</option>
              <?php foreach ($countries as $code => $name): ?>
                <option value="<?= h($code) ?>"><?= h($name) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-4">
            <label class="form-label">Default Service</label>
            <input type="text" class="form-control" id="bulkService" placeholder="viber">
          </div>
          <div class="col-4">
            <label class="form-label">Default Rate</label>
            <input type="number" class="form-control" id="bulkRate" step="0.000001" min="0" placeholder="0.005500">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitBulk()">Import</button>
      </div>
    </div>
  </div>
</div>

<?php
$appUrl = APP_URL;
$extraJs = <<<JS
<script>
var APP_URL = "{$appUrl}";
var numbersTable;

$(document).ready(function() {
    if ($.fn.select2) {
        $('#addCountry,#editCountry,#bulkCountry').select2({ theme:'bootstrap-5', width:'100%', dropdownParent: \$('#addModal,#editModal,#bulkModal') });
        $('#assignUserId').select2({ theme:'bootstrap-5', width:'100%', dropdownParent: \$('#assignModal') });
    }

    numbersTable = $('#numbersTable').DataTable({
        processing: true, serverSide: true,
        ajax: { url: APP_URL+'/ajax/dt_numbers.php', type:'POST' },
        columns: [null,null,null,null,null,null,null,null,null].map(()=>({data:null,defaultContent:''})),
        pageLength: 25, order:[[7,'desc']],
        dom: "<'row'<'col-sm-6'l><'col-sm-6'f>><'row'<'col-12 mb-2'B>><'row'<'col-12'tr>><'row'<'col-sm-5'i><'col-sm-7'p>>",
        buttons: [
            {extend:'copy',className:'btn btn-sm btn-outline-secondary'},
            {extend:'csv',className:'btn btn-sm btn-outline-secondary'},
            {extend:'excel',className:'btn btn-sm btn-outline-secondary'},
        ],
    });
});

function submitAdd() {
    $.post(APP_URL+'/ajax/aj_numbers.php', {
        action:'add', number:$('#addNumber').val(), country:$('#addCountry').val(),
        service:$('#addService').val(), rate:$('#addRate').val(), status:$('#addStatus').val()
    }, function(r) {
        if (r.status==='success') { bootstrap.Modal.getInstance(document.getElementById('addModal')).hide(); numbersTable.ajax.reload(); showToast(r.message,'success'); }
        else showToast(r.message,'danger');
    });
}

function editNumber(id) {
    $.get(APP_URL+'/ajax/aj_numbers.php?action=get&id='+id, function(r) {
        if (r.status!=='success') return showToast(r.message,'danger');
        var d = r.data;
        $('#editId').val(d.id);
        $('#editCountry').val(d.country).trigger('change');
        $('#editService').val(d.service);
        $('#editRate').val(d.rate);
        $('#editStatus').val(d.status);
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
}

function submitEdit() {
    $.post(APP_URL+'/ajax/aj_numbers.php', {
        action:'edit', id:$('#editId').val(), country:$('#editCountry').val(),
        service:$('#editService').val(), rate:$('#editRate').val(), status:$('#editStatus').val()
    }, function(r) {
        if (r.status==='success') { bootstrap.Modal.getInstance(document.getElementById('editModal')).hide(); numbersTable.ajax.reload(); showToast(r.message,'success'); }
        else showToast(r.message,'danger');
    });
}

function assignNumber(id) {
    $('#assignId').val(id);
    // Load resellers
    $.get(APP_URL+'/ajax/aj_numbers.php?action=resellers_list', function(r) {
        var sel = $('#assignUserId').empty().append('<option value="">Select user…</option>');
        (r.data||[]).forEach(function(u) {
            sel.append('<option value="'+u.id+'">'+u.username+' ('+u.role+')</option>');
        });
        if ($.fn.select2) sel.trigger('change');
    });
    new bootstrap.Modal(document.getElementById('assignModal')).show();
}

function submitAssign() {
    $.post(APP_URL+'/ajax/aj_numbers.php', {
        action:'assign', id:$('#assignId').val(), assigned_to:$('#assignUserId').val()
    }, function(r) {
        if (r.status==='success') { bootstrap.Modal.getInstance(document.getElementById('assignModal')).hide(); numbersTable.ajax.reload(); showToast(r.message,'success'); }
        else showToast(r.message,'danger');
    });
}

function unassignNumber(id) {
    if (!confirm('Unassign this number?')) return;
    $.post(APP_URL+'/ajax/aj_numbers.php', {action:'unassign', id:id}, function(r) {
        numbersTable.ajax.reload();
        showToast(r.message, r.status==='success'?'success':'danger');
    });
}

function deleteNumber(id, assignedTo) {
    if (!confirm('Delete this number? (soft delete, history kept)')) return;
    $.post(APP_URL+'/ajax/aj_numbers.php', {action:'delete', id:id}, function(r) {
        numbersTable.ajax.reload();
        showToast(r.message, r.status==='success'?'success':'danger');
    });
}

function submitBulk() {
    $.post(APP_URL+'/ajax/aj_numbers.php', {
        action:'bulk_add', numbers:$('#bulkNumbers').val(),
        country:$('#bulkCountry').val(), service:$('#bulkService').val(), rate:$('#bulkRate').val()
    }, function(r) {
        if (r.status==='success') { bootstrap.Modal.getInstance(document.getElementById('bulkModal')).hide(); numbersTable.ajax.reload(); showToast(r.message,'success'); }
        else showToast(r.message,'danger');
    });
}
</script>
JS;
include __DIR__ . '/includes/footer.php';
?>
