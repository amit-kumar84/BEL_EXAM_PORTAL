<?php
// Admin shell — used as include for all admin pages
require_once __DIR__ . '/../includes/helpers.php';
$u = require_login('admin');
ensure_softdelete_and_permissions();
ensure_bilingual_columns();
ensure_phase3_migrations();
$PAGE_TITLE = ($ADMIN_TITLE ?? 'Admin') . ' — BEL Kotdwar';
$ADMIN_SHELL = true;
$PUBLIC = false;
require __DIR__ . '/../includes/header.php';
$nav = [
  ['dashboard.php','Dashboard','fa-gauge-high'],
  ['students.php','Students','fa-users'],
  ['exams.php','Exams','fa-book-open'],
  ['live-monitor.php','Live Monitor','fa-satellite-dish'],
  ['results.php','Results & History','fa-chart-bar'],
];
if (!empty($u['is_super'])) {
  $nav[] = ['admins.php','Admin Accounts','fa-user-shield'];
  $nav[] = ['trash.php','Trash / Deleted','fa-trash-can-arrow-up'];
  $nav[] = ['logs.php','Activity Logs','fa-clock-rotate-left'];
}
?>
<div class="admin-layout">
  <div class="admin-sidebar-backdrop" data-testid="admin-sidebar-backdrop"></div>
  <aside class="admin-sidebar" data-testid="admin-sidebar">
    <div class="brand">
      <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" alt="BEL">
      <div><div class="fw-bold small">Bharat Electronics Ltd</div><div style="font-size:11px; letter-spacing:.08em; color:#94a3b8">KOTDWAR · ADMIN</div></div>
    </div>
    <?php foreach ($nav as $n): ?>
      <a href="<?= url('admin/' . $n[0]) ?>" class="nav-link <?= $curr === $n[0] ? 'active' : '' ?>" data-testid="nav-<?= str_replace('.php','',$n[0]) ?>"><i class="fas <?= $n[2] ?>"></i> <?= $n[1] ?></a>
    <?php endforeach; ?>
    <div class="user-box">
      <div style="color:#94a3b8">Signed in as</div>
      <div class="fw-semibold text-white"><?= h($u['name']) ?></div>
      <div style="color:#94a3b8; font-size:11px"><?= h($u['email']) ?></div>
      <a href="<?= url('logout.php') ?>" class="btn btn-sm btn-danger w-100 mt-2" data-testid="logout-btn"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
    </div>
  </aside>
  <main class="admin-main" data-testid="admin-main">
    <div class="admin-topbar">
      <div class="d-flex align-items-center">
        <button type="button" class="admin-mobile-burger" data-testid="admin-mobile-burger" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
        <h1><?= h($ADMIN_TITLE ?? 'Admin') ?></h1>
      </div>
      <span class="status-pill"><span class="dot"></span> System Online</span>
    </div>
    <div class="p-4">
<script>
(function(){
  var burger = document.querySelector('.admin-mobile-burger');
  var sb = document.querySelector('.admin-sidebar');
  var bd = document.querySelector('.admin-sidebar-backdrop');
  if (!burger || !sb || !bd) return;
  function open(){ sb.classList.add('is-open'); bd.classList.add('is-open'); }
  function close(){ sb.classList.remove('is-open'); bd.classList.remove('is-open'); }
  burger.addEventListener('click', function(){ sb.classList.contains('is-open') ? close() : open(); });
  bd.addEventListener('click', close);
})();
</script>
