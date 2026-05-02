<?php $ADMIN_TITLE = 'Results & Attempt History';
require_once __DIR__ . '/../includes/helpers.php'; require_login('admin');
require __DIR__ . '/_shell_top.php';

$q = trim($_GET['q'] ?? '');
$examId = (int)($_GET['exam_id'] ?? 0);

// load exams for filter dropdown
$exams = db()->query('SELECT id, exam_name, exam_code FROM exams ORDER BY start_time DESC')->fetchAll();

 $sql = 'SELECT a.*, u.name AS sname, u.email AS semail, u.roll_number, u.dob, e.exam_name, e.exam_code FROM attempts a JOIN users u ON u.id=a.user_id JOIN exams e ON e.id=a.exam_id WHERE a.status="submitted"';
if ($q) {
  $qLike = db()->quote("%$q%");
  $sql .= " AND (u.name LIKE $qLike OR e.exam_name LIKE $qLike OR u.roll_number LIKE $qLike OR u.dob LIKE $qLike";
  if (is_numeric($q)) {
    $sql .= ' OR a.exam_id=' . (int)$q;
  }
  $sql .= ')';
}
if ($examId) $sql .= ' AND a.exam_id=' . (int)$examId;
$rows = db()->query($sql . ' ORDER BY a.submitted_at DESC')->fetchAll();
?>
<div class="d-flex justify-content-between mb-3 gap-2">
  <form class="d-flex gap-2">
    <input name="q" value="<?= h($q) ?>" class="form-control" style="width:320px" placeholder="Search by student or exam">
    <select name="exam_id" class="form-select" style="width:260px">
      <option value="">All exams</option>
      <?php foreach ($exams as $ex): ?>
        <option value="<?= (int)$ex['id'] ?>" <?= $examId === (int)$ex['id'] ? 'selected' : '' ?>><?= h($ex['exam_name']) ?> <?= $ex['exam_code'] ? '— ' . h($ex['exam_code']) : '' ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-secondary">Filter</button>
  </form>
  <div class="d-flex gap-2">
    <a href="<?= url('admin/export-results.php' . ($examId ? '?exam_id=' . $examId : '')) ?>" class="btn btn-success"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
  </div>
</div>
<table class="data-table"><thead><tr><th>Student</th><th>Roll</th><th>Exam</th><th>Attempt</th><th>Score</th><th>Submitted</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr><td class="fw-medium"><?= h($r['sname']) ?></td><td class="small"><?= h($r['roll_number']) ?></td><td><?= h($r['exam_name']) ?></td>
    <td>#<?= (int)$r['attempt_no'] ?></td><td class="fw-bold text-success"><?= $r['score'] ?> / <?= $r['total'] ?></td>
    <td class="small text-muted"><?= fmt_dt($r['submitted_at']) ?></td>
    <td class="text-end"><a href="<?= url('admin/attempt.php?id='.$r['id']) ?>" class="btn btn-sm btn-navy">Review</a></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No submissions yet</td></tr><?php endif; ?>
</tbody></table>
<?php require __DIR__ . '/_shell_bottom.php'; ?>
