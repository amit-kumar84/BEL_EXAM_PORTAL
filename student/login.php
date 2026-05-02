<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/lang.php';

if (current_user() && current_user()['role'] === 'student') redirect(url('student/dashboard.php'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ident = trim($_POST['identifier'] ?? '');
    $dob   = trim($_POST['dob'] ?? '');
    $pwd   = $_POST['password'] ?? '';
    $stmt = db()->prepare('SELECT * FROM users WHERE role="student" AND (email = ? OR username = ? OR roll_number = ?) LIMIT 1');
    $stmt->execute([strtolower($ident), $ident, $ident]);
    $u = $stmt->fetch();
    if (!$u || !password_verify($pwd, $u['password_hash']) || $u['dob'] !== $dob) {
        flash('Invalid credentials. Please check Roll/Staff ID, DOB and password.', 'error');
        redirect(url('student/login.php'));
    }
    unset($u['password_hash']);
    $_SESSION['user'] = $u;
    session_regenerate_id(true);
    redirect(url('student/dashboard.php'));
}
$BODY_CLASS = 'auth-page';
$PAGE_TITLE = t('sl_title') . ' — ' . t('brand');
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
          <div class="small" style="color:var(--saffron); letter-spacing:0.15em"><?= t('gov_in_hi') ?> · GOVT OF INDIA</div>
        </div>
      </div>
      <h2 class="display-6 fw-bold">Secure. Fair.<br>Government-grade testing.</h2>
      <p class="text-secondary mt-3">Lockdown environment, proctoring and instant evaluation — purpose-built for BEL recruitment.</p>
      <div class="tricolor mt-4" style="width:140px"><span></span><span></span><span></span></div>
    </div>
    <small class="text-secondary">© <?= date('Y') ?> <?= t('brand') ?></small>
  </div>
  <div class="right">
    <form method="post" style="width:100%; max-width:420px">
      <?= csrf_input() ?>
      <div class="d-flex align-items-center gap-3 mb-3">
        <div style="width:42px; height:42px; background:var(--navy); color:#fff; display:flex; align-items:center; justify-content:center; border-radius:3px; border-bottom: 2px solid var(--saffron)"><i class="fas fa-user-graduate"></i></div>
        <h3 class="fw-bold mb-0"><?= t('sl_title') ?></h3>
      </div>
      <p class="text-secondary small mb-4"><?= t('sl_sub') ?></p>

      <label class="form-label-xs"><?= t('sl_id') ?></label>
      <input name="identifier" class="form-control mb-3" required placeholder="BEL2034 / 21EC045">

      <label class="form-label-xs"><?= t('sl_dob') ?></label>
      <input type="date" name="dob" class="form-control mb-3" required>

      <label class="form-label-xs"><?= t('sl_pwd') ?></label>
      <input type="password" name="password" class="form-control mb-4" required>

      <button class="btn btn-navy w-100" data-testid="student-login-submit"><?= t('sl_submit') ?></button>
      <div class="mt-3 small">
        <a href="<?= url('index.php') ?>" class="text-decoration-none text-secondary"><i class="fas fa-arrow-left me-1"></i><?= t('sl_back') ?></a>
      </div>
      <div class="mt-4 small text-secondary" style="border-top:1px solid #e2e8f0; padding-top:10px; font-size:11px; line-height:1.5">
        <i class="fas fa-info-circle me-1"></i> For lost credentials, please contact the <b>BEL Kotdwar Examination Controller</b>. Passwords are not resettable online.
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
