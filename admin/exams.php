<?php $ADMIN_TITLE = 'Exams';
require_once __DIR__ . '/../includes/helpers.php'; $me = require_login('admin');
ensure_softdelete_and_permissions();
// ensure exam_code column exists
ensure_exam_code_column();
if ($_SERVER['REQUEST_METHOD']==='POST') { csrf_check();
  $a = $_POST['action'] ?? '';
  if ($a === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    // Ownership / permission check on edit
    if ($id) {
      // Owner OR super OR admin with edit/full grant on this exam can edit basic details + proctor settings
      $accLvl = exam_access_for($id, $me);
      if ($accLvl !== 'full' && $accLvl !== 'edit') {
        flash('You do not have edit permission on this exam.','error'); redirect(url('admin/exams.php'));
      }
      if (!$me['is_super'] && !can('exams','edit',$me)) { flash('Permission denied: edit exams.','error'); redirect(url('admin/exams.php')); }
    } else {
      if (!can('exams','create',$me)) { flash('Permission denied: create exams.','error'); redirect(url('admin/exams.php')); }
    }
    $exam_code = trim($_POST['exam_code'] ?? '');
    if ($exam_code === '') {
      flash('Exam code is required.', 'error');
      redirect(url('admin/exams.php'));
    }
    $dup = db()->prepare('SELECT id FROM exams WHERE exam_code=? AND id<>? AND deleted_at IS NULL LIMIT 1');
    $dup->execute([$exam_code, $id]);
    if ($dup->fetch()) {
      flash('This exam code already assigned to another exam. Please give another code.', 'error');
      redirect(url('admin/exams.php'));
    }
    $d = [trim($_POST['exam_name']), $exam_code, (int)$_POST['duration_minutes'], (int)$_POST['max_attempts'],
          str_replace('T',' ', $_POST['start_time']).':00', str_replace('T',' ', $_POST['end_time']).':00',
          (int)($_POST['total_marks'] ?: 0), trim($_POST['instructions']??'')];
    // Per-exam violation config (super-admin-gated values coming from the modal).
    $vcfg = default_violation_config();
    foreach (array_keys($vcfg) as $k) $vcfg[$k] = !empty($_POST["viol_$k"]) ? 1 : 0;
    $vcfgJson = json_encode($vcfg);
    $forceFs = !empty($_POST['force_fullscreen']) ? 1 : 0;
    $maxV    = (int)($_POST['max_violations'] ?? 5); if ($maxV < 1) $maxV = 1;
    if ($id) {
      $before = db()->prepare('SELECT * FROM exams WHERE id=?'); $before->execute([$id]); $beforeRow = $before->fetch() ?: [];
      db()->prepare('UPDATE exams SET exam_name=?,exam_code=?,duration_minutes=?,max_attempts=?,start_time=?,end_time=?,total_marks=?,instructions=?,violation_config=?,force_fullscreen=?,max_violations=? WHERE id=?')
        ->execute([...$d,$vcfgJson,$forceFs,$maxV,$id]);
      $payload = ['table' => 'exams', 'before' => array_slice($beforeRow, 0, 8), 'after' => array_merge(['id' => $id], array_combine(['exam_name','exam_code','duration_minutes','max_attempts','start_time','end_time','total_marks','instructions'], $d)), 'violation_config' => $vcfg, 'force_fullscreen' => $forceFs, 'max_violations' => $maxV];
      log_admin_activity('exam_update', 'Updated exam ' . trim($_POST['exam_name']) . ' (' . $exam_code . ')', $me, 'admin/exams.php', $payload);
    } else {
      db()->prepare('INSERT INTO exams (exam_name,exam_code,duration_minutes,max_attempts,start_time,end_time,total_marks,instructions,violation_config,force_fullscreen,max_violations,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([...$d,$vcfgJson,$forceFs,$maxV,$me['id']]);
      $newId = (int)db()->lastInsertId();
      $payload = ['table' => 'exams', 'after' => array_merge(['id' => $newId], array_combine(['exam_name','exam_code','duration_minutes','max_attempts','start_time','end_time','total_marks','instructions'], $d)), 'violation_config' => $vcfg];
      log_admin_activity('exam_add', 'Created exam ' . trim($_POST['exam_name']) . ' (' . $exam_code . ')', $me, 'admin/exams.php', $payload);
    }
    flash('Saved','success');
  } elseif ($a==='delete') {
    $eidDelete = (int)$_POST['id'];
    $src = db()->prepare('SELECT * FROM exams WHERE id=? AND deleted_at IS NULL');
    $src->execute([$eidDelete]);
    $examRow = $src->fetch();
    if (!$examRow) { flash('Exam not found or already deleted','error'); redirect(url('admin/exams.php')); }
    // Ownership check for non-super
    if (!$me['is_super'] && (int)$examRow['created_by'] !== (int)$me['id']) {
      flash('You can delete only exams you created.','error'); redirect(url('admin/exams.php'));
    }
    if (!can('exams','delete',$me)) { flash('Permission denied: delete exams.','error'); redirect(url('admin/exams.php')); }
    // Soft delete (super-admin can hard-delete from Trash page)
    soft_delete('exams', $eidDelete, $me);
    $payload = ['table' => 'exams', 'before' => array_slice($examRow, 0, 9), 'soft_delete' => true];
    log_admin_activity('exam_delete', 'Soft-deleted exam ' . ($examRow['exam_name'] ?? ('#' . $eidDelete)) . (($examRow['exam_code'] ?? '') !== '' ? ' (' . $examRow['exam_code'] . ')' : ''), $me, 'admin/exams.php', $payload);
    flash('Moved to Trash. Super admin can restore or permanently delete.','success');
  }
  elseif ($a==='dup') {
    if (!can('exams','create',$me)) { flash('Permission denied: create exams.','error'); redirect(url('admin/exams.php')); }
    $src = db()->prepare('SELECT * FROM exams WHERE id=? AND deleted_at IS NULL'); $src->execute([(int)$_POST['id']]); $s = $src->fetch();
    if ($s) {
      db()->prepare('INSERT INTO exams (exam_name,duration_minutes,max_attempts,start_time,end_time,total_marks,instructions,created_by) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$s['exam_name'].' (Copy)',$s['duration_minutes'],$s['max_attempts'],$s['start_time'],$s['end_time'],$s['total_marks'],$s['instructions'],$me['id']]);
      $nid = db()->lastInsertId();
      $qs = db()->prepare('SELECT * FROM questions WHERE exam_id=? AND deleted_at IS NULL'); $qs->execute([$s['id']]);
      foreach ($qs->fetchAll() as $q) {
        db()->prepare('INSERT INTO questions (exam_id,question_type,question_text,question_text_hi,correct_text,correct_text_hi,correct_numeric,correct_bool,marks,negative_marks) VALUES (?,?,?,?,?,?,?,?,?,?)')
          ->execute([$nid,$q['question_type'],$q['question_text'],$q['question_text_hi'] ?? null,$q['correct_text'],$q['correct_text_hi'] ?? null,$q['correct_numeric'],$q['correct_bool'],$q['marks'],$q['negative_marks']]);
        $nqid = db()->lastInsertId();
        $oo = db()->prepare('SELECT * FROM question_options WHERE question_id=? AND deleted_at IS NULL'); $oo->execute([$q['id']]);
        foreach ($oo->fetchAll() as $o) db()->prepare('INSERT INTO question_options (question_id,opt_order,opt_text,opt_text_hi,is_correct) VALUES (?,?,?,?,?)')->execute([$nqid,$o['opt_order'],$o['opt_text'],$o['opt_text_hi'] ?? null,$o['is_correct']]);
      }
      $payload = ['table' => 'exams', 'after' => ['id' => $nid, 'source_exam_id' => (int)$_POST['id']]];
      log_admin_activity('exam_duplicate', 'Duplicated exam id ' . (int)$_POST['id'], $me, 'admin/exams.php', $payload);
      flash('Duplicated','success');
    }
  }
  elseif ($a === 'grant_access') {
    if (empty($me['is_super'])) { flash('Only super admin can grant exam access','error'); redirect(url('admin/exams.php')); }
    $eid = (int)$_POST['exam_id']; $aid = (int)$_POST['admin_id']; $lvl = in_array($_POST['access_level'] ?? '', ['view','edit','full']) ? $_POST['access_level'] : 'view';
    if ($aid && $eid) {
      db()->prepare('INSERT INTO exam_admin_access (exam_id,admin_id,access_level,granted_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE access_level=VALUES(access_level), granted_by=VALUES(granted_by), granted_at=NOW()')
        ->execute([$eid, $aid, $lvl, (int)$me['id']]);
      log_admin_activity('exam_access_grant', "Granted $lvl access on exam id $eid to admin id $aid", $me, 'admin/exams.php', ['exam_id'=>$eid,'admin_id'=>$aid,'access_level'=>$lvl]);
      flash('Access granted','success');
    }
  }
  elseif ($a === 'revoke_access') {
    if (empty($me['is_super'])) { flash('Only super admin can revoke access','error'); redirect(url('admin/exams.php')); }
    $gid = (int)$_POST['grant_id'];
    db()->prepare('DELETE FROM exam_admin_access WHERE id=?')->execute([$gid]);
    log_admin_activity('exam_access_revoke', "Revoked exam access grant id $gid", $me, 'admin/exams.php', ['grant_id'=>$gid]);
    flash('Access revoked','success');
  }
  redirect(url('admin/exams.php'));
}
require __DIR__ . '/_shell_top.php';
$q = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? 'active_upcoming';
// Ownership filter: regular admins only see their own exams unless super or view_all granted
[$ownWhere, $ownParams] = ownership_sql_clause('exams', 'e', $me);

// Also include exams granted via exam_admin_access (when restricted)
if (empty($me['is_super']) && !can_view_all('exams', $me)) {
  $accessibleIds = accessible_exam_ids($me);
  if ($accessibleIds) {
    $in = implode(',', array_map('intval', $accessibleIds));
    $ownWhere = " AND e.id IN ($in)";
    $ownParams = [];
  }
}

$sql = 'SELECT e.*, (SELECT COUNT(*) FROM questions WHERE exam_id=e.id AND deleted_at IS NULL) q, creator.name AS creator_name, creator.email AS creator_email FROM exams e LEFT JOIN users creator ON creator.id=e.created_by WHERE e.deleted_at IS NULL' . $ownWhere;
$params = $ownParams;
if ($q !== '') {
  $sql .= ' AND (e.exam_name LIKE ? OR e.exam_code LIKE ?)';
  $params[] = "%$q%"; $params[] = "%$q%";
}
if ($status_filter === 'active')   $sql .= ' AND NOW() BETWEEN e.start_time AND e.end_time';
elseif ($status_filter === 'upcoming') $sql .= ' AND NOW() < e.start_time';
elseif ($status_filter === 'closed')   $sql .= ' AND NOW() > e.end_time';
elseif ($status_filter === 'active_upcoming') $sql .= ' AND NOW() <= e.end_time';
$sql .= ' ORDER BY start_time DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// List of admins for super-admin grant UI
$adminsList = [];
if (!empty($me['is_super'])) {
  $adminsList = db()->query("SELECT id, name, email FROM users WHERE role='admin' AND is_super=0 AND deleted_at IS NULL ORDER BY name")->fetchAll();
}
?>
<div class="d-flex justify-content-between mb-3 gap-2 flex-wrap align-items-center">
  <form class="d-flex gap-2 align-items-center">
    <input name="q" value="<?= h($q) ?>" class="form-control" style="width:260px" placeholder="Search exam name or code" data-testid="exams-search-input">
    <select name="status" class="form-select" style="width:180px" onchange="this.form.submit()">
      <option value="active_upcoming" <?= $status_filter==='active_upcoming'?'selected':'' ?>>Active + Upcoming</option>
      <option value="active" <?= $status_filter==='active'?'selected':'' ?>>Active only</option>
      <option value="upcoming" <?= $status_filter==='upcoming'?'selected':'' ?>>Upcoming only</option>
      <option value="closed" <?= $status_filter==='closed'?'selected':'' ?>>Closed / Completed</option>
      <option value="all" <?= $status_filter==='all'?'selected':'' ?>>All</option>
    </select>
  </form>
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <?php if (!$me['is_super'] && !can_view_all('exams',$me)): ?>
      <span class="badge bg-light text-dark align-self-center border"><i class="fas fa-eye me-1"></i>Own + granted exams only</span>
    <?php elseif (!$me['is_super']): ?>
      <span class="badge bg-success align-self-center"><i class="fas fa-unlock me-1"></i>View-all permission granted</span>
    <?php endif; ?>
    <?php if (can('exams','create',$me)): ?>
      <button class="btn btn-navy" type="button" onclick="openCreateExamModal()"><i class="fas fa-plus me-1"></i>Create Exam</button>
    <?php endif; ?>
  </div>
</div>
<div class="row g-3">
<?php foreach ($rows as $e): $st = exam_status($e); $mineOrSuper = $me['is_super'] || (int)$e['created_by'] === (int)$me['id']; ?>
  <div class="col-md-6 col-lg-4"><div class="exam-card" data-exam-id="<?= (int)$e['id'] ?>" data-exam-code="<?= h($e['exam_code'] ?? '') ?>">
    <div class="d-flex justify-content-between align-items-start"><h5 class="fw-bold mb-0"><?= h($e['exam_name']) ?></h5><span class="pill status-<?= $st ?>"><?= $st ?></span></div>
    <div class="small text-secondary mt-2">Code: <b><?= h($e['exam_code'] ?? '') ?></b> · Duration: <b><?= $e['duration_minutes'] ?>min</b> · Attempts: <b><?= $e['max_attempts'] ?></b> · Qs: <b><?= $e['q'] ?></b></div>
    <div class="small text-muted"><?= fmt_dt($e['start_time']) ?> → <?= fmt_dt($e['end_time']) ?></div>
    <div class="small mt-2" style="background:#f8fafc; border-left:3px solid var(--navy); padding:6px 10px">
      <i class="fas fa-user-shield me-1 text-muted"></i>
      <b>Hosted by:</b> <?= h($e['creator_name'] ?? '—') ?>
      <?php if (!empty($e['creator_email'])): ?><span class="text-muted">(<?= h($e['creator_email']) ?>)</span><?php endif; ?>
      <br><i class="far fa-clock me-1 text-muted"></i><b>Created:</b> <?= fmt_dt($e['created_at']) ?>
    </div>
    <div class="mt-3 d-flex gap-1 flex-wrap">
      <?php if ($mineOrSuper || can('questions','edit',$me)): ?>
        <a href="<?= url('admin/questions.php?exam_id='.$e['id']) ?>" class="btn btn-sm btn-success"><i class="fas fa-list me-1"></i>Questions</a>
      <?php endif; ?>
      <a href="<?= url('admin/monitor-exam.php?exam_id='.$e['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-satellite-dish me-1"></i>Monitor</a>
      <?php if ($mineOrSuper && can('exams','edit',$me)): ?>
        <button class="btn btn-sm btn-outline-secondary" data-can-proctor="1" onclick='fillForm(<?= json_encode($e) ?>)' data-bs-toggle="modal" data-bs-target="#ef">Edit</button>
      <?php else:
        // Show "Edit" if admin has 'edit' or 'full' grant on this specific exam
        $accLvl = exam_access_for((int)$e['id'], $me);
        if (in_array($accLvl, ['edit','full'], true)): ?>
        <button class="btn btn-sm btn-outline-secondary" data-can-proctor="<?= $accLvl==='full'?1:1 ?>" onclick='fillForm(<?= json_encode($e) ?>)' data-bs-toggle="modal" data-bs-target="#ef">Edit</button>
      <?php endif; endif; ?>
      <?php if (can('exams','create',$me)): ?>
        <form method="post" class="d-inline"><?= csrf_input() ?><input type="hidden" name="action" value="dup"><input type="hidden" name="id" value="<?= $e['id'] ?>"><button class="btn btn-sm btn-outline-secondary">Duplicate</button></form>
      <?php endif; ?>
      <?php if ($mineOrSuper && can('exams','delete',$me)): ?>
        <form method="post" class="d-inline" onsubmit="return confirm('Move this exam to Trash? Super admin can restore later.')"><?= csrf_input() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $e['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
      <?php endif; ?>
    </div>
  </div></div>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="col-12"><div class="exam-card text-center text-muted py-5">No exams yet. Create one.</div></div><?php endif; ?>
</div>

<div class="modal fade" id="ef"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post" id="ef-form"><?= csrf_input() ?>
  <input type="hidden" name="action" value="save"><input type="hidden" name="id" id="ef-id" value="">
  <div class="modal-header"><h5 class="modal-title" id="ef-title">Create Exam</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div id="ef-warn" class="alert alert-danger py-2 small d-none"></div>
    <ul class="nav nav-tabs mb-3" role="tablist">
      <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-basic">Basic Details</a></li>
      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-proctor"><i class="fas fa-shield-halved me-1"></i>Proctor &amp; Violation Controls</a></li>
    </ul>
    <div class="tab-content">
      <div class="tab-pane fade show active" id="tab-basic">
        <label class="form-label-xs">Exam Name</label><input id="ef-name" name="exam_name" class="form-control mb-2" required>
        <label class="form-label-xs">Exam Code <span class="text-muted">(unique)</span></label><input id="ef-code" name="exam_code" class="form-control mb-2" placeholder="e.g. EXAM2026_A" required>
        <div class="row g-2"><div class="col"><label class="form-label-xs">Duration (min)</label><input id="ef-dur" name="duration_minutes" type="number" min="1" class="form-control" required></div>
        <div class="col"><label class="form-label-xs">Max Attempts</label><input id="ef-att" name="max_attempts" type="number" min="1" value="1" class="form-control" required></div></div>
        <div class="row g-2 mt-1"><div class="col"><label class="form-label-xs">Start</label><input id="ef-st" name="start_time" type="datetime-local" class="form-control" required></div>
        <div class="col"><label class="form-label-xs">End</label><input id="ef-en" name="end_time" type="datetime-local" class="form-control" required></div></div>
        <label class="form-label-xs mt-2">Total Marks (optional)</label><input id="ef-tot" name="total_marks" type="number" class="form-control mb-2">
        <label class="form-label-xs">Instructions</label><textarea id="ef-ins" name="instructions" class="form-control" rows="3"></textarea>
      </div>
      <div class="tab-pane fade" id="tab-proctor">
        <div id="proctor-readonly-banner" class="alert alert-warning small py-2 mb-3 d-none">
          <i class="fas fa-lock me-1"></i>You have view-only access on this exam. Settings below are visible but cannot be changed.
        </div>
        <fieldset id="proctor-fieldset" class="border rounded p-3">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="ef-ffs" name="force_fullscreen" value="1"><label class="form-check-label fw-bold" for="ef-ffs"><i class="fas fa-expand me-1"></i>Force Fullscreen</label><div class="small text-muted">Block exam until fullscreen is active; auto-return on exit.</div></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-camera" name="viol_camera" value="1"><label class="form-check-label fw-bold" for="viol-camera"><i class="fas fa-video me-1"></i>Camera / Webcam proctoring</label></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-tab_switch" name="viol_tab_switch" value="1"><label class="form-check-label fw-bold" for="viol-tab_switch"><i class="fas fa-window-restore me-1"></i>Tab / window switch detection</label></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-window_blur" name="viol_window_blur" value="1"><label class="form-check-label fw-bold" for="viol-window_blur"><i class="fas fa-eye-slash me-1"></i>Window blur / focus loss</label></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-right_click" name="viol_right_click" value="1"><label class="form-check-label fw-bold" for="viol-right_click"><i class="fas fa-computer-mouse me-1"></i>Right-click block</label></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-copy_paste" name="viol_copy_paste" value="1"><label class="form-check-label fw-bold" for="viol-copy_paste"><i class="fas fa-copy me-1"></i>Copy / Paste block</label></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-copy_text_select" name="viol_copy_text_select" value="1"><label class="form-check-label" for="viol-copy_text_select"><i class="fas fa-i-cursor me-1"></i>Disable text selection</label></div>
            </div>
            <div class="col-md-6">
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-keyboard_shortcuts" name="viol_keyboard_shortcuts" value="1"><label class="form-check-label fw-bold" for="viol-keyboard_shortcuts"><i class="fas fa-keyboard me-1"></i>Keyboard shortcuts block (Ctrl+U/P/S/R, Ctrl+W, F11, Esc)</label></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-escape_f11_block" name="viol_escape_f11_block" value="1"><label class="form-check-label fw-bold" for="viol-escape_f11_block"><i class="fas fa-square-xmark me-1"></i>Block Escape &amp; F11</label></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-devtools_block" name="viol_devtools_block" value="1"><label class="form-check-label fw-bold" for="viol-devtools_block"><i class="fas fa-code me-1"></i>Dev Tools block (F12 / Ctrl+Shift+I)</label></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-fullscreen_force" name="viol_fullscreen_force" value="1"><label class="form-check-label fw-bold" for="viol-fullscreen_force"><i class="fas fa-up-right-and-down-left-from-center me-1"></i>Auto re-enter fullscreen on exit</label></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-fullscreen_overlay" name="viol_fullscreen_overlay" value="1"><label class="form-check-label fw-bold" for="viol-fullscreen_overlay"><i class="fas fa-shield-halved me-1"></i>Blocking overlay on fullscreen exit</label><div class="small text-muted">Show a full-screen lock overlay while returning to fullscreen.</div></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-windows_key_block" name="viol_windows_key_block" value="1"><label class="form-check-label fw-bold" for="viol-windows_key_block"><i class="fas fa-square me-1"></i>Block Windows key</label><div class="small text-muted">Prevent Windows/Super key presses during exam.</div></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-screenshot_block" name="viol_screenshot_block" value="1"><label class="form-check-label fw-bold" for="viol-screenshot_block"><i class="fas fa-camera me-1"></i>Block screenshot keys</label><div class="small text-muted">Block Print Screen, Shift+PrtScr, Mac Cmd+Shift+3/4/5, Linux screenshot combos.</div></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-mac_shortcuts_block" name="viol_mac_shortcuts_block" value="1"><label class="form-check-label fw-bold" for="viol-mac_shortcuts_block"><i class="fab fa-apple me-1"></i>Block macOS shortcuts</label><div class="small text-muted">Hard-blocks Cmd+Tab, Cmd+Q, Cmd+H, Cmd+M, Cmd+Space (Spotlight), Cmd+W, Cmd+N, Cmd+T, Cmd+`.</div></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-alt_shortcuts_block" name="viol_alt_shortcuts_block" value="1"><label class="form-check-label fw-bold" for="viol-alt_shortcuts_block"><i class="fas fa-keyboard me-1"></i>Block Alt-key shortcuts</label><div class="small text-muted">Alt+Tab, Alt+F4, Alt+Space, Alt+Left/Right (browser navigation).</div></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-all_function_keys_block" name="viol_all_function_keys_block" value="1"><label class="form-check-label fw-bold" for="viol-all_function_keys_block"><i class="fas fa-grip me-1"></i>Block all function keys (F1–F12)</label><div class="small text-muted">Hard-blocks every F-key regardless of modifier.</div></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-extension_overlay_block" name="viol_extension_overlay_block" value="1"><label class="form-check-label fw-bold" for="viol-extension_overlay_block"><i class="fas fa-puzzle-piece me-1"></i>Block browser extension / AI overlays</label><div class="small text-muted">MutationObserver removes injected iframes, AI side-panels and extension UI overlays in real time.</div></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-clipboard_api_block" name="viol_clipboard_api_block" value="1"><label class="form-check-label fw-bold" for="viol-clipboard_api_block"><i class="fas fa-clipboard me-1"></i>Block clipboard API &amp; drag-drop</label><div class="small text-muted">Disables navigator.clipboard, drag-drop and cut events.</div></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-screen_sharing_block" name="viol_screen_sharing_block" value="1"><label class="form-check-label fw-bold" for="viol-screen_sharing_block"><i class="fas fa-share-from-square me-1"></i>Screen sharing detection</label><div class="small text-muted">Detects active screen-sharing (getDisplayMedia in use, mirrored displays).</div></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-remote_access_block" name="viol_remote_access_block" value="1"><label class="form-check-label fw-bold" for="viol-remote_access_block"><i class="fas fa-network-wired me-1"></i>Remote access detection</label><div class="small text-muted">Heuristics for RDP / AnyDesk / TeamViewer (pointer-latency, DPI, colour-depth).</div></div>
              <hr class="my-2">
              <div class="small text-muted mb-2">Future-proof (experimental)</div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-second_display" name="viol_second_display" value="1"><label class="form-check-label" for="viol-second_display"><i class="fas fa-desktop me-1"></i>Second display detection</label></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-screen_recording" name="viol_screen_recording" value="1"><label class="form-check-label" for="viol-screen_recording"><i class="fas fa-record-vinyl me-1"></i>Screen-recording detection</label></div>
              <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="viol-virtual_machine" name="viol_virtual_machine" value="1"><label class="form-check-label" for="viol-virtual_machine"><i class="fas fa-cube me-1"></i>Virtual machine heuristic</label></div>
            </div>
          </div>
          <hr>
          <label class="form-label-xs">Max Violations (auto-submit threshold)</label>
          <input id="ef-maxv" name="max_violations" type="number" min="1" max="100" value="5" class="form-control" style="max-width:160px">
          <div class="small text-muted mt-1">Student is auto-submitted once they cross this threshold.</div>
        </fieldset>
      </div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-navy">Save</button></div>
</form></div></div></div>

<?php if (!empty($me['is_super'])): ?>
<div class="modal fade" id="grantModal"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="fas fa-key me-1 text-warning"></i>Grant Exam Access — <span id="g-exam-name"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="alert alert-info small py-2"><i class="fas fa-circle-info me-1"></i>Granting access lets the chosen admin view/edit this exam and <b>see the students registered for it</b>, without giving them access to every exam.</div>
    <form method="post" class="row g-2 align-items-end"><?= csrf_input() ?>
      <input type="hidden" name="action" value="grant_access">
      <input type="hidden" name="exam_id" id="g-exam-id">
      <div class="col-md-5">
        <label class="form-label-xs">Admin</label>
        <select name="admin_id" class="form-select" required>
          <option value="">Select admin…</option>
          <?php foreach ($adminsList as $ad): ?>
            <option value="<?= $ad['id'] ?>"><?= h($ad['name']) ?> (<?= h($ad['email']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label-xs">Access Level</label>
        <select name="access_level" class="form-select" required>
          <option value="view">i. View-only</option>
          <option value="edit">ii. View + Edit + Monitor + Students</option>
          <option value="full">iii. Full control (edit, delete, monitor, results)</option>
        </select>
      </div>
      <div class="col-md-3"><button class="btn btn-warning w-100"><i class="fas fa-plus me-1"></i>Grant</button></div>
    </form>
    <hr>
    <h6 class="fw-bold mb-2"><i class="fas fa-users me-1"></i>Current grants for this exam</h6>
    <div id="g-grants-list"><div class="text-muted small">Loading…</div></div>
  </div>
</div></div></div>
<?php endif; ?>

<script>
function openCreateExamModal() {
  // Reset the form for new exam
  document.getElementById('ef-id').value = '';
  document.getElementById('ef-name').value = '';
  document.getElementById('ef-code').value = '';
  document.getElementById('ef-dur').value = 30;
  document.getElementById('ef-att').value = 1;
  document.getElementById('ef-tot').value = '';
  document.getElementById('ef-ins').value = '';
  document.getElementById('ef-st').value = '';
  document.getElementById('ef-en').value = '';
  document.getElementById('ef-title').textContent = 'Create Exam';
  
  // Show the modal
  if (typeof bootstrap !== 'undefined') {
    const modalEl = document.getElementById('ef');
    if (modalEl) {
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    } else {
      alert('Modal element not found. Please refresh the page.');
    }
  } else {
    alert('Bootstrap library not loaded. Please refresh the page.');
  }
}

function fillForm(e){ e=e||{}; document.getElementById('ef-id').value=e.id||'';
  document.getElementById('ef-name').value=e.exam_name||''; document.getElementById('ef-dur').value=e.duration_minutes||30;
  document.getElementById('ef-code').value=e.exam_code||'';
  document.getElementById('ef-att').value=e.max_attempts||1; document.getElementById('ef-tot').value=e.total_marks||'';
  document.getElementById('ef-ins').value=e.instructions||''; document.getElementById('ef-title').textContent=e.id?'Edit Exam':'Create Exam';
  document.getElementById('ef-st').value=e.start_time?e.start_time.replace(' ','T').slice(0,16):'';
  document.getElementById('ef-en').value=e.end_time?e.end_time.replace(' ','T').slice(0,16):'';
  // Proctor defaults — all-ON for new, or whatever was stored for existing exam
  document.getElementById('ef-ffs').checked = (e.force_fullscreen === undefined || e.force_fullscreen === null) ? true : !!parseInt(e.force_fullscreen);
  document.getElementById('ef-maxv').value = e.max_violations || 5;
  let cfg = {};
  try { cfg = e.violation_config ? JSON.parse(e.violation_config) : {}; } catch(err) { cfg = {}; }
  const defaults = { camera:1, tab_switch:1, window_blur:1, right_click:1, copy_paste:1, fullscreen_force:1, fullscreen_overlay:1, keyboard_shortcuts:1, escape_f11_block:1, devtools_block:1, windows_key_block:1, screenshot_block:1, mac_shortcuts_block:1, alt_shortcuts_block:1, all_function_keys_block:1, extension_overlay_block:1, clipboard_api_block:1, screen_sharing_block:1, remote_access_block:1, copy_text_select:0, second_display:0, screen_recording:0, virtual_machine:0 };
  Object.keys(defaults).forEach(k => {
    const el = document.getElementById('viol-'+k); if (!el) return;
    const v = (cfg[k] !== undefined) ? cfg[k] : (e.id ? 0 : defaults[k]);  // existing row → use stored; new row → use defaults
    el.checked = !!parseInt(v);
  });
  // Decide whether the proctor fieldset is editable.
  // Always-enabled for new exam (creator owns it). For existing rows, use the
  // data-can-proctor attribute of the trigger button (set by PHP based on access level).
  const triggerBtn = (window.event && (window.event.target.closest('button'))) || null;
  const canProctor = !e.id || (triggerBtn && triggerBtn.getAttribute('data-can-proctor') === '1');
  const fieldset = document.getElementById('proctor-fieldset');
  const banner = document.getElementById('proctor-readonly-banner');
  if (fieldset) fieldset.disabled = !canProctor;
  if (banner) banner.classList.toggle('d-none', canProctor);
  const warn = document.getElementById('ef-warn'); warn.classList.add('d-none'); warn.textContent = '';
}

function showExamWarn(msg) {
  const warn = document.getElementById('ef-warn');
  warn.textContent = msg;
  warn.classList.remove('d-none');
}

function codeExists(code, currentId) {
  const normalized = (code || '').trim().toLowerCase();
  if (!normalized) return false;
  return Array.from(document.querySelectorAll('.exam-card')).some(card => {
    const cardId = String(card.dataset.examId || '');
    const cardCode = String(card.dataset.examCode || '').trim().toLowerCase();
    return cardCode === normalized && cardId !== String(currentId || '');
  });
}

// Safely attach event listeners after DOM is loaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', attachEventListeners);
} else {
  attachEventListeners();
}

function attachEventListeners() {
  try {
    const form = document.getElementById('ef-form');
    if (form) {
      form.addEventListener('submit', (e) => {
        const code = document.getElementById('ef-code').value.trim();
        const id = document.getElementById('ef-id').value;
        if (!code) {
          e.preventDefault();
          showExamWarn('Exam code is required. Please assign a new code before saving.');
          document.getElementById('ef-code').focus();
          return;
        }
        if (codeExists(code, id)) {
          e.preventDefault();
          showExamWarn('This exam code already assigned to another exam. Please give another code.');
          document.getElementById('ef-code').focus();
        }
      });
    }
  } catch(err) { console.error('Error attaching form listener:', err); }

  // Grant Access modal wiring (super-admin only)
  try {
    document.querySelectorAll('.grant-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const eid = btn.dataset.examId;
        document.getElementById('g-exam-id').value = eid;
        document.getElementById('g-exam-name').textContent = btn.dataset.examName;
        const listEl = document.getElementById('g-grants-list');
        listEl.innerHTML = '<div class="text-muted small">Loading…</div>';
        try {
          const r = await fetch('<?= url('api/exam-grants.php') ?>?exam_id=' + eid, {credentials:'same-origin'});
          const d = await r.json();
          if (!d.ok || !d.grants.length) {
            listEl.innerHTML = '<div class="text-muted small py-2"><i class="fas fa-inbox me-1"></i>No access grants yet — this exam is visible only to its creator and super admin.</div>';
          } else {
            const csrf = '<?= csrf() ?>';
            listEl.innerHTML = '<table class="table table-sm align-middle mb-0"><thead><tr><th>Admin</th><th>Level</th><th>Granted</th><th></th></tr></thead><tbody>' +
              d.grants.map(g => `<tr>
                <td><b>${g.admin_name}</b><br><small class="text-muted">${g.admin_email}</small></td>
                <td><span class="badge bg-${g.access_level==='full'?'success':(g.access_level==='edit'?'info':'secondary')}">${g.access_level}</span></td>
                <td class="small text-muted">${g.granted_at}</td>
                <td class="text-end"><form method="post" onsubmit="return confirm('Revoke this access?')" style="display:inline"><input type="hidden" name="_csrf" value="${csrf}"><input type="hidden" name="action" value="revoke_access"><input type="hidden" name="grant_id" value="${g.id}"><button class="btn btn-sm btn-outline-danger">Revoke</button></form></td>
              </tr>`).join('') + '</tbody></table>';
          }
        } catch(err) { listEl.innerHTML = '<div class="alert alert-danger small py-2">Failed to load grants</div>'; }
        new bootstrap.Modal(document.getElementById('grantModal')).show();
      });
    });
  } catch(err) { console.error('Error attaching grant buttons:', err); }

  try {
    const codeInput = document.getElementById('ef-code');
    if (codeInput) {
      codeInput.addEventListener('input', () => {
        const code = document.getElementById('ef-code').value.trim();
        const id = document.getElementById('ef-id').value;
        const warn = document.getElementById('ef-warn');
        if (!code) {
          showExamWarn('Exam code is required. Please assign a new code before saving.');
          return;
        }
        if (codeExists(code, id)) {
          showExamWarn('This exam code already assigned to another exam. Please give another code.');
          return;
        }
        warn.classList.add('d-none');
        warn.textContent = '';
      });
    }
  } catch(err) { console.error('Error attaching code input listener:', err); }
}
</script>
<?php require __DIR__ . '/_shell_bottom.php'; ?>
