<?php
/**
 * Sigma SMS A2P — Dashboard
 */
require_once __DIR__ . '/functions.php';
requireLogin();
$pageTitle = 'Dashboard';
$user = getCurrentUser();
$role = $user['role'];
include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h2 class="animate-in"><i class="ri-dashboard-line me-2"></i>Dashboard</h2>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb"><li class="breadcrumb-item active">Dashboard</li></ol>
    </nav>
  </div>
  <div class="d-flex align-items-center gap-2">
    <select class="form-select form-select-sm" id="rangePreset" style="min-width: 170px;">
      <option value="24h">Last 24 Hours</option>
      <option value="7d" selected>Last 7 Days</option>
      <option value="30d">Last 30 Days</option>
      <option value="90d">Last 90 Days</option>
    </select>
    <?php if (in_array($role, ['admin', 'manager'])): ?>
    <button class="btn btn-primary btn-fetch" id="fetchBtn" onclick="triggerFetch()">
      <i class="ri-refresh-line me-1"></i> Fetch OTPs Now
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4" id="statsCards">
  <div class="col-6 col-xl-2 stat-animate" style="--delay:.05s">
    <div class="stat-card bg-stat-1">
      <div>
        <div class="stat-val" id="s-today-sms">–</div>
        <div class="stat-label">Today SMS</div>
      </div>
      <div class="stat-icon"><i class="ri-message-2-line"></i></div>
    </div>
  </div>
  <div class="col-6 col-xl-2 stat-animate" style="--delay:.1s">
    <div class="stat-card bg-stat-2">
      <div>
        <div class="stat-val" id="s-week-sms">–</div>
        <div class="stat-label">Week SMS</div>
      </div>
      <div class="stat-icon"><i class="ri-message-3-line"></i></div>
    </div>
  </div>
  <div class="col-6 col-xl-2 stat-animate" style="--delay:.15s">
    <div class="stat-card bg-stat-3">
      <div>
        <div class="stat-val" id="s-today-profit">–</div>
        <div class="stat-label">Today Profit</div>
      </div>
      <div class="stat-icon"><i class="ri-money-dollar-circle-line"></i></div>
    </div>
  </div>
  <div class="col-6 col-xl-2 stat-animate" style="--delay:.2s">
    <div class="stat-card bg-stat-4">
      <div>
        <div class="stat-val" id="s-week-profit">–</div>
        <div class="stat-label">Week Profit</div>
      </div>
      <div class="stat-icon"><i class="ri-funds-line"></i></div>
    </div>
  </div>
  <div class="col-6 col-xl-2 stat-animate" style="--delay:.25s">
    <div class="stat-card bg-stat-5">
      <div>
        <div class="stat-val" id="s-total-numbers">–</div>
        <div class="stat-label">Numbers</div>
      </div>
      <div class="stat-icon"><i class="ri-sim-card-line"></i></div>
    </div>
  </div>
  <div class="col-6 col-xl-2 stat-animate" style="--delay:.3s">
    <div class="stat-card bg-stat-6">
      <div>
        <div class="stat-val" id="s-total-users">–</div>
        <div class="stat-label"><?= ($role === 'reseller') ? 'Clients' : 'Users' ?></div>
      </div>
      <div class="stat-icon"><i class="ri-team-line"></i></div>
    </div>
  </div>
  <div class="col-6 col-xl-3 stat-animate" style="--delay:.35s">
    <div class="stat-card bg-stat-2">
      <div>
        <div class="stat-val" id="s-range-sms">–</div>
        <div class="stat-label">Selected Range SMS</div>
      </div>
      <div class="stat-icon"><i class="ri-calendar-event-line"></i></div>
    </div>
  </div>
  <div class="col-6 col-xl-3 stat-animate" style="--delay:.4s">
    <div class="stat-card bg-stat-3">
      <div>
        <div class="stat-val" id="s-range-profit">–</div>
        <div class="stat-label">Selected Range Profit</div>
      </div>
      <div class="stat-icon"><i class="ri-bar-chart-grouped-line"></i></div>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
  <div class="col-lg-7 chart-animate" style="--delay:.35s">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="ri-line-chart-line me-1 text-primary"></i>SMS Activity</span>
        <span class="badge bg-primary">Area Chart</span>
      </div>
      <div class="card-body">
        <div id="chartSms" style="min-height:260px;"></div>
      </div>
    </div>
  </div>
  <div class="col-lg-5 chart-animate" style="--delay:.4s">
    <div class="card h-100">
      <div class="card-header">
        <i class="ri-pie-chart-line me-1 text-primary"></i>Top 5 Services
      </div>
      <div class="card-body">
        <div id="chartServices" style="min-height:260px;"></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6 chart-animate" style="--delay:.43s">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="ri-global-line me-1 text-primary"></i>Top Countries</span>
        <span class="badge bg-secondary">Bar Chart</span>
      </div>
      <div class="card-body">
        <div id="chartCountries" style="min-height:240px;"></div>
      </div>
    </div>
  </div>
  <div class="col-lg-6 chart-animate" style="--delay:.45s">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="ri-flashlight-line me-1 text-primary"></i>Quick Actions</span>
      </div>
      <div class="card-body d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary btn-sm" href="<?= APP_URL ?>/sms_reports.php"><i class="ri-file-list-2-line me-1"></i>Open Reports</a>
        <a class="btn btn-outline-primary btn-sm" href="<?= APP_URL ?>/profit_stats.php"><i class="ri-money-dollar-circle-line me-1"></i>Profit Stats</a>
        <a class="btn btn-outline-primary btn-sm" href="<?= APP_URL ?>/notifications.php"><i class="ri-notification-3-line me-1"></i>Notifications</a>
        <button class="btn btn-outline-primary btn-sm" onclick="refreshDashboard()"><i class="ri-refresh-line me-1"></i>Refresh Dashboard</button>
      </div>
    </div>
  </div>
</div>

<!-- Recent OTPs -->
<div class="card chart-animate" style="--delay:.45s">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="ri-time-line me-1 text-primary"></i>Recent OTPs</span>
    <a href="<?= APP_URL ?>/sms_reports.php" class="btn btn-sm btn-outline-primary">
      <i class="ri-arrow-right-line me-1"></i>View All
    </a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="recentOtpsTable">
        <thead>
          <tr>
            <th>Received At</th>
            <th>Number</th>
            <th>Service</th>
            <th>Country</th>
            <th>OTP</th>
            <th>Message</th>
            <?php if (in_array($role, ['admin', 'manager', 'reseller'])): ?>
            <th>Profit</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody id="recentOtpsBody">
          <tr>
            <td colspan="7" class="text-center text-muted py-4">
              <span class="spinner-border spinner-border-sm me-2"></span>Loading…
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Announcements (for resellers/sub-resellers) -->
<?php if (in_array($role, ['reseller', 'sub_reseller'])): ?>
<div class="card mt-3 chart-animate" style="--delay:.5s">
  <div class="card-header"><i class="ri-megaphone-line me-1 text-primary"></i>Announcements</div>
  <div class="card-body">
    <?php
    $pdo  = getDB();
    $news = $pdo->query(
        "SELECT n.*, u.username FROM news_master n
         JOIN users u ON n.created_by = u.id
         ORDER BY n.created_at DESC LIMIT 5"
    )->fetchAll();
    if (empty($news)): ?>
      <p class="text-muted mb-0">No announcements yet.</p>
    <?php else: foreach ($news as $item): ?>
      <div class="mb-3 pb-3 border-bottom">
        <h6 class="mb-1 fw-semibold"><?= h($item['title']) ?></h6>
        <p class="mb-1 text-muted" style="font-size:.875rem;"><?= nl2br(h($item['content'])) ?></p>
        <small class="text-muted">
          <i class="ri-user-line me-1"></i><?= h($item['username']) ?>
          &mdash; <?= h($item['created_at']) ?>
        </small>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php endif; ?>

<?php
$showProfit = in_array($role, ['admin', 'manager', 'reseller']) ? 'true' : 'false';
$appUrl     = APP_URL;
$csrfToken  = csrfToken();
?>
<script>
var APP_URL    = "<?= h($appUrl) ?>";
var CSRF_TOKEN = "<?= h($csrfToken) ?>";
var SHOW_PROFIT = <?= $showProfit ?>;
var currentRange = '7d';
var smsChart = null;
var servicesChart = null;
var countriesChart = null;

function refreshDashboard() {
    loadStats();
    loadSmsChart();
    loadServicesChart();
    loadCountriesChart();
    loadRecentOtps();
}

function apiUrl(path) {
    return APP_URL + path + (path.indexOf('?') >= 0 ? '&' : '?') + 'range=' + encodeURIComponent(currentRange);
}

function loadStats() {
$.getJSON(apiUrl('/ajax/dashboard_stats.php'), function(d) {
    if (d.status !== 'success') return;
    var s = d.data;
    animateCount('s-today-sms',     s.today_sms);
    animateCount('s-week-sms',      s.week_sms);
    animateCount('s-total-numbers', s.total_numbers);
    animateCount('s-total-users',   s.total_users);
    animateCount('s-range-sms', s.range_sms || 0);
    $('#s-today-profit').text('$' + parseFloat(s.today_profit).toFixed(4));
    $('#s-week-profit').text('$'  + parseFloat(s.week_profit).toFixed(4));
    $('#s-range-profit').text('$' + parseFloat(s.range_profit || 0).toFixed(4));
});
}

// ── SMS Area Chart ───────────────────────────────────────────────────────────
function loadSmsChart() {
$.getJSON(apiUrl('/ajax/dashboard_charts.php?type=sms'), function(d) {
    if (!d.categories) return;
    if (smsChart) smsChart.destroy();
    smsChart = new ApexCharts(document.querySelector('#chartSms'), {
        chart: {
            type: 'area', height: 260,
            toolbar: { show: false },
            animations: { enabled: true, easing: 'easeinout', speed: 800 },
        },
        series: [{ name: 'SMS Received', data: d.data }],
        xaxis: { categories: d.categories, labels: { style: { fontSize: '11px', colors: '#6c757d' } } },
        yaxis: { labels: { style: { colors: '#6c757d' } } },
        colors: ['#4f46e5'],
        fill: {
            type: 'gradient',
            gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.02, stops: [0, 100] }
        },
        stroke: { curve: 'smooth', width: 2.5 },
        markers: { size: 5, colors: ['#4f46e5'], strokeColors: '#fff', strokeWidth: 2 },
        tooltip: { y: { formatter: function(v) { return v + ' SMS'; } } },
        grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
        dataLabels: { enabled: false },
    });
    smsChart.render();
});
}

// ── Services Donut Chart ─────────────────────────────────────────────────────
function loadServicesChart() {
$.getJSON(apiUrl('/ajax/dashboard_charts.php?type=services'), function(d) {
    if (!d.labels || !d.labels.length) {
        document.querySelector('#chartServices').innerHTML =
            '<div class="text-center text-muted py-5"><i class="ri-pie-chart-line" style="font-size:2.5rem;opacity:.3;"></i><p class="mt-2">No data yet</p></div>';
        return;
    }
    if (servicesChart) servicesChart.destroy();
    servicesChart = new ApexCharts(document.querySelector('#chartServices'), {
        chart: {
            type: 'donut', height: 260,
            animations: { enabled: true, easing: 'easeinout', speed: 800 },
        },
        series: d.data,
        labels: d.labels,
        colors: ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444'],
        legend: { position: 'bottom', fontSize: '12px' },
        dataLabels: { enabled: true, formatter: function(v) { return v.toFixed(1) + '%'; } },
        tooltip: { y: { formatter: function(v) { return v + ' SMS'; } } },
        plotOptions: { pie: { donut: { size: '65%' } } },
    });
    servicesChart.render();
});
}

function loadCountriesChart() {
$.getJSON(apiUrl('/ajax/dashboard_charts.php?type=countries'), function(d) {
    if (!d.labels || !d.labels.length) {
        document.querySelector('#chartCountries').innerHTML = '<div class="text-center text-muted py-5"><i class="ri-global-line" style="font-size:2.5rem;opacity:.3;"></i><p class="mt-2">No data yet</p></div>';
        return;
    }
    if (countriesChart) countriesChart.destroy();
    countriesChart = new ApexCharts(document.querySelector('#chartCountries'), {
        chart: { type: 'bar', height: 240, toolbar: { show: false } },
        series: [{ name: 'SMS', data: d.data }],
        xaxis: { categories: d.labels },
        colors: ['#0ea5e9'],
        plotOptions: { bar: { borderRadius: 6, distributed: true } },
        legend: { show: false },
        dataLabels: { enabled: false }
    });
    countriesChart.render();
});
}

// ── Recent OTPs ──────────────────────────────────────────────────────────────
function loadRecentOtps() {
$.getJSON(apiUrl('/ajax/dashboard_stats.php?recent=1'), function(d) {
    var tbody = $('#recentOtpsBody');
    if (!d.recent || d.recent.length === 0) {
        tbody.html('<tr><td colspan="7" class="text-center text-muted py-4"><i class="ri-inbox-line me-1"></i>No OTPs received yet.</td></tr>');
        return;
    }
    var html = '';
    d.recent.forEach(function(r) {
        html += '<tr>';
        html += '<td><small class="text-muted">' + (r.received_at || '') + '</small></td>';
        html += '<td><code class="text-primary">' + (r.number || '') + '</code></td>';
        html += '<td><span class="badge bg-info text-dark">' + (r.service || '–') + '</span></td>';
        html += '<td><span class="badge bg-secondary">' + (r.country || '–') + '</span></td>';
        html += '<td><strong class="text-dark">' + (r.otp || '') + '</strong></td>';
        html += '<td><small class="text-muted">' + ((r.message || '').substring(0, 60) + ((r.message || '').length > 60 ? '…' : '')) + '</small></td>';
        if (SHOW_PROFIT) {
            html += '<td>' + (r.profit ? '<span class="text-success fw-semibold">$' + parseFloat(r.profit).toFixed(6) + '</span>' : '<span class="text-muted">–</span>') + '</td>';
        }
        html += '</tr>';
    });
    tbody.html(html);
});
}

// ── Fetch OTPs trigger ───────────────────────────────────────────────────────
function triggerFetch() {
    var btn = document.getElementById('fetchBtn');
    if (!btn) return;
    btn.classList.add('loading');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Fetching…';

    $.ajax({
        url: APP_URL + '/ajax/cron_fetch.php',
        method: 'POST',
        dataType: 'json',
        data: { csrf_token: CSRF_TOKEN },
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN }
    }).done(function(d) {
        btn.classList.remove('loading');
        btn.disabled = false;
        btn.innerHTML = '<i class="ri-refresh-line me-1"></i>Fetch OTPs Now';
        if (d.status === 'success') {
            showToast('✅ Fetched! New SMS: ' + d.new_count, 'success');
            setTimeout(function() { location.reload(); }, 1500);
        } else {
            showToast(d.message || 'Fetch failed', 'warning');
        }
    }).fail(function() {
        btn.classList.remove('loading');
        btn.disabled = false;
        btn.innerHTML = '<i class="ri-refresh-line me-1"></i>Fetch OTPs Now';
        showToast('Fetch request failed', 'danger');
    });
}

$('#rangePreset').on('change', function() {
    currentRange = this.value;
    refreshDashboard();
});

refreshDashboard();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
