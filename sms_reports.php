<?php
/**
 * Sigma SMS A2P — SMS Reports
 */
require_once __DIR__ . '/functions.php';
requireLogin();
$pageTitle = 'SMS Reports';
$user      = getCurrentUser();
$countries = allCountries();
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h2><i class="ri-message-2-line me-2"></i>SMS Reports</h2>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
      <li class="breadcrumb-item active">SMS Reports</li>
    </ol>
  </nav>
</div>

<!-- Filters Card -->
<div class="card mb-4">
  <div class="card-header"><i class="ri-filter-line me-1"></i>Filters &amp; Grouping</div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Date From</label>
        <input type="text" class="form-control flatpickr-date" id="filterDateFrom" placeholder="YYYY-MM-DD">
      </div>
      <div class="col-md-3">
        <label class="form-label">Date To</label>
        <input type="text" class="form-control flatpickr-date" id="filterDateTo" placeholder="YYYY-MM-DD">
      </div>
      <div class="col-md-2">
        <label class="form-label">Service</label>
        <input type="text" class="form-control" id="filterService" placeholder="e.g. viber">
      </div>
      <div class="col-md-2">
        <label class="form-label">Country</label>
        <select class="form-select select2" id="filterCountry">
          <option value="">All Countries</option>
          <?php foreach ($countries as $code => $name): ?>
            <option value="<?= h($code) ?>"><?= h($name) ?> (<?= h($code) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Number</label>
        <input type="text" class="form-control" id="filterNumber" placeholder="+959…">
      </div>
      <div class="col-md-4">
        <label class="form-label">Group By</label>
        <select class="form-select select2" id="filterGroupBy" multiple>
          <option value="DATE(sr.received_at)">Date</option>
          <option value="sr.service">Service</option>
          <option value="sr.country">Country</option>
          <option value="sr.number">Number</option>
        </select>
      </div>
      <div class="col-md-8 d-flex align-items-end gap-2">
        <button class="btn btn-primary" onclick="applyFilters()">
          <i class="ri-search-line me-1"></i>Apply Filters
        </button>
        <button class="btn btn-outline-secondary" onclick="resetFilters()">
          <i class="ri-refresh-line me-1"></i>Reset
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Data Table Card -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="ri-table-line me-1"></i>Results</span>
    <div class="d-flex gap-3 text-muted" style="font-size:.85rem;">
      <span>Total SMS: <strong id="footerSms" class="text-dark">–</strong></span>
      <span>Total Profit: <strong id="footerProfit" class="text-success">–</strong></span>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="smsTable" width="100%">
        <thead>
          <tr id="smsTableHead">
            <th>Date/Time</th>
            <th>Number</th>
            <th>Service</th>
            <th>Country</th>
            <th>OTP</th>
            <th>Message</th>
            <th>Rate</th>
            <th>Profit</th>
            <th>Assigned To</th>
          </tr>
        </thead>
        <tfoot>
          <tr>
            <th colspan="6" class="text-end text-muted">Totals:</th>
            <th></th>
            <th id="footTotalProfit" class="text-success">–</th>
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
var smsTable;

$(document).ready(function() {
    if ($.fn.select2) {
        $('#filterCountry').select2({ theme: 'bootstrap-5', width: '100%' });
        $('#filterGroupBy').select2({ theme: 'bootstrap-5', width: '100%' });
    }
    if (typeof flatpickr !== 'undefined') {
        flatpickr('.flatpickr-date', { dateFormat: 'Y-m-d' });
    }
    initTable();
});

function initTable() {
    if (smsTable) {
        smsTable.destroy();
        $('#smsTableHead').html(getHeaders());
    }
    var groupBy = $('#filterGroupBy').val() || [];

    smsTable = $('#smsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: APP_URL + '/ajax/dt_sms_reports.php',
            type: 'POST',
            data: function(d) {
                d.date_from = $('#filterDateFrom').val();
                d.date_to   = $('#filterDateTo').val();
                d.service   = $('#filterService').val();
                d.country   = $('#filterCountry').val();
                d.number    = $('#filterNumber').val();
                d.group_by  = groupBy;
                return d;
            },
            dataSrc: function(json) {
                if (json.footer) {
                    $('#footerSms').text(json.footer.total_sms.toLocaleString());
                    $('#footerProfit').text(json.footer.total_profit);
                    $('#footTotalProfit').text(json.footer.total_profit);
                }
                return json.data;
            }
        },
        columns: getColumns(groupBy),
        pageLength: 25,
        order: [[0, 'desc']],
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

function getHeaders() {
    var groupBy = $('#filterGroupBy').val() || [];
    if (groupBy.length) {
        var map = {
            'DATE(sr.received_at)': 'Date',
            'sr.service':  'Service',
            'sr.country':  'Country',
            'sr.number':   'Number'
        };
        var h = groupBy.map(function(g) { return '<th>' + (map[g] || g) + '</th>'; }).join('');
        return h + '<th>Total SMS</th><th>Total Profit</th><th>Last Received</th>';
    }
    return '<th>Date/Time</th><th>Number</th><th>Service</th><th>Country</th><th>OTP</th><th>Message</th><th>Rate</th><th>Profit</th><th>Assigned To</th>';
}

function getColumns(groupBy) {
    var count = (groupBy && groupBy.length) ? groupBy.length + 3 : 9;
    var cols = [];
    for (var i = 0; i < count; i++) {
        cols.push({ data: null, defaultContent: '–' });
    }
    return cols;
}

function applyFilters() { initTable(); }

function resetFilters() {
    $('#filterDateFrom, #filterDateTo, #filterService, #filterNumber').val('');
    if ($.fn.select2) {
        $('#filterCountry, #filterGroupBy').val(null).trigger('change');
    }
    initTable();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
