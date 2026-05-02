<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/lang.php';
$role = ($_GET['role'] ?? 'admin') === 'admin' ? 'admin' : 'student';
if ($role === 'admin') {
    http_response_code(403);
    $PAGE_TITLE = 'Admin Password Reset';
    $PUBLIC = true;
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="container py-5" style="max-width: 760px">
      <div class="card shadow-sm border-0">
        <div class="card-body p-4 p-md-5">
          <h2 class="h4 fw-bold mb-3">Administrator password reset is not self-service</h2>
          <p class="text-secondary mb-4">Please contact the BEL administrator or super admin to reset admin login credentials.</p>
          <a href="<?= url('admin/login.php') ?>" class="btn btn-navy">Back to Admin Login</a>
        </div>
      </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}
if ($role === 'student') {
    // Student self-service password reset is disabled — admin issues credentials.
    http_response_code(404);
    die('<div style="font-family:sans-serif;padding:2em"><h3>Forgot password is not available for candidates.</h3><p>Please contact the BEL Kotdwar Examination Controller to reset your password.</p><p><a href="' . url('student/login.php') . '">← Back to Student Login</a></p></div>');
}
$step = 1;
$returned_token = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'request') {
        $ident = trim($_POST['identifier'] ?? '');
        $stmt = db()->prepare('SELECT id FROM users WHERE role = ? AND (email = ? OR username = ? OR roll_number = ?) LIMIT 1');
        $stmt->execute([$role, strtolower($ident), $ident, $ident]);
        $u = $stmt->fetch();
        if ($u) {
            $token = bin2hex(random_bytes(20));
            $exp = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $ins = db()->prepare('INSERT INTO password_reset (token, user_id, expires_at) VALUES (?, ?, ?)');
            $ins->execute([$token, $u['id'], $exp]);
            $returned_token = $token;
        }
        $step = 2;
    } elseif (($_POST['action'] ?? '') === 'reset') {
        $token = trim($_POST['token'] ?? '');
        $newpwd = $_POST['newpwd'] ?? '';
        if (strlen($newpwd) < 6) { flash('Password must be at least 6 characters', 'error'); $step = 2; }
        else {
            $stmt = db()->prepare('SELECT * FROM password_reset WHERE token = ? AND used = 0 AND expires_at > NOW()');
            $stmt->execute([$token]);
            $rec = $stmt->fetch();
            if (!$rec) { flash('Invalid or expired token', 'error'); $step = 2; }
            else {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($newpwd, PASSWORD_BCRYPT), $rec['user_id']]);
                db()->prepare('UPDATE password_reset SET used = 1 WHERE id = ?')->execute([$rec['id']]);
                flash('Password reset successfully. Please login.', 'success');
                redirect(url(($role === 'admin' ? 'admin/login.php' : 'student/login.php')));
            }
        }
    }
}
$PAGE_TITLE = t('fg_title');
require __DIR__ . '/includes/header.php';
?>
<div class="login-split">
  <div class="left d-none d-md-flex">
    <div>
      <div class="d-flex align-items-center gap-3 mb-5">
        <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" style="width:48px; height:48px; background:#fff; padding:6px; border-radius:3px; object-fit:contain" alt="BEL">
        <div><div class="fw-bold"><?= t('brand') ?></div><div class="small text-secondary"><?= t('brand_unit') ?></div></div>
      </div>
      <h2 class="display-6 fw-bold"><?= t('fg_title') ?></h2>
      <p class="text-secondary mt-3">Password reset for <?= $role === 'admin' ? 'administrator' : 'candidate' ?>.</p>
    </div>
    <small class="text-secondary">© <?= date('Y') ?> BEL Kotdwar</small>
  </div>
  <div class="right">
    <form method="post" style="width:100%; max-width:420px">
      <?= csrf_input() ?>
      <h3 class="fw-bold mb-4"><?= t('fg_title') ?></h3>
      <?php if ($step === 1): ?>
        <input type="hidden" name="action" value="request">
        <label class="form-label-xs"><?= $role === 'admin' ? 'Email / Username' : 'Roll/Staff ID, Username or Email' ?></label>
        <input name="identifier" class="form-control mb-4" required>
        <button class="btn btn-navy w-100"><?= t('fg_get') ?></button>
      <?php else: ?>
        <input type="hidden" name="action" value="reset">
        <?php if ($returned_token): ?>
          <div class="alert alert-warning small"><b><?= t('fg_token') ?> (1h):</b><br><code style="word-break:break-all"><?= h($returned_token) ?></code></div>
        <?php endif; ?>
        <label class="form-label-xs"><?= t('fg_token') ?></label>
        <input name="token" class="form-control mb-3 font-monospace" required value="<?= h($returned_token ?? '') ?>">
        <label class="form-label-xs"><?= t('fg_newpwd') ?></label>
        <input type="password" name="newpwd" minlength="6" class="form-control mb-4" required>
        <button class="btn btn-navy w-100"><?= t('fg_reset') ?></button>
      <?php endif; ?>
      <div class="mt-3 small"><a href="<?= url(($role === 'admin' ? 'admin/login.php' : 'student/login.php')) ?>" class="text-secondary"><i class="fas fa-arrow-left me-1"></i>Back to login</a></div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
