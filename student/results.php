<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/lang.php';
$u = require_login('student');
$stmt = db()->prepare('SELECT a.*, e.exam_name FROM attempts a JOIN exams e ON e.id=a.exam_id WHERE a.user_id=? AND a.status="submitted" ORDER BY a.submitted_at DESC');
$stmt->execute([$u['id']]);
$rows = $stmt->fetchAll();
$PAGE_TITLE = t('rs_title');
require __DIR__ . '/../includes/header.php';
?>
<header style="background:var(--navy); color:#fff; border-bottom: 2px solid var(--saffron); padding: 14px 0">
  <div class="container d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
      <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" style="width:40px; height:40px; background:#fff; padding:5px; border-radius:3px; object-fit:contain" alt="BEL">
      <span class="fw-bold"><i class="fas fa-trophy me-2"></i><?= t('rs_title') ?> · BEL Kotdwar</span>
    </div>
    <a href="<?= url('student/dashboard.php') ?>" class="btn btn-sm btn-outline-light"><i class="fas fa-arrow-left"></i> <?= t('sl_back') ?></a>
  </div>
</header>
<main class="container py-4">
  <table class="data-table"><thead><tr>
    <th><?= t('rs_exam') ?></th><th><?= t('rs_attempt') ?></th><th><?= t('rs_score') ?></th><th><?= t('rs_submitted') ?></th>
  </tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td class="fw-medium"><?= h($r['exam_name']) ?></td><td>#<?= (int)$r['attempt_no'] ?></td>
        <td class="fw-bold text-success"><?= $r['score'] ?> / <?= $r['total'] ?></td>
        <td class="small text-muted"><?= fmt_dt($r['submitted_at']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="text-center text-muted py-5"><?= t('rs_none') ?></td></tr><?php endif; ?>
  </tbody></table>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
