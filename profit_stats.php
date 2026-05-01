<?php
/**
 * Sigma SMS A2P — Profit Stats
 */
require_once __DIR__ . '/functions.php';
requireLogin();
$pageTitle = 'Profit Stats';
$user      = getCurrentUser();
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h2><i class="ri-money-dollar-circle-line me-2"></i>Profit Stats</h2>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Profit Stats</li>
    </ol>
  </nav>
</div>

<!-- Filters -->
<div class="card mb-4">
  <div class="card-header"><i class="ri-filter-line me-1"></i>Filters</div>
  <div class="card-body">
    <div class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label">Date From</label>
        <input type="text" class="form-control flatpickr-date" id="pDateFrom" placeholder="YYYY-MM-DD">
      </div>
      <div class="col-md-3">
        <label class="form-label">Date To</label>
        <input type="text" class="form-control flatpickr-date" id="pDateTo" placeholder="YYYY-MM-DD">
      </div>
      <div class="col-md-2">
        <label class="form-label">Service</label>
        <input type="text" class="form-control" id="pService" placeholder="e.g. viber">
      </div>
      <div class="col-md-2">
        <label class="form-label">Group By</label>
        <select class="form-select" id="pGroupBy">
          <option value="day">Day</option>
          <option value="service">Service</option>
          <option value="country">Country</option>
          <option value="number">Number</option>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary" onclick="applyFilters()">
          <i class="ri-search-line me-1"></i>Apply
        </button>
        <button class="btn btn-outline-secondary" onclick="resetFilters()">
          <i class="ri-refresh-line"></i>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Totals Banner -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="stat-card bg-stat-3">
      <div>
        <div class="stat-val" id="totProfit">–</div>
        <div class="stat-label">Total Profit (filtered)</div>
      </div>
      <div class="stat-icon"><i class="ri-money-dollar-circle-line"></i></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card bg-stat-2">
      <div>
        <div class="stat-val" id="totSms">–</div>
        <div class="stat-label">Total SMS (filtered)</div>
      </div>
      <div class="stat-icon"><i class="ri-message-2-line"></i></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="ri-bar-chart-line me-1"></i>Profit Breakdown</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="profitTable" width="100%">
        <thead>
          <tr>
            <th>Group</th>
            <th>Total SMS</th>
            <th>Total Profit (USD)</th>
            <th>First Record</th>
            <th>Last Record</th>
          </tr>
        </thead>
        <tfoot>
          <tr>
            <th>Totals</th>
            <th id="ftSms">–</th>
            <th id="ftProfit" class="text-success">–</th>
            <th></th>
            <th></th>
          </tr>
        </tfoot>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<script>
var APP_URL = "<?= h(APP_URL) ?>";
var profitTable;

$(document).ready(function() {
    if (typeof flatpickr !== 'undefined') {
        flatpickr('.flatpickr-date', { dateFormat: 'Y-m-d' });
    }
    initTable();
});

function initTable() {
    if (profitTable) profitTable.destroy();

    profitTable = $('#profitTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: APP_URL + '/ajax/dt_profit_reports.php',
            type: 'POST',
            data: function(d) {
                d.date_from = $('#pDateFrom').val();
                d.date_to   = $('#pDateTo').val();
                d.service   = $('#pService').val();
                d.group_by  = $('#pGroupBy').val();
                return d;
            },
            dataSrc: function(json) {
                if (json.footer) {
                    $('#totProfit').text(json.footer.total_profit);
                    $('#totSms').text(json.footer.total_sms.toLocaleString());
                    $('#ftSms').text(json.footer.total_sms.toLocaleString());
                    $('#ftProfit').text(json.footer.total_profit);
                }
                return json.data;
            }
        },
        columns: [null, null, null, null, null].map(function() {
            return { data: null, defaultContent: '–' };
        }),
        pageLength: 25,
        order: [[3, 'desc']],
        dom: "<'row'<'col-sm-6'l><'col-sm-6'f>><'row'<'col-12 mb-2'B>><'row'<'col-12'tr>><'row'<'col-sm-5'i><'col-sm-7'p>>",
        buttons: [
            { extend: 'copy',  className: 'btn btn-sm btn-outline-secondary' },
            { extend: 'csv',   className: 'btn btn-sm btn-outline-secondary' },
            { extend: 'excel', className: 'btn btn-sm btn-outline-secondary' },
            { extend: 'pdf',   className: 'btn btn-sm btn-outline-secondary' },
            { extend: 'print', className: 'btn btn-sm btn-outline-secondary' },
        ],
        language: {
            processing: '<span class="spinner-border spinner-border-sm me-1"></span> Loading…',
            search: '',
            searchPlaceholder: 'Search…',
            lengthMenu: '_MENU_ per page',
        },
    });
}

function applyFilters() { profitTable.ajax.reload(); }

function resetFilters() {
    $('#pDateFrom, #pDateTo, #pService').val('');
    $('#pGroupBy').val('day');
    profitTable.ajax.reload();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
