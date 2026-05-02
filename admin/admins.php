<?php $ADMIN_TITLE = 'Admin Accounts';
require_once __DIR__ . '/../includes/helpers.php'; $me = require_login('admin');
ensure_softdelete_and_permissions();
if (empty($me['is_super'])) {
  http_response_code(403);
  $PAGE_TITLE = 'Forbidden — BEL Kotdwar';
  $PUBLIC = false;
  require __DIR__ . '/../includes/header.php';
  ?>
  <div class="p-4">
    <div class="alert alert-danger mb-0">Only super admin can access the Admin Accounts section.</div>
  </div>
  <?php
  require __DIR__ . '/_shell_bottom.php';
  exit;
}
$canResetAdminPasswords = !empty($me['is_super']);
if ($_SERVER['REQUEST_METHOD']==='POST') { csrf_check();
  $a = $_POST['action'];
  if ($a==='add') {
    $email = strtolower(trim($_POST['email'])); $name = trim($_POST['name']); $pwd = $_POST['password'];
    if (strlen($pwd)<6) { flash('Password min 6 chars','error'); redirect(url('admin/admins.php')); }
    if (db()->prepare('SELECT 1 FROM users WHERE email=?')->execute([$email]) && db()->prepare('SELECT 1 FROM users WHERE email=?')->execute([$email])) {}
    $chk = db()->prepare('SELECT 1 FROM users WHERE email=?'); $chk->execute([$email]);
    if ($chk->fetch()) { flash('Email exists','error'); redirect(url('admin/admins.php')); }
    db()->prepare('INSERT INTO users (role,name,email,username,password_hash,is_super) VALUES ("admin",?,?,?,?,0)')
      ->execute([$name,$email,explode('@',$email)[0],password_hash($pwd,PASSWORD_BCRYPT)]);
    $newId = (int)db()->lastInsertId();
    $payload = ['table' => 'users', 'after' => ['id' => $newId, 'name' => $name, 'email' => $email, 'username' => explode('@',$email)[0], 'role' => 'admin', 'is_super' => 0]];
    log_admin_activity('admin_add', 'Created admin ' . $name . ' (' . $email . ')', $me, 'admin/admins.php', $payload);
    flash('Admin created','success');
  } elseif ($a==='delete') {
    $tid = (int)$_POST['id'];
    $t = db()->prepare('SELECT * FROM users WHERE id=? AND role="admin" AND deleted_at IS NULL'); $t->execute([$tid]); $u = $t->fetch();
    if (!$u) { flash('Not found','error'); }
    elseif ($u['is_super']) { flash('Cannot delete super admin','error'); }
    elseif ($u['id']==$me['id']) { flash('Cannot delete yourself','error'); }
    else {
      soft_delete('users', $tid, $me, " AND role='admin'");
      $payload = ['table' => 'users', 'before' => array_slice($u, 0, 10), 'soft_delete' => true];
      log_admin_activity('admin_delete', 'Soft-deleted admin ' . $u['name'] . ' (' . $u['email'] . ')', $me, 'admin/admins.php', $payload);
      flash('Moved admin to Trash','success');
    }
  } elseif ($a==='reset') {
    if (!$canResetAdminPasswords) {
      flash('Only super admin can reset admin passwords','error');
    } else {
      $tid = (int)$_POST['id'];
      $newName  = trim($_POST['new_name']  ?? '');
      $newEmail = strtolower(trim($_POST['new_email'] ?? ''));
      $newPwd   = trim($_POST['new_password'] ?? '');
      $t = db()->prepare('SELECT * FROM users WHERE id=? AND role="admin"'); $t->execute([$tid]); $u = $t->fetch();
      if (!$u) { flash('Not found','error'); redirect(url('admin/admins.php')); }
      if (!empty($u['is_super'])) { flash('Super admin is managed via the Edit button (requires verification).','error'); redirect(url('admin/admins.php')); }
      if ($newName === '')  { flash('Name is required','error'); redirect(url('admin/admins.php')); }
      if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) { flash('Valid email is required','error'); redirect(url('admin/admins.php')); }
      // Uniqueness check (allow same email on the same row)
      $dup = db()->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1'); $dup->execute([$newEmail, $tid]);
      if ($dup->fetch()) { flash('Email already used by another account','error'); redirect(url('admin/admins.php')); }
      $oldHash = $u['password_hash'];
      $newHash = $oldHash;
      if ($newPwd !== '') {
        if (strlen($newPwd) < 6) { flash('New password must be at least 6 characters','error'); redirect(url('admin/admins.php')); }
        $newHash = password_hash($newPwd, PASSWORD_BCRYPT);
      }
      // Password is stored ONLY as bcrypt hash; plain_password explicitly cleared for admins.
      db()->prepare('UPDATE users SET name=?, email=?, password_hash=?, plain_password=NULL WHERE id=? AND role="admin"')
        ->execute([$newName, $newEmail, $newHash, $tid]);
      $payload = ['table' => 'users',
        'before' => ['id'=>$tid, 'name'=>$u['name'], 'email'=>$u['email'], 'password_hash'=>$oldHash],
        'after'  => ['id'=>$tid, 'name'=>$newName, 'email'=>$newEmail, 'password_hash'=>$newHash]];
      log_admin_activity('admin_reset', 'Updated admin ' . $newName . ' (' . $newEmail . ')' . ($newPwd !== '' ? ' [password changed]' : ''), $me, 'admin/admins.php', $payload);
      flash('Admin account updated — ' . $newName . ' (' . $newEmail . ')' . ($newPwd !== '' ? ' with new password' : ''), 'success');
    }
  } elseif ($a==='super_edit') {
    // Super admin self-edits own email / password after answering developer-set verification question.
    if (empty($me['is_super'])) {
      flash('Only super admin can use this action','error');
      redirect(url('admin/admins.php'));
    }
    $answer   = trim($_POST['verify_answer'] ?? '');
    $newEmail = strtolower(trim($_POST['new_email'] ?? ''));
    $newPwd   = trim($_POST['new_password'] ?? '');
    if ($answer === '') { flash('Verification answer is required','error'); redirect(url('admin/admins.php')); }
    if (hash('sha256', strtolower($answer)) !== SUPER_VERIFY_ANSWER_HASH) {
      log_admin_activity('super_edit_denied', 'Super admin edit attempt — wrong verification answer', $me, 'admin/admins.php', ['answer_len' => strlen($answer)]);
      flash('Verification answer is incorrect. Action denied.','error');
      redirect(url('admin/admins.php'));
    }
    if ($newEmail === '' && $newPwd === '') {
      flash('Enter a new email or a new password (or both) to update.','error');
      redirect(url('admin/admins.php'));
    }
    $cur = db()->prepare('SELECT * FROM users WHERE id=? AND role="admin" AND is_super=1'); $cur->execute([(int)$me['id']]); $row = $cur->fetch();
    if (!$row) { flash('Super admin account not found','error'); redirect(url('admin/admins.php')); }
    $updEmail = $row['email'];
    if ($newEmail !== '') {
      if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) { flash('Valid email is required','error'); redirect(url('admin/admins.php')); }
      $dup = db()->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1'); $dup->execute([$newEmail, (int)$me['id']]);
      if ($dup->fetch()) { flash('Email already used by another account','error'); redirect(url('admin/admins.php')); }
      $updEmail = $newEmail;
    }
    $updHash = $row['password_hash'];
    if ($newPwd !== '') {
      if (strlen($newPwd) < 8) { flash('Super admin password must be at least 8 characters','error'); redirect(url('admin/admins.php')); }
      $updHash = password_hash($newPwd, PASSWORD_BCRYPT);
    }
    db()->prepare('UPDATE users SET email=?, password_hash=?, plain_password=NULL WHERE id=? AND is_super=1')
      ->execute([$updEmail, $updHash, (int)$me['id']]);
    // Refresh session so header shows updated email immediately
    $_SESSION['user']['email'] = $updEmail;
    log_admin_activity('super_edit', 'Super admin updated own account' . ($newEmail !== '' ? ' [email]' : '') . ($newPwd !== '' ? ' [password]' : ''), $me, 'admin/admins.php', ['fields_changed' => array_values(array_filter(['email'=>$newEmail!=='', 'password'=>$newPwd!=='']))]);
    flash('Super admin account updated successfully. Use the new credentials on next login.','success');
  } elseif ($a==='perms') {
    // Save per-admin permissions (super admin only)
    $tid = (int)$_POST['id'];
    $t = db()->prepare('SELECT * FROM users WHERE id=? AND role="admin"'); $t->execute([$tid]); $target = $t->fetch();
    if (!$target) { flash('Admin not found','error'); redirect(url('admin/admins.php')); }
    if (!empty($target['is_super'])) { flash('Super admin permissions are always unlimited','error'); redirect(url('admin/admins.php')); }
    $view_all_exams    = !empty($_POST['view_all_exams']) ? 1 : 0;
    $view_all_students = !empty($_POST['view_all_students']) ? 1 : 0;
    // Action-level matrix
    $matrix = [];
    foreach (['students','exams','questions','results'] as $sec) {
      foreach (['create','edit','delete','view'] as $act) {
        // 'results' uses 'view' only; others use create/edit/delete
        if ($sec === 'results' && $act !== 'view') continue;
        if ($sec !== 'results' && $act === 'view') continue;
        $matrix[$sec][$act] = !empty($_POST["perm_{$sec}_{$act}"]) ? 1 : 0;
      }
    }
    $encoded = json_encode($matrix, JSON_UNESCAPED_UNICODE);
    $up = db()->prepare('INSERT INTO admin_permissions (admin_id, perms, view_all_exams, view_all_students) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE perms=VALUES(perms), view_all_exams=VALUES(view_all_exams), view_all_students=VALUES(view_all_students)');
    $up->execute([$tid, $encoded, $view_all_exams, $view_all_students]);

    // ---- Specific-exam access grants (multi-exam picker in same modal) ----
    // Replace-all semantics: any exam not selected here is revoked for this admin.
    $selectedExams = isset($_POST['exam_grant_ids']) && is_array($_POST['exam_grant_ids']) ? array_map('intval', $_POST['exam_grant_ids']) : [];
    $level = in_array($_POST['exam_grant_level'] ?? '', ['view','edit','full'], true) ? $_POST['exam_grant_level'] : 'view';
    $existing = db()->prepare('SELECT exam_id FROM exam_admin_access WHERE admin_id=?');
    $existing->execute([$tid]);
    $existingIds = array_map('intval', $existing->fetchAll(PDO::FETCH_COLUMN));
    $toRevoke = array_diff($existingIds, $selectedExams);
    $toAddOrUpdate = $selectedExams;
    if ($toRevoke) {
        $in = implode(',', array_map('intval', $toRevoke));
        db()->exec("DELETE FROM exam_admin_access WHERE admin_id=$tid AND exam_id IN ($in)");
    }
    foreach ($toAddOrUpdate as $eidGrant) {
        if ($eidGrant <= 0) continue;
        db()->prepare('INSERT INTO exam_admin_access (exam_id, admin_id, access_level, granted_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE access_level=VALUES(access_level), granted_by=VALUES(granted_by), granted_at=NOW()')
          ->execute([$eidGrant, $tid, $level, (int)$me['id']]);
    }

    log_admin_activity('admin_perms_update', 'Updated permissions for ' . $target['name'] . ' (' . $target['email'] . ')', $me, 'admin/admins.php', ['admin_id'=>$tid,'view_all_exams'=>$view_all_exams,'view_all_students'=>$view_all_students,'matrix'=>$matrix,'exam_grants'=>['ids'=>$selectedExams,'level'=>$level,'revoked'=>array_values($toRevoke)]]);
    flash('Permissions updated for ' . $target['name'] . ' — ' . count($selectedExams) . ' exam grant(s) saved' . (count($toRevoke) ? ', ' . count($toRevoke) . ' revoked' : ''), 'success');
  }
  redirect(url('admin/admins.php'));
}
require __DIR__ . '/_shell_top.php';
$search = trim($_GET['search'] ?? '');
$where = [];
$params = [];
if ($search !== '') {
  $where[] = '(name LIKE ? OR email LIKE ?)';
  $params[] = '%' . $search . '%';
  $params[] = '%' . $search . '%';
}
$sql = 'SELECT u.*, ap.perms, ap.view_all_exams, ap.view_all_students FROM users u LEFT JOIN admin_permissions ap ON ap.admin_id=u.id WHERE u.role="admin" AND u.deleted_at IS NULL';
if ($where) $sql .= ' AND ' . implode(' AND ', $where);
$sql .= ' ORDER BY u.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Load all live exams for the modal multi-picker (only super admins reach this page)
$allExamsForPicker = db()->query("SELECT id, exam_name, exam_code, start_time, end_time FROM exams WHERE deleted_at IS NULL ORDER BY start_time DESC")->fetchAll();
// Load existing exam-grants per admin (for pre-selecting)
$grantMap = [];
$grantQ = db()->query("SELECT admin_id, exam_id, access_level FROM exam_admin_access");
foreach ($grantQ->fetchAll() as $g) {
  $grantMap[(int)$g['admin_id']][(int)$g['exam_id']] = $g['access_level'];
}
?>
<div class="d-flex justify-content-between mb-3 gap-2 flex-wrap">
  <form class="d-flex gap-2" method="get">
    <input type="text" name="search" class="form-control" placeholder="Search name or email..." value="<?= h($search) ?>" style="width:300px">
    <button class="btn btn-outline-secondary">Search</button>
    <?php if ($search !== ''): ?>
      <a href="<?= url('admin/admins.php') ?>" class="btn btn-outline-secondary">Clear</a>
    <?php endif; ?>
  </form>
  <button class="btn btn-navy" data-bs-toggle="modal" data-bs-target="#aa"><i class="fas fa-user-plus me-1"></i>Add Admin</button>
</div>
<table class="data-table"><thead><tr><th>Name</th><th>Email</th><th>Type</th><th>View Scope</th><th>Permissions</th><th>Created</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php
// Helper: mask email for display so only first char + domain TLD show (e.g. a***@***.com)
function mask_email_display(string $em): string {
  $at = strpos($em, '@');
  if ($at === false || $at < 1) return str_repeat('•', max(3, strlen($em)));
  $local = substr($em, 0, $at);
  $dom   = substr($em, $at+1);
  $lMasked = $local[0] . str_repeat('•', max(2, strlen($local)-1));
  $dotPos = strrpos($dom, '.');
  if ($dotPos === false) { $dMasked = str_repeat('•', strlen($dom)); }
  else { $dMasked = str_repeat('•', $dotPos) . substr($dom, $dotPos); }
  return $lMasked . '@' . $dMasked;
}
?>
<?php foreach ($rows as $r):
  $rPerms = $r['perms'] ? (json_decode($r['perms'], true) ?: []) : [];
  $rDefaults = default_admin_perms();
  $rEff = array_replace_recursive($rDefaults, $rPerms);
  $isSelfSuper = !empty($r['is_super']) && (int)$r['id'] === (int)$me['id'];
  // Super admin email is hidden/masked except when the super admin is viewing their own row
  $emailDisplay = !empty($r['is_super']) && !$isSelfSuper ? mask_email_display($r['email']) : h($r['email']);
?>
  <tr><td class="fw-medium"><?= h($r['name']) ?></td><td data-testid="admin-email-cell-<?= $r['id'] ?>"><?= $emailDisplay ?>
      <?php if (!empty($r['is_super'])): ?><i class="fas fa-lock text-muted ms-1" title="Email encrypted/hidden — visible only to the super admin themselves"></i><?php endif; ?>
    </td>
    <td><?php if ($r['is_super']): ?><span class="badge bg-success">Super Admin</span><?php else: ?><span class="badge bg-secondary">Admin</span><?php endif; ?></td>
    <td>
      <?php if ($r['is_super']): ?>
        <span class="badge bg-success">Full access</span>
      <?php else: ?>
        <div class="small">
          <span class="badge <?= !empty($r['view_all_exams'])?'bg-info':'bg-light text-dark border' ?>">Exams: <?= !empty($r['view_all_exams'])?'All':'Own only' ?></span>
          <span class="badge <?= !empty($r['view_all_students'])?'bg-info':'bg-light text-dark border' ?>">Students: <?= !empty($r['view_all_students'])?'All':'Own only' ?></span>
        </div>
      <?php endif; ?>
    </td>
    <td>
      <?php if ($r['is_super']): ?>
        <span class="small text-muted">—</span>
      <?php else: ?>
        <?php
          $pills = [];
          foreach (['students','exams','questions'] as $sec) {
            $actions = [];
            foreach (['create','edit','delete'] as $act) {
              if (!empty($rEff[$sec][$act])) $actions[] = strtoupper(substr($act,0,1));
            }
            if ($actions) $pills[] = '<span class="badge bg-light text-dark border" style="font-size:10px">'.$sec.': '.implode('',$actions).'</span>';
          }
          echo implode(' ', $pills) ?: '<span class="small text-muted">No permissions</span>';
          $gCnt = count($grantMap[(int)$r['id']] ?? []);
          if ($gCnt > 0) echo ' <span class="badge bg-warning text-dark" style="font-size:10px" title="Specific exam grants"><i class="fas fa-key me-1"></i>'.$gCnt.' exam'.($gCnt!==1?'s':'').'</span>';
        ?>
      <?php endif; ?>
    </td>
    <td class="small text-muted"><?= fmt_dt($r['created_at']) ?></td>
    <td class="text-end">
      <?php if ($isSelfSuper): ?>
        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#superEditModal" data-testid="super-edit-btn"><i class="fas fa-user-pen me-1"></i>Edit</button>
      <?php endif; ?>
      <?php if (!$r['is_super']): ?>
        <?php $myGrants = $grantMap[(int)$r['id']] ?? []; ?>
        <button type="button" class="btn btn-sm btn-outline-primary perms-btn"
          data-id="<?= $r['id'] ?>" data-name="<?= h($r['name']) ?>" data-email="<?= h($r['email']) ?>"
          data-view-all-exams="<?= !empty($r['view_all_exams'])?1:0 ?>"
          data-view-all-students="<?= !empty($r['view_all_students'])?1:0 ?>"
          data-perms='<?= h(json_encode($rEff)) ?>'
          data-grants='<?= h(json_encode($myGrants)) ?>'>
          <i class="fas fa-shield-halved me-1"></i>Permissions
        </button>
      <?php endif; ?>
      <?php if ($canResetAdminPasswords && !$r['is_super']): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary reset-admin-btn" data-bs-toggle="modal" data-bs-target="#resetAdminModal" data-admin-id="<?= $r['id'] ?>" data-admin-name="<?= h($r['name']) ?>" data-admin-email="<?= h($r['email']) ?>" data-testid="reset-admin-btn-<?= $r['id'] ?>">Reset</button>
      <?php endif; ?>
      <?php if (!$r['is_super']): ?>
        <form method="post" class="d-inline" onsubmit="return confirm('Move this admin to Trash?')"><?= csrf_input() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
      <?php endif; ?>
    </td>
  </tr>
<?php endforeach; ?>
</tbody></table>

<div class="modal fade" id="aa"><div class="modal-dialog"><form method="post" class="modal-content"><?= csrf_input() ?>
  <input type="hidden" name="action" value="add">
  <div class="modal-header"><h5 class="modal-title">Add Admin</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <label class="form-label-xs">Name</label><input name="name" class="form-control mb-2" required>
    <label class="form-label-xs">Email</label><input name="email" type="email" class="form-control mb-2" required>
    <label class="form-label-xs">Password (min 6)</label><input name="password" type="password" minlength="6" class="form-control" required>
  </div>
  <div class="modal-footer"><button class="btn btn-navy">Create</button></div>
</form></div></div>
<div class="modal fade" id="resetAdminModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content" data-testid="reset-admin-form">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="reset">
      <input type="hidden" name="id" id="reset-admin-id">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Edit Admin (Name / Email / Password)</h5>
          <div class="small text-muted" id="reset-admin-target"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label-xs">Name</label>
        <input type="text" name="new_name" id="reset-admin-name" class="form-control mb-2" required data-testid="reset-admin-name-input">
        <label class="form-label-xs">Email</label>
        <input type="email" name="new_email" id="reset-admin-email" class="form-control mb-2" required data-testid="reset-admin-email-input">
        <label class="form-label-xs">New Password <span class="text-muted">(leave blank to keep current)</span></label>
        <input type="password" name="new_password" id="reset-admin-password" minlength="6" class="form-control" data-testid="reset-admin-password-input">
        <div class="small text-muted mt-2"><i class="fas fa-lock me-1"></i>Passwords are stored encrypted (bcrypt hash) — never in plaintext.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-navy" data-testid="reset-admin-submit">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php if (!empty($me['is_super'])): ?>
<div class="modal fade" id="superEditModal" tabindex="-1" aria-hidden="true" data-testid="super-edit-modal">
  <div class="modal-dialog">
    <form method="post" class="modal-content" data-testid="super-edit-form">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="super_edit">
      <div class="modal-header bg-warning-subtle">
        <div>
          <h5 class="modal-title mb-0"><i class="fas fa-user-shield me-1 text-danger"></i>Update Super Admin Credentials</h5>
          <div class="small text-muted">Developer-set verification required</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning small py-2"><i class="fas fa-triangle-exclamation me-1"></i>You must answer the developer's verification question correctly to update the super admin email or password. Wrong answers are logged.</div>
        <label class="form-label-xs fw-bold">Verification Question</label>
        <div class="p-2 bg-light border rounded mb-2" data-testid="super-edit-question"><?= h(SUPER_VERIFY_QUESTION) ?></div>
        <label class="form-label-xs">Your Answer <span class="text-danger">*</span></label>
        <input type="text" name="verify_answer" class="form-control mb-3" required autocomplete="off" data-testid="super-edit-answer-input">
        <hr>
        <label class="form-label-xs">New Email <span class="text-muted">(leave blank to keep current)</span></label>
        <input type="email" name="new_email" class="form-control mb-2" placeholder="e.g. superadmin@belkotdwar.in" autocomplete="off" data-testid="super-edit-email-input">
        <label class="form-label-xs">New Password <span class="text-muted">(min 8 chars; leave blank to keep current)</span></label>
        <input type="password" name="new_password" class="form-control" minlength="8" autocomplete="new-password" data-testid="super-edit-password-input">
        <div class="small text-muted mt-2"><i class="fas fa-shield-halved me-1"></i>Password is stored encrypted (bcrypt). Current email and password are never displayed.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger" data-testid="super-edit-submit"><i class="fas fa-key me-1"></i>Update Credentials</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('resetAdminModal');
  if (!modal) return;
  modal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    if (!button) return;
    const nm = button.getAttribute('data-admin-name') || '';
    const em = button.getAttribute('data-admin-email') || '';
    document.getElementById('reset-admin-id').value = button.getAttribute('data-admin-id') || '';
    document.getElementById('reset-admin-target').textContent = 'Editing ' + nm + ' (' + em + ')';
    document.getElementById('reset-admin-name').value = nm;
    document.getElementById('reset-admin-email').value = em;
    document.getElementById('reset-admin-password').value = '';
  });
});

// Permissions modal logic
document.querySelectorAll('.perms-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const d = btn.dataset;
    document.getElementById('p-id').value = d.id;
    document.getElementById('p-title-target').textContent = d.name + ' (' + d.email + ')';
    document.getElementById('p-view-all-exams').checked = d.viewAllExams === '1';
    document.getElementById('p-view-all-students').checked = d.viewAllStudents === '1';
    try {
      const perms = JSON.parse(d.perms || '{}');
      ['students','exams','questions'].forEach(sec => {
        ['create','edit','delete'].forEach(act => {
          const cb = document.getElementById(`p-${sec}-${act}`);
          if (cb) cb.checked = !!(perms[sec] && perms[sec][act]);
        });
      });
      const r = document.getElementById('p-results-view');
      if (r) r.checked = !!(perms.results && perms.results.view);
    } catch(e){}

    // Specific-exam access pre-fill
    let grants = {};
    try { grants = JSON.parse(d.grants || '{}'); } catch(e){ grants = {}; }
    const grantedIds = Object.keys(grants).map(Number);
    document.querySelectorAll('.p-grant-checkbox').forEach(cb => {
      cb.checked = grantedIds.includes(parseInt(cb.value));
    });
    // Use highest level present (full > edit > view) as the default for the dropdown
    const levels = Object.values(grants);
    const lvlSel = document.getElementById('p-grant-level');
    if (lvlSel) {
      lvlSel.value = levels.includes('full') ? 'full' : (levels.includes('edit') ? 'edit' : 'view');
    }
    // Reset filter
    const f = document.getElementById('p-grant-filter'); if (f) { f.value=''; filterGrantRows(''); }
    const sa = document.getElementById('p-grant-all'); if (sa) sa.checked = false;

    new bootstrap.Modal(document.getElementById('permsModal')).show();
  });
});

function filterGrantRows(q) {
  q = (q || '').toLowerCase();
  document.querySelectorAll('.p-grant-row').forEach(r => {
    const name = (r.querySelector('.p-grant-name')?.textContent || '').toLowerCase();
    const code = (r.querySelector('.p-grant-code')?.textContent || '').toLowerCase();
    r.style.display = (!q || name.includes(q) || code.includes(q)) ? '' : 'none';
  });
}
document.getElementById('p-grant-filter')?.addEventListener('input', e => filterGrantRows(e.target.value));
document.getElementById('p-grant-all')?.addEventListener('change', e => {
  const checked = e.target.checked;
  document.querySelectorAll('.p-grant-row').forEach(r => {
    if (r.style.display !== 'none') {
      const cb = r.querySelector('.p-grant-checkbox');
      if (cb) cb.checked = checked;
    }
  });
});
</script>

<!-- Permissions Modal -->
<div class="modal fade" id="permsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="perms">
      <input type="hidden" name="id" id="p-id">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0"><i class="fas fa-shield-halved me-1"></i>Manage Permissions</h5>
          <div class="small text-muted" id="p-title-target"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info small mb-3">
          <i class="fas fa-circle-info me-1"></i>
          By default a normal admin sees and manages only records <b>they themselves created</b>. Grant <b>View All</b> below to let this admin see data created by other admins.
        </div>

        <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="fas fa-globe me-1"></i>View Scope (cross-admin visibility)</h6>
        <div class="form-check form-switch mb-2">
          <input class="form-check-input" type="checkbox" id="p-view-all-exams" name="view_all_exams" value="1">
          <label class="form-check-label" for="p-view-all-exams"><b>View all Exams</b> (across all hosts — otherwise only exams this admin created)</label>
        </div>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="p-view-all-students" name="view_all_students" value="1">
          <label class="form-check-label" for="p-view-all-students"><b>View all Students</b> (across all hosts — otherwise only students this admin created)</label>
        </div>

        <h6 class="fw-bold border-bottom pb-2 mb-3 mt-4"><i class="fas fa-key me-1"></i>Action Permissions (on records visible to this admin)</h6>
        <table class="table table-sm align-middle">
          <thead><tr class="table-light"><th>Section</th><th class="text-center">Create</th><th class="text-center">Edit</th><th class="text-center">Delete</th></tr></thead>
          <tbody>
            <?php foreach (['students'=>'Students','exams'=>'Exams','questions'=>'Questions'] as $sec=>$lbl): ?>
              <tr>
                <td class="fw-bold"><?= h($lbl) ?></td>
                <?php foreach (['create','edit','delete'] as $act): ?>
                  <td class="text-center">
                    <input type="checkbox" class="form-check-input" id="p-<?= $sec ?>-<?= $act ?>" name="perm_<?= $sec ?>_<?= $act ?>" value="1">
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
            <tr>
              <td class="fw-bold">Results</td>
              <td colspan="3">
                <label class="small"><input type="checkbox" class="form-check-input me-1" id="p-results-view" name="perm_results_view" value="1"> View results page</label>
              </td>
            </tr>
          </tbody>
        </table>

        <div class="small text-muted">
          <b>Note:</b> Super admin always has full access regardless of these settings. Delete = soft-delete (moved to Trash); only super admin can <i>permanently</i> delete from Trash.
        </div>

        <h6 class="fw-bold border-bottom pb-2 mb-3 mt-4"><i class="fas fa-key me-1 text-warning"></i>Specific Exam Access (single / multiple)</h6>
        <div class="alert alert-info small py-2 mb-3">
          <i class="fas fa-circle-info me-1"></i>
          Pick one or many exams below. The chosen access level applies to <b>all selected exams</b>. The admin will also see the students <b>registered for those exams</b> automatically — no separate student grant needed.
        </div>
        <div class="row g-2 mb-2">
          <div class="col-md-4">
            <label class="form-label-xs">Access Level for selected exams</label>
            <select name="exam_grant_level" id="p-grant-level" class="form-select">
              <option value="view">i. View-only</option>
              <option value="edit">ii. View + Edit questions / monitor live / see students</option>
              <option value="full">iii. Full control (edit, delete, monitor, results)</option>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label-xs">Filter exams</label>
            <input type="text" id="p-grant-filter" class="form-control" placeholder="Search by exam name or code…">
          </div>
        </div>
        <div class="border" style="max-height:240px; overflow-y:auto; border-radius:4px">
          <table class="table table-sm mb-0 align-middle" id="p-grant-table">
            <thead class="table-light"><tr><th style="width:40px"><input type="checkbox" class="form-check-input" id="p-grant-all"></th><th>Exam</th><th style="width:120px">Code</th><th style="width:160px">Window</th></tr></thead>
            <tbody>
            <?php foreach ($allExamsForPicker as $ex): ?>
              <tr class="p-grant-row">
                <td><input type="checkbox" class="form-check-input p-grant-checkbox" name="exam_grant_ids[]" value="<?= (int)$ex['id'] ?>"></td>
                <td class="fw-medium p-grant-name"><?= h($ex['exam_name']) ?></td>
                <td class="font-monospace small p-grant-code"><?= h($ex['exam_code']) ?></td>
                <td class="small text-muted"><?= fmt_dt($ex['start_time']) ?> → <?= date('d M', strtotime($ex['end_time'])) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$allExamsForPicker): ?>
              <tr><td colspan="4" class="text-center text-muted py-3">No exams in the system yet.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="small text-muted mt-2"><i class="fas fa-circle-info me-1"></i>Saving with <b>no exams checked</b> will revoke all existing exam grants for this admin.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-navy"><i class="fas fa-save me-1"></i>Save Permissions</button>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/_shell_bottom.php'; ?>
