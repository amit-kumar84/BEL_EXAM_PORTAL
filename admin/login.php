<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/lang.php';

if (current_user() && current_user()['role'] === 'admin') redirect(url('admin/dashboard.php'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ident = trim($_POST['identifier'] ?? '');
    $pwd   = $_POST['password'] ?? '';
    $stmt = db()->prepare('SELECT * FROM users WHERE role="admin" AND (email = ? OR username = ?) LIMIT 1');
    $stmt->execute([strtolower($ident), $ident]);
    $u = $stmt->fetch();
    if (!$u || !password_verify($pwd, $u['password_hash'])) {
        flash('Invalid credentials.', 'error');
        redirect(url('admin/login.php'));
    }
    unset($u['password_hash']);
    $_SESSION['user'] = $u;
    session_regenerate_id(true);
    log_admin_activity('login', 'Admin login successful', $u, 'admin/login.php');
    redirect(url('admin/dashboard.php'));
}
$BODY_CLASS = 'auth-page';
$PAGE_TITLE = t('al_title') . ' — ' . t('brand');
require __DIR__ . '/../includes/header.php';
?>
<div class="login-split">
  <div class="left d-none d-md-flex">
    <div>
      <div class="d-flex align-items-center gap-3 mb-5">
        <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" style="width:48px; height:48px; background:#fff; padding:6px; border-radius:3px; object-fit:contain" alt="BEL">
        <div>
          <div class="fw-bold"><?= t('brand') ?></div>
          <div class="small text-secondary"><?= t('brand_unit') ?></div>
        </div>
      </div>
      <h2 class="display-6 fw-bold">Administrator Console</h2>
      <p class="text-secondary mt-3">Manage exams, students, questions, analytics and hall tickets.</p>
      <div class="tricolor mt-4" style="width:140px"><span></span><span></span><span></span></div>
    </div>
    <small class="text-secondary">© <?= date('Y') ?> BEL Kotdwar</small>
  </div>
  <div class="right">
    <form method="post" style="width:100%; max-width:420px">
      <?= csrf_input() ?>
      <div class="d-flex align-items-center gap-3 mb-3">
        <div style="width:42px; height:42px; background:var(--navy); color:#fff; display:flex; align-items:center; justify-content:center; border-radius:3px; border-bottom:2px solid var(--saffron)"><i class="fas fa-shield-alt"></i></div>
        <h3 class="fw-bold mb-0"><?= t('al_title') ?></h3>
      </div>
      <p class="text-secondary small mb-4"><?= t('al_sub') ?></p>

      <label class="form-label-xs"><?= t('al_id') ?></label>
      <input name="identifier" class="form-control mb-3" required placeholder="admin@belkotdwar.in">

      <label class="form-label-xs"><?= t('sl_pwd') ?></label>
      <input type="password" name="password" class="form-control mb-4" required>

      <button class="btn btn-navy w-100"><?= t('sl_submit') ?></button>
      <div class="d-flex justify-content-between align-items-start mt-3 small gap-3">
        <a href="<?= url('index.php') ?>" class="text-decoration-none text-secondary"><i class="fas fa-arrow-left me-1"></i><?= t('sl_back') ?></a>
        <span class="text-secondary text-end">For password reset, contact the BEL administrator.</span>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
