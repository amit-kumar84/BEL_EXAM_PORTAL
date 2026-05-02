<?php $ADMIN_TITLE = 'Trash / Deleted Records';
require_once __DIR__ . '/../includes/helpers.php';
$me = require_login('admin');
ensure_softdelete_and_permissions();

if (empty($me['is_super'])) {
  require __DIR__ . '/_shell_top.php';
  ?><div class="alert alert-danger">Only super admin can access the Trash. This section includes permanent delete operations.</div><?php
  require __DIR__ . '/_shell_bottom.php';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_check();
  $a = $_POST['action'] ?? '';
  $kind = $_POST['kind'] ?? '';
  $id = (int)($_POST['id'] ?? 0);
  $tableMap = [
    'exam'     => 'exams',
    'student'  => 'users',       // role=student
    'admin'    => 'users',       // role=admin
    'question' => 'questions',
  ];
  $table = $tableMap[$kind] ?? null;
  if (!$table || !$id) { flash('Invalid request','error'); redirect(url('admin/trash.php')); }

  $extra = '';
  if ($kind === 'student') $extra = " AND role='student'";
  if ($kind === 'admin')   $extra = " AND role='admin'";

  if ($a === 'restore') {
    $stmt = db()->prepare("UPDATE $table SET deleted_at=NULL, deleted_by=NULL, deleted_by_name=NULL, deleted_by_email=NULL WHERE id=? $extra");
    $stmt->execute([$id]);
    log_admin_activity($kind.'_restore', "Restored $kind id $id from Trash", $me, 'admin/trash.php', ['table'=>$table,'id'=>$id]);
    flash('Restored successfully','success');
  } elseif ($a === 'hard_delete') {
    // Permanent delete (super admin only, already enforced above)
    $snap = db()->prepare("SELECT * FROM $table WHERE id=? $extra");
    $snap->execute([$id]);
    $row = $snap->fetch() ?: [];
    $stmt = db()->prepare("DELETE FROM $table WHERE id=? $extra");
    $stmt->execute([$id]);
    log_admin_activity($kind.'_hard_delete', "Permanently deleted $kind id $id", $me, 'admin/trash.php', ['table'=>$table,'before'=>array_slice($row,0,14)]);
    flash('Permanently deleted','success');
  }
  redirect(url('admin/trash.php?tab='.urlencode($kind)));
}

require __DIR__ . '/_shell_top.php';
$tab = $_GET['tab'] ?? 'exam';

// Deleted exams
$delExams = db()->query("SELECT e.*, creator.name AS creator_name FROM exams e LEFT JOIN users creator ON creator.id=e.created_by WHERE e.deleted_at IS NOT NULL ORDER BY e.deleted_at DESC")->fetchAll();
$delStudents = db()->query("SELECT u.*, creator.name AS creator_name FROM users u LEFT JOIN users creator ON creator.id=u.created_by WHERE u.role='student' AND u.deleted_at IS NOT NULL ORDER BY u.deleted_at DESC")->fetchAll();
$delAdmins = db()->query("SELECT u.*, creator.name AS creator_name FROM users u LEFT JOIN users creator ON creator.id=u.created_by WHERE u.role='admin' AND u.deleted_at IS NOT NULL ORDER BY u.deleted_at DESC")->fetchAll();
$delQuestions = db()->query("SELECT q.*, e.exam_name FROM questions q LEFT JOIN exams e ON e.id=q.exam_id WHERE q.deleted_at IS NOT NULL ORDER BY q.deleted_at DESC")->fetchAll();

function rowActions($kind, $id) { ?>
  <div class="d-flex gap-1 justify-content-end">
    <form method="post" class="d-inline"><?= csrf_input() ?>
      <input type="hidden" name="action" value="restore">
      <input type="hidden" name="kind" value="<?= h($kind) ?>">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <button class="btn btn-sm btn-outline-success" title="Restore"><i class="fas fa-rotate-left me-1"></i>Restore</button>
    </form>
    <form method="post" class="d-inline" onsubmit="return confirm('Permanently delete? This cannot be undone.')"><?= csrf_input() ?>
      <input type="hidden" name="action" value="hard_delete">
      <input type="hidden" name="kind" value="<?= h($kind) ?>">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <button class="btn btn-sm btn-danger" title="Permanent delete"><i class="fas fa-trash me-1"></i>Delete Forever</button>
    </form>
  </div>
<?php }
?>
<style>
.trash-tbl tr { background:#fff0f0 !important; }
.trash-tbl tr td { color:#7f1d1d; }
.trash-tbl .deleter { font-size:11px; color:#991b1b; background:#fee2e2; padding:3px 8px; border-radius:3px; border:1px solid #fca5a5; display:inline-block; }
.tab-pill { padding:8px 16px; border:1px solid #cbd5e1; border-radius:3px; background:#fff; font-weight:600; color:#475569; text-decoration:none; margin-right:6px; font-size:13px; display:inline-flex; align-items:center; gap:6px; }
.tab-pill.active { background:var(--navy); color:#fff; border-color:var(--navy); }
.tab-pill .cnt { background:#dc2626; color:#fff; border-radius:50%; min-width:20px; height:20px; display:inline-flex; align-items:center; justify-content:center; font-size:10px; padding:0 4px; }
</style>
<div class="alert alert-warning mb-3">
  <i class="fas fa-triangle-exclamation me-1"></i>
  <b>Super Admin — Trash</b>. Deleted records are retained here until you <b>Restore</b> them (undo the deletion) or <b>Delete Forever</b> (permanent — cannot be undone). Deleter identity is recorded on every row.
</div>

<div class="mb-3">
  <a class="tab-pill <?= $tab==='exam'?'active':'' ?>" href="?tab=exam">
    <i class="fas fa-book-open"></i>Exams <span class="cnt"><?= count($delExams) ?></span></a>
  <a class="tab-pill <?= $tab==='student'?'active':'' ?>" href="?tab=student">
    <i class="fas fa-users"></i>Students <span class="cnt"><?= count($delStudents) ?></span></a>
  <a class="tab-pill <?= $tab==='admin'?'active':'' ?>" href="?tab=admin">
    <i class="fas fa-user-shield"></i>Admins <span class="cnt"><?= count($delAdmins) ?></span></a>
  <a class="tab-pill <?= $tab==='question'?'active':'' ?>" href="?tab=question">
    <i class="fas fa-circle-question"></i>Questions <span class="cnt"><?= count($delQuestions) ?></span></a>
</div>

<?php if ($tab === 'exam'): ?>
  <div class="bg-white border">
  <table class="data-table trash-tbl"><thead><tr><th>Exam</th><th>Code</th><th>Created By</th><th>Deleted By</th><th>Deleted At</th><th class="text-end">Actions</th></tr></thead><tbody>
  <?php foreach ($delExams as $e): ?>
    <tr>
      <td class="fw-bold"><?= h($e['exam_name']) ?></td>
      <td class="font-monospace"><?= h($e['exam_code']) ?></td>
      <td><?= h($e['creator_name'] ?? '—') ?><div class="small" style="opacity:.7"><?= fmt_dt($e['created_at']) ?></div></td>
      <td><span class="deleter"><i class="fas fa-user me-1"></i><?= h($e['deleted_by_name']) ?></span><div class="small" style="opacity:.8"><?= h($e['deleted_by_email']) ?></div></td>
      <td><?= fmt_dt($e['deleted_at']) ?></td>
      <td class="text-end"><?php rowActions('exam', $e['id']); ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$delExams): ?><tr><td colspan="6" class="text-center text-muted py-4" style="background:#fff !important;color:#64748b !important">No deleted exams</td></tr><?php endif; ?>
  </tbody></table></div>

<?php elseif ($tab === 'student'): ?>
  <div class="bg-white border">
  <table class="data-table trash-tbl"><thead><tr><th>Name</th><th>Roll</th><th>Email</th><th>Created By</th><th>Deleted By</th><th>Deleted At</th><th class="text-end">Actions</th></tr></thead><tbody>
  <?php foreach ($delStudents as $s): ?>
    <tr>
      <td class="fw-bold"><?= h($s['name']) ?></td>
      <td class="font-monospace"><?= h($s['roll_number']) ?></td>
      <td><?= h($s['email']) ?></td>
      <td><?= h($s['creator_name'] ?? '—') ?></td>
      <td><span class="deleter"><i class="fas fa-user me-1"></i><?= h($s['deleted_by_name']) ?></span><div class="small" style="opacity:.8"><?= h($s['deleted_by_email']) ?></div></td>
      <td><?= fmt_dt($s['deleted_at']) ?></td>
      <td class="text-end"><?php rowActions('student', $s['id']); ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$delStudents): ?><tr><td colspan="7" class="text-center text-muted py-4" style="background:#fff !important;color:#64748b !important">No deleted students</td></tr><?php endif; ?>
  </tbody></table></div>

<?php elseif ($tab === 'admin'): ?>
  <div class="bg-white border">
  <table class="data-table trash-tbl"><thead><tr><th>Name</th><th>Email</th><th>Deleted By</th><th>Deleted At</th><th class="text-end">Actions</th></tr></thead><tbody>
  <?php foreach ($delAdmins as $s): ?>
    <tr>
      <td class="fw-bold"><?= h($s['name']) ?></td>
      <td><?= h($s['email']) ?></td>
      <td><span class="deleter"><i class="fas fa-user me-1"></i><?= h($s['deleted_by_name']) ?></span><div class="small" style="opacity:.8"><?= h($s['deleted_by_email']) ?></div></td>
      <td><?= fmt_dt($s['deleted_at']) ?></td>
      <td class="text-end"><?php rowActions('admin', $s['id']); ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$delAdmins): ?><tr><td colspan="5" class="text-center text-muted py-4" style="background:#fff !important;color:#64748b !important">No deleted admins</td></tr><?php endif; ?>
  </tbody></table></div>

<?php elseif ($tab === 'question'): ?>
  <div class="bg-white border">
  <table class="data-table trash-tbl"><thead><tr><th>#</th><th>Question</th><th>Exam</th><th>Type</th><th>Deleted By</th><th>Deleted At</th><th class="text-end">Actions</th></tr></thead><tbody>
  <?php foreach ($delQuestions as $q): ?>
    <tr>
      <td><?= (int)$q['id'] ?></td>
      <td><?= h(mb_substr($q['question_text'], 0, 90)) ?><?= mb_strlen($q['question_text'])>90?'…':'' ?></td>
      <td><?= h($q['exam_name'] ?? '—') ?></td>
      <td><?= str_replace('_',' ',h($q['question_type'])) ?></td>
      <td><span class="deleter"><i class="fas fa-user me-1"></i><?= h($q['deleted_by_name']) ?></span><div class="small" style="opacity:.8"><?= h($q['deleted_by_email']) ?></div></td>
      <td><?= fmt_dt($q['deleted_at']) ?></td>
      <td class="text-end"><?php rowActions('question', $q['id']); ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$delQuestions): ?><tr><td colspan="7" class="text-center text-muted py-4" style="background:#fff !important;color:#64748b !important">No deleted questions</td></tr><?php endif; ?>
  </tbody></table></div>
<?php endif; ?>

<?php require __DIR__ . '/_shell_bottom.php'; ?>
