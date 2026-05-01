<?php
/**
 * Sigma SMS A2P — Shared Header / Sidebar
 * Included at the top of every authenticated page.
 */
$user     = getCurrentUser();
$role     = $user['role'];
$unread   = getUnreadNotificationCount((int)$user['id']);
$flash    = getFlash();
$siteName = getSetting('site_name', APP_NAME);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= h(csrfToken()) ?>">
<title><?= h($pageTitle ?? 'Dashboard') ?> — <?= h($siteName) ?></title>

<!-- Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<!-- Remixicon -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.min.css">
<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.0.2/css/buttons.bootstrap5.min.css">
<!-- Select2 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<!-- App CSS -->
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body>
<div class="wrapper">

<!-- ── Sidebar ──────────────────────────────────────────────────────────── -->
<aside id="sidebar">
  <div class="sidebar-logo">
    <a href="<?= APP_URL ?>/dashboard.php" class="sidebar-brand">
      <div class="sidebar-brand-icon">🔐</div>
      <div class="sidebar-brand-text"><?= h($siteName) ?></div>
    </a>
  </div>

  <div class="sidebar-inner">
    <ul class="sidebar-nav">

      <!-- Dashboard -->
      <li class="sidebar-item <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
        <a class="sidebar-link" href="<?= APP_URL ?>/dashboard.php">
          <i class="ri-dashboard-line"></i><span>Dashboard</span>
        </a>
      </li>

      <!-- Reports -->
      <li class="sidebar-header">Reports</li>
      <li class="sidebar-item <?= basename($_SERVER['PHP_SELF']) === 'sms_reports.php' ? 'active' : '' ?>">
        <a class="sidebar-link" href="<?= APP_URL ?>/sms_reports.php">
          <i class="ri-message-2-line"></i><span>SMS Reports</span>
        </a>
      </li>
      <li class="sidebar-item <?= basename($_SERVER['PHP_SELF']) === 'profit_stats.php' ? 'active' : '' ?>">
        <a class="sidebar-link" href="<?= APP_URL ?>/profit_stats.php">
          <i class="ri-money-dollar-circle-line"></i><span>Profit Stats</span>
        </a>
      </li>

      <!-- Numbers -->
      <li class="sidebar-header">Numbers</li>
      <?php if (in_array($role, ['admin', 'manager'])): ?>
      <li class="sidebar-item <?= basename($_SERVER['PHP_SELF']) === 'numbers.php' ? 'active' : '' ?>">
        <a class="sidebar-link" href="<?= APP_URL ?>/numbers.php">
          <i class="ri-sim-card-line"></i><span>Manage Numbers</span>
        </a>
      </li>
      <?php endif; ?>
      <?php if (in_array($role, ['reseller', 'sub_reseller'])): ?>
      <li class="sidebar-item <?= basename($_SERVER['PHP_SELF']) === 'my_numbers.php' ? 'active' : '' ?>">
        <a class="sidebar-link" href="<?= APP_URL ?>/my_numbers.php">
          <i class="ri-sim-card-line"></i><span>My Numbers</span>
        </a>
      </li>
      <?php endif; ?>

      <!-- Users -->
      <?php if (in_array($role, ['admin', 'manager', 'reseller'])): ?>
      <li class="sidebar-header">Users</li>
      <li class="sidebar-item <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>">
        <a class="sidebar-link" href="<?= APP_URL ?>/users.php">
          <i class="ri-team-line"></i>
          <span><?= $role === 'reseller' ? 'My Clients' : 'Manage Users' ?></span>
        </a>
      </li>
      <?php endif; ?>

      <!-- Finance -->
      <li class="sidebar-header">Finance</li>
      <li class="sidebar-item <?= basename($_SERVER['PHP_SELF']) === 'payment_requests.php' ? 'active' : '' ?>">
        <a class="sidebar-link" href="<?= APP_URL ?>/payment_requests.php">
          <i class="ri-bank-card-line"></i><span>Payment Requests</span>
        </a>
      </li>
      <?php if (in_array($role, ['admin', 'manager'])): ?>
      <li class="sidebar-item <?= basename($_SERVER['PHP_SELF']) === 'credit_notes.php' ? 'active' : '' ?>">
        <a class="sidebar-link" href="<?= APP_URL ?>/credit_notes.php">
          <i class="ri-file-list-3-line"></i><span>Credit Notes</span>
        </a>
      </li>
      <li class="sidebar-item <?= basename($_SERVER['PHP_SELF']) === 'statements.php' ? 'active' : '' ?>">
        <a class="sidebar-link" href="<?= APP_URL ?>/statements.php">
          <i class="ri-file-chart-line"></i><span>Statements</span>
        </a>
      </li>
      <?php endif; ?>
      <li class="sidebar-item <?= basename($_SERVER['PHP_SELF']) === 'bank_accounts.php' ? 'active' : '' ?>">
        <a class="sidebar-link" href="<?= APP_URL ?>/bank_accounts.php">
          <i class="ri-bank-line"></i><span>Bank Accounts</span>
        </a>
      </li>

      <!-- System -->
      <?php if (in_array($role, ['admin', 'manager'])): ?>
      <li class="sidebar-header">System</li>
      <li class="sidebar-item <?= basename($_SERVER['PHP_SELF']) === 'news_master.php' ? 'active' : '' ?>">
        <a class="sidebar-link" href="<?= APP_URL ?>/news_master.php">
          <i class="ri-megaphone-line"></i><span>Announcements</span>
        </a>
      </li>
      <?php endif; ?>

      <!-- Account -->
      <li class="sidebar-header">Account</li>
      <li class="sidebar-item <?= basename($_SERVER['PHP_SELF']) === 'notifications.php' ? 'active' : '' ?>">
        <a class="sidebar-link" href="<?= APP_URL ?>/notifications.php">
          <i class="ri-notification-line"></i>
          <span>
            Notifications
            <?php if ($unread > 0): ?>
              <span class="badge bg-danger ms-1" style="font-size:.6rem;padding:.2em .45em;"><?= $unread ?></span>
            <?php endif; ?>
          </span>
        </a>
      </li>
      <li class="sidebar-item <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>">
        <a class="sidebar-link" href="<?= APP_URL ?>/profile.php">
          <i class="ri-user-settings-line"></i><span>Profile &amp; API</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a class="sidebar-link text-danger" href="<?= APP_URL ?>/logout.php"
           onclick="return confirm('Sign out?')">
          <i class="ri-logout-circle-line"></i><span>Logout</span>
        </a>
      </li>

    </ul>
  </div>
</aside>

<!-- ── Main Content ──────────────────────────────────────────────────────── -->
<div class="main">

  <!-- Topbar -->
  <nav class="navbar navbar-expand px-3 py-0">
    <!-- Sidebar toggle -->
    <button class="btn btn-sm btn-light me-2 d-flex align-items-center justify-content-center"
            id="sidebarToggle" type="button" style="width:36px;height:36px;border-radius:8px;">
      <i class="ri-menu-line fs-5"></i>
    </button>

    <!-- Breadcrumb / page title -->
    <span class="text-muted d-none d-md-inline" style="font-size:.82rem;">
      <?= h($pageTitle ?? '') ?>
    </span>

    <!-- Right side -->
    <div class="ms-auto d-flex align-items-center gap-3">

      <!-- Fetch OTPs button (admin/manager only) -->
      <?php if (in_array($role, ['admin', 'manager'])): ?>
      <button class="btn btn-sm btn-primary btn-fetch d-none d-md-flex align-items-center gap-1"
              id="fetchBtnTop" onclick="triggerFetchTop()" style="font-size:.78rem;">
        <i class="ri-refresh-line"></i>
        <span>Fetch OTPs</span>
      </button>
      <?php endif; ?>

      <!-- Notifications bell -->
      <a href="<?= APP_URL ?>/notifications.php"
         class="position-relative text-secondary text-decoration-none d-flex align-items-center"
         style="font-size:1.2rem;">
        <i class="ri-notification-3-line"></i>
        <?php if ($unread > 0): ?>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                style="font-size:.55rem;padding:.25em .4em;"><?= $unread ?></span>
        <?php endif; ?>
      </a>

      <!-- User dropdown -->
      <div class="dropdown">
        <a class="d-flex align-items-center gap-2 text-decoration-none dropdown-toggle"
           href="#" data-bs-toggle="dropdown" aria-expanded="false">
          <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
               style="width:34px;height:34px;background:linear-gradient(135deg,#4f46e5,#0ea5e9);font-size:.8rem;flex-shrink:0;">
            <?= strtoupper(substr($user['username'], 0, 1)) ?>
          </div>
          <div class="d-none d-md-block lh-1">
            <div style="font-size:.84rem;font-weight:600;color:#0f172a;"><?= h($user['username']) ?></div>
            <div style="font-size:.72rem;color:#64748b;"><?= ucfirst(str_replace('_', ' ', $role)) ?></div>
          </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius:10px;min-width:180px;">
          <li>
            <a class="dropdown-item d-flex align-items-center gap-2" href="<?= APP_URL ?>/profile.php">
              <i class="ri-user-line text-primary"></i>Profile &amp; API
            </a>
          </li>
          <li>
            <a class="dropdown-item d-flex align-items-center gap-2" href="<?= APP_URL ?>/notifications.php">
              <i class="ri-notification-line text-primary"></i>Notifications
              <?php if ($unread > 0): ?>
                <span class="badge bg-danger ms-auto"><?= $unread ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li><hr class="dropdown-divider my-1"></li>
          <li>
            <a class="dropdown-item d-flex align-items-center gap-2 text-danger"
               href="<?= APP_URL ?>/logout.php" onclick="return confirm('Sign out?')">
              <i class="ri-logout-circle-line"></i>Logout
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Flash message -->
  <?php if ($flash): ?>
  <div class="px-3 pt-3">
    <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show mb-0" role="alert">
      <i class="ri-<?= $flash['type'] === 'success' ? 'checkbox-circle' : ($flash['type'] === 'danger' ? 'error-warning' : 'information') ?>-line me-2"></i>
      <?= h($flash['msg']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  </div>
  <?php endif; ?>

  <!-- Page content -->
  <div class="content">
