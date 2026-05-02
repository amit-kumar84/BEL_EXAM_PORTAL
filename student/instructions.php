<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/lang.php';
$u = require_login('student');
$eid = (int)($_GET['exam_id'] ?? 0);
$stmt = db()->prepare('SELECT *, (SELECT COUNT(*) FROM questions WHERE exam_id=exams.id AND deleted_at IS NULL) AS qcount FROM exams WHERE id=?');
$stmt->execute([$eid]);
$exam = $stmt->fetch();
if (!$exam) { flash('Exam not found', 'error'); redirect(url('student/dashboard.php')); }
if (!is_exam_assigned((int)$u['id'], $eid)) { flash('This examination is not assigned to your account.', 'error'); redirect(url('student/dashboard.php')); }
$used = db()->prepare('SELECT COUNT(*) FROM attempts WHERE user_id=? AND exam_id=? AND status="submitted"');
$used->execute([$u['id'], $eid]);
$attempts_left = max(0, (int)$exam['max_attempts'] - (int)$used->fetchColumn());
$PAGE_TITLE = t('in_title');
require __DIR__ . '/../includes/header.php';
?>
<div class="tricolor"><span></span><span></span><span></span></div>
<header style="background:var(--navy); color:#fff; border-bottom: 2px solid var(--saffron); padding: 14px 0">
  <div class="container d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
      <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" style="width:40px; height:40px; background:#fff; padding:5px; border-radius:3px; object-fit:contain" alt="BEL">
      <span class="fw-bold">BEL Kotdwar · <?= t('in_title') ?></span>
    </div>
    <a href="<?= url('student/dashboard.php') ?>" class="btn btn-sm btn-outline-light"><i class="fas fa-arrow-left"></i> <?= t('sl_back') ?></a>
  </div>
</header>

<main class="container py-4" style="max-width:900px">
  <div class="exam-card">
    <div class="border-bottom pb-3 mb-4">
      <h2 class="fw-bold"><?= h($exam['exam_name']) ?></h2>
      <p class="text-secondary mb-0">Duration: <b><?= (int)$exam['duration_minutes'] ?> min</b> · Questions: <b><?= (int)$exam['qcount'] ?></b> · Attempts left: <b><?= $attempts_left ?></b></p>
    </div>
    <?php foreach ([
      ['1', 'Lockdown Mode (mandatory)', 'As soon as you click <b>Start Exam</b>, the browser will switch to <b>fullscreen</b>. Exiting fullscreen, switching tabs / applications, or right-click / copy / paste will be logged as violations. After '.MAX_VIOLATIONS.' violations the exam will <b>auto-submit</b>.'],
      ['2', 'Webcam Proctoring', 'Your webcam preview will be visible during the exam. Ensure your face is clearly visible.'],
      ['3', 'Question Navigation', 'Use the question palette to jump to any question, mark for review, or revisit. Green=answered, red=not answered, blue=marked, orange=answered & marked.'],
      ['4', 'Submission', 'Click <b>Submit Exam</b> when done. The exam will auto-submit when the timer reaches zero.'],
    ] as $s): ?>
      <div class="mb-3">
        <h5 class="d-flex align-items-center gap-2"><span style="width:28px; height:28px; background:var(--navy); color:#fff; border-radius:3px; display:inline-flex; align-items:center; justify-content:center; font-size:13px"><?= $s[0] ?></span><?= $s[1] ?></h5>
        <p class="text-secondary small mb-0"><?= $s[2] ?></p>
      </div>
    <?php endforeach; ?>
    <?php if (!empty($exam['instructions'])): ?>
      <div class="bg-light p-3 small border"><?= nl2br(h($exam['instructions'])) ?></div>
    <?php endif; ?>

    <form method="get" action="<?= url('student/take-exam.php') ?>" class="mt-4">
      <input type="hidden" name="exam_id" value="<?= $eid ?>">
      <div class="form-check mb-3">
        <input type="checkbox" class="form-check-input" id="agree" required>
        <label class="form-check-label small" for="agree"><?= t('in_agree') ?></label>
      </div>
      <button class="btn btn-success btn-lg"><i class="fas fa-shield-alt me-2"></i><?= t('in_begin') ?></button>
      <a href="<?= url('student/dashboard.php') ?>" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
    </form>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
