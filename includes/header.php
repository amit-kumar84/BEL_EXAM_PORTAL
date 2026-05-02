<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/lang.php';
$PAGE_TITLE = $PAGE_TITLE ?? 'BEL Kotdwar — Examination Portal';
$PUBLIC = $PUBLIC ?? false;
$ADMIN_SHELL = $ADMIN_SHELL ?? false;
?><!DOCTYPE html>
<html lang="<?= lang() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($PAGE_TITLE) ?></title>
<!-- Bootstrap 5.3.2 - Local Offline Copy -->
<link rel="stylesheet" href="<?= url('assets/lib/bootstrap/css/bootstrap.min.css') ?>">
<!-- Font Awesome 6.5.1 - Local Offline Copy -->
<link rel="stylesheet" href="<?= url('assets/lib/fontawesome/css/all.min.css') ?>">
<!-- Custom Application Styles -->
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body class="<?= trim(($ADMIN_SHELL ? 'admin-body ' : '') . ($PUBLIC ? 'public-body' : '')) ?>">

<?php if ($PUBLIC): ?>
<div class="tricolor"><span></span><span></span><span></span></div>
<div class="gov-bar">
  <div class="container d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-2">
      <span class="flag-swatch"></span>
      <span class="d-none d-sm-inline"><?= t('gov_in_hi') ?> · <?= t('gov_in') ?></span>
      <span class="muted">|</span>
      <span class="d-none d-md-inline"><?= t('mod') ?></span>
    </div>
    <div class="d-flex align-items-center gap-3">
      <span class="d-none d-md-inline muted">A- A A+</span>
      <a href="?lang=<?= lang() === 'en' ? 'hi' : 'en' ?>" class="lang-btn"><?= t('lang_toggle') ?></a>
    </div>
  </div>
</div>
<header class="brand-banner">
  <div class="container">
    <div class="brand-banner-inner">
      <a href="<?= url('index.php') ?>" class="brand-banner-mark" aria-label="<?= h(t('brand')) ?>">
        <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" alt="BEL">
      </a>
      <div class="brand-banner-copy">
        <div class="brand-banner-title"><?= t('brand') ?></div>
        <div class="brand-banner-subtitle">GOVERNMENT OF INDIA, Ministry of Defence · Public Sector Undertaking, KOTDWAR</div>
        <div class="brand-banner-meta">Examination Portal · Kotdwar Unit</div>
      </div>
      <div class="brand-banner-badge">
        <span class="status-pill status-pill-compact"><span class="dot"></span> <?= t('system_online') ?></span>
      </div>
    </div>
  </div>
</header>
<div class="sub-bar">
  <div class="container d-flex align-items-center justify-content-between">
    <span><?= t('bar_portal') ?></span>
    <span class="muted d-none d-md-inline"><?= t('mod') ?></span>
  </div>
</div>
<?php endif; ?>

<?php foreach ((flash() ?: []) as $f): ?>
  <div class="container mt-3">
    <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : ($f['type'] === 'success' ? 'success' : 'info') ?> flash-message fade show mb-0" data-auto-dismiss="5000">
      <?= h($f['msg']) ?>
    </div>
  </div>
<?php endforeach; ?>
