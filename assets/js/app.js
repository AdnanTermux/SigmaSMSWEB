/**
 * Sigma SMS A2P — App JavaScript
 * Handles animations, toasts, helpers, and global UI behavior.
 */

/* ── Toast Notifications ─────────────────────────────────────────────────── */
var _toastQueue = [];

function showToast(msg, type, duration) {
    type     = type     || 'info';
    duration = duration || 3500;

    var colors = {
        success: '#059669',
        danger:  '#dc2626',
        warning: '#d97706',
        info:    '#4f46e5',
    };
    var icons = {
        success: 'ri-checkbox-circle-line',
        danger:  'ri-error-warning-line',
        warning: 'ri-alert-line',
        info:    'ri-information-line',
    };

    var toast = document.createElement('div');
    toast.className = 'sigma-toast';
    toast.style.background = colors[type] || colors.info;

    // Stack offset
    var offset = _toastQueue.length * 60;
    toast.style.bottom = (1.5 * 16 + offset) + 'px';

    toast.innerHTML =
        '<i class="' + (icons[type] || icons.info) + '" style="font-size:1.1rem;flex-shrink:0;"></i>' +
        '<span>' + msg + '</span>';

    document.body.appendChild(toast);
    _toastQueue.push(toast);

    setTimeout(function() {
        toast.classList.add('hiding');
        setTimeout(function() {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
            var idx = _toastQueue.indexOf(toast);
            if (idx > -1) _toastQueue.splice(idx, 1);
            // Re-stack remaining toasts
            _toastQueue.forEach(function(t, i) {
                t.style.bottom = (1.5 * 16 + i * 60) + 'px';
            });
        }, 280);
    }, duration);
}

/* ── Copy to Clipboard ───────────────────────────────────────────────────── */
function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function() {
            showToast('Copied to clipboard!', 'success');
        }).catch(function() {
            _fallbackCopy(text);
        });
    } else {
        _fallbackCopy(text);
    }
}

function _fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;';
    document.body.appendChild(ta);
    ta.select();
    try {
        document.execCommand('copy');
        showToast('Copied!', 'success');
    } catch (e) {
        showToast('Copy failed — please copy manually.', 'warning');
    }
    document.body.removeChild(ta);
}

/* ── AJAX Helper with CSRF ───────────────────────────────────────────────── */
function ajaxPost(url, data, cb, errCb) {
    var meta = document.querySelector('meta[name="csrf"]');
    if (meta) data.csrf_token = meta.content;
    $.post(url, data, cb, 'json').fail(function(xhr) {
        var msg = 'Request failed';
        try { msg = JSON.parse(xhr.responseText).message || msg; } catch(e) {}
        if (errCb) errCb(msg);
        else showToast(msg, 'danger');
    });
}

/* ── Animated Counter ────────────────────────────────────────────────────── */
function animateCount(id, target, prefix, suffix) {
    prefix = prefix || '';
    suffix = suffix || '';
    var el = document.getElementById(id);
    if (!el) return;
    var start    = 0;
    var duration = 900;
    var startTime = null;

    function step(timestamp) {
        if (!startTime) startTime = timestamp;
        var progress = Math.min((timestamp - startTime) / duration, 1);
        var eased    = 1 - Math.pow(1 - progress, 3); // ease-out cubic
        var current  = Math.floor(eased * target);
        el.textContent = prefix + current.toLocaleString() + suffix;
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}

/* ── Sidebar Toggle ──────────────────────────────────────────────────────── */
function initSidebar() {
    var toggleBtn = document.getElementById('sidebarToggle');
    var sidebar   = document.getElementById('sidebar');
    var main      = document.querySelector('.main');
    if (!toggleBtn || !sidebar) return;

    // Create overlay for mobile
    var overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    var isMobile = function() { return window.innerWidth < 992; };

    toggleBtn.addEventListener('click', function() {
        if (isMobile()) {
            sidebar.classList.toggle('collapsed');
            overlay.classList.toggle('active');
        } else {
            sidebar.classList.toggle('collapsed');
            if (main) main.classList.toggle('expanded');
        }
    });

    overlay.addEventListener('click', function() {
        sidebar.classList.remove('collapsed');
        overlay.classList.remove('active');
    });

    // Handle resize
    window.addEventListener('resize', function() {
        if (!isMobile()) {
            overlay.classList.remove('active');
        }
    });
}

/* ── Auto-dismiss Alerts ─────────────────────────────────────────────────── */
function initAlerts() {
    setTimeout(function() {
        document.querySelectorAll('.alert.fade.show').forEach(function(el) {
            try {
                if (typeof bootstrap !== 'undefined') {
                    bootstrap.Alert.getOrCreateInstance(el).close();
                }
            } catch(e) {}
        });
    }, 5000);
}

/* ── Intersection Observer for card animations ───────────────────────────── */
function initCardAnimations() {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    if (!('IntersectionObserver' in window)) return;
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('card-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    // Only observe cards that aren't already animated
    document.querySelectorAll('.card:not(.stat-animate):not(.chart-animate)').forEach(function(card) {
        observer.observe(card);
    });
}

function initPageTransitions() {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    document.querySelectorAll('a.sidebar-link[href]').forEach(function(link) {
        link.addEventListener('click', function() {
            document.body.classList.add('page-leaving');
        });
    });
}

/* ── Select2 Global Init ─────────────────────────────────────────────────── */
function initSelect2(selector, opts) {
    if (!$.fn.select2) return;
    $(selector).select2(Object.assign({ theme: 'bootstrap-5', width: '100%' }, opts || {}));
}

/* ── DataTable Defaults ──────────────────────────────────────────────────── */
function initDataTableDefaults() {
    if (!$.fn.DataTable) return;
    $.extend(true, $.fn.dataTable.defaults, {
        language: {
            paginate: { previous: '‹', next: '›' },
            search: '',
            searchPlaceholder: 'Search…',
            lengthMenu: '_MENU_ per page',
            info: 'Showing _START_–_END_ of _TOTAL_',
            infoEmpty: 'No records',
            zeroRecords: '<div class="text-center text-muted py-3"><i class="ri-inbox-line me-1"></i>No matching records found</div>',
            processing: '<div class="text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>',
        },
    });
}

/* ── Flatpickr Global Init ───────────────────────────────────────────────── */
function initFlatpickr() {
    if (typeof flatpickr === 'undefined') return;
    flatpickr('.flatpickr-date', { dateFormat: 'Y-m-d' });
    flatpickr('.flatpickr-datetime', { enableTime: true, dateFormat: 'Y-m-d H:i' });
}

/* ── Document Ready ──────────────────────────────────────────────────────── */
$(document).ready(function() {
    var csrfMeta = document.querySelector('meta[name="csrf"]');
    if (csrfMeta && $.ajaxSetup) {
        $.ajaxSetup({
            beforeSend: function(xhr, settings) {
                var method = (settings.type || 'GET').toUpperCase();
                if (method !== 'GET' && method !== 'HEAD') {
                    xhr.setRequestHeader('X-CSRF-TOKEN', csrfMeta.content);
                }
            }
        });
    }

    initSidebar();
    initAlerts();
    initCardAnimations();
    initPageTransitions();
    initDataTableDefaults();
    initFlatpickr();

    // Global Select2 for any .select2 class not inside a modal
    if ($.fn.select2) {
        $('select.select2:not([data-modal-select2])').select2({
            theme: 'bootstrap-5',
            width: '100%',
        });
    }

    // Tooltip init (Bootstrap 5)
    if (typeof bootstrap !== 'undefined') {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
            new bootstrap.Tooltip(el);
        });
    }
});
