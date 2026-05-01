  </div><!-- /content -->
</div><!-- /main -->
</div><!-- /wrapper -->

<!-- ── Scripts ──────────────────────────────────────────────────────────── -->
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap 5 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables core -->
<script src="https://cdn.datatables.net/2.0.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
<!-- DataTables Buttons -->
<script src="https://cdn.datatables.net/buttons/3.0.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.print.min.js"></script>
<!-- JSZip + PDFMake (for Excel/PDF export) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<!-- ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.52.0/dist/apexcharts.min.js"></script>
<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Flatpickr -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<!-- App JS -->
<script src="<?= APP_URL ?>/assets/js/app.js"></script>

<script>
// Global APP_URL for inline scripts
var APP_URL = "<?= APP_URL ?>";

// Topbar "Fetch OTPs" button (mini version)
function triggerFetchTop() {
    var btn = document.getElementById('fetchBtnTop');
    if (!btn) return;
    var csrfToken = (document.querySelector('meta[name="csrf"]') || {}).content || '';
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    $.ajax({
        url: APP_URL + '/ajax/cron_fetch.php',
        method: 'POST',
        dataType: 'json',
        data: { csrf_token: csrfToken },
        headers: { 'X-CSRF-TOKEN': csrfToken }
    }).done(function(d) {
        btn.disabled = false;
        btn.innerHTML = '<i class="ri-refresh-line"></i><span>Fetch OTPs</span>';
        if (d.status === 'success') {
            showToast('Fetched! New SMS: ' + d.new_count, 'success');
            if (d.new_count > 0) setTimeout(function() { location.reload(); }, 1500);
        } else {
            showToast(d.message || 'Fetch failed', 'warning');
        }
    }).fail(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="ri-refresh-line"></i><span>Fetch OTPs</span>';
        showToast('Fetch request failed', 'danger');
    });
}
</script>

<?php if (!empty($extraJs)) echo $extraJs; ?>
</body>
</html>
