<?php
// admin/preview-question.php — renders a single question exactly as the student
// would see it during the exam, with a mini EN/हिं toggle. Offline-only.
require_once __DIR__ . '/../includes/helpers.php';
$me = require_login('admin');
ensure_bilingual_columns();
ensure_softdelete_and_permissions();

$qid = (int)($_GET['id'] ?? 0);
$q = db()->prepare('SELECT q.*, e.exam_name, e.created_by AS exam_owner FROM questions q JOIN exams e ON e.id=q.exam_id WHERE q.id=? AND q.deleted_at IS NULL');
$q->execute([$qid]);
$row = $q->fetch();
if (!$row) die('Question not found');
if (empty($me['is_super']) && (int)$row['exam_owner'] !== (int)$me['id']) die('Forbidden');

$opts = db()->prepare('SELECT * FROM question_options WHERE question_id=? AND deleted_at IS NULL ORDER BY opt_order');
$opts->execute([$qid]);
$options = $opts->fetchAll();

$QTYPE_EN = ['mcq'=>'Multiple Choice','multi_select'=>'Multi Select','true_false'=>'True / False','short_answer'=>'Short Answer','numeric'=>'Numeric'];
$QTYPE_HI = ['mcq'=>'बहुविकल्पीय','multi_select'=>'बहु-चयन','true_false'=>'सत्य / असत्य','short_answer'=>'संक्षिप्त उत्तर','numeric'=>'संख्यात्मक'];
$qHi = trim((string)($row['question_text_hi'] ?? ''));
$hasHi = $qHi !== '';
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Preview · <?= h($row['exam_name']) ?></title>
<link rel="stylesheet" href="<?= url('assets/lib/bootstrap/css/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= url('assets/lib/fontawesome/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
<style>
body { background:#f1f5f9; padding:20px; }
.preview-wrap { max-width:920px; margin:0 auto; background:#fff; border:1px solid #e2e8f0; border-radius:4px; overflow:hidden; }
.preview-top { background:var(--navy); color:#fff; padding:14px 20px; display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid var(--saffron); }
.lang-switch { display:inline-flex; gap:4px; background:#0f172a; border:1px solid #334155; border-radius:4px; padding:3px; }
.lang-switch button { border:0; background:transparent; color:#cbd5e1; font-weight:600; font-size:12px; padding:4px 12px; border-radius:3px; cursor:pointer; }
.lang-switch button.active { background:var(--saffron); color:#0f172a; }
.preview-body { padding:30px; }
[data-lang-text].lang-hidden, [data-q-lang].hidden, [data-opt-lang].hidden { display:none !important; }
.correct-marker { color:#16a34a; font-weight:700; margin-left:8px; background:#f0fdf4; border:1px solid #bbf7d0; padding:2px 8px; border-radius:3px; font-size:12px; }
.opt-line { display:block; padding:14px; border:1px solid #cbd5e1; margin-bottom:10px; border-radius:3px; }
.opt-line.correct { border-color:#16a34a; background:#f0fdf4; }
.hi-warn { display:inline-block; font-size:12px; color:#b45309; background:#fef3c7; padding:2px 8px; border-radius:3px; margin-left:6px; }
</style></head>
<body>
<div class="preview-wrap">
  <div class="preview-top">
    <div class="d-flex align-items-center gap-3">
      <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" style="width:40px; height:40px; background:#fff; padding:4px; border-radius:3px; object-fit:contain">
      <div>
        <div class="fw-bold"><?= h($row['exam_name']) ?></div>
        <div class="small" style="opacity:.75">Admin Preview · Student-Eye View</div>
      </div>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <div class="lang-switch">
        <button type="button" class="active" data-lang-btn="en" onclick="setLang('en')">EN</button>
        <button type="button" data-lang-btn="hi" onclick="setLang('hi')">हिं</button>
      </div>
      <a href="<?= url('admin/questions.php?exam_id='.(int)$row['exam_id']) ?>" class="btn btn-sm btn-outline-light"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
  </div>
  <div class="preview-body" data-lang="en">
    <div class="small text-uppercase text-muted mb-2" style="letter-spacing:.08em">
      <span data-lang-text="en">Question</span><span data-lang-text="hi" class="lang-hidden">प्रश्न</span> ·
      <span data-lang-text="en"><?= h($QTYPE_EN[$row['question_type']] ?? $row['question_type']) ?></span>
      <span data-lang-text="hi" class="lang-hidden"><?= h($QTYPE_HI[$row['question_type']] ?? $row['question_type']) ?></span>
      · <?= (float)$row['marks'] ?> <span data-lang-text="en">marks</span><span data-lang-text="hi" class="lang-hidden">अंक</span>
      <?php if ((float)$row['negative_marks']>0) echo ' · −'.(float)$row['negative_marks']; ?>
    </div>
    <h4 class="mb-4">
      <span data-q-lang="en"><?= nl2br(h($row['question_text'])) ?></span>
      <span data-q-lang="hi" class="hidden">
        <?php if ($hasHi): ?><?= nl2br(h($qHi)) ?>
        <?php else: ?><?= nl2br(h($row['question_text'])) ?><span class="hi-warn">(इस प्रश्न का हिंदी अनुवाद उपलब्ध नहीं है)</span>
        <?php endif; ?>
      </span>
    </h4>
    <?php if (in_array($row['question_type'], ['mcq','multi_select'])): ?>
      <?php foreach ($options as $o): $oHi = trim((string)($o['opt_text_hi'] ?? '')); ?>
        <label class="opt-line <?= $o['is_correct']?'correct':'' ?>">
          <input type="<?= $row['question_type']==='mcq'?'radio':'checkbox' ?>" disabled <?= $o['is_correct']?'checked':'' ?>>
          <span data-opt-lang="en"><?= h($o['opt_text']) ?></span>
          <span data-opt-lang="hi" class="hidden"><?= h($oHi !== '' ? $oHi : $o['opt_text']) ?></span>
          <?php if ($o['is_correct']): ?><span class="correct-marker"><i class="fas fa-check me-1"></i>Correct</span><?php endif; ?>
        </label>
      <?php endforeach; ?>
    <?php elseif ($row['question_type']==='true_false'): ?>
      <?php foreach ([['true','True','सत्य'],['false','False','असत्य']] as $tf): $isCorrect = ($tf[0]==='true' && $row['correct_bool']) || ($tf[0]==='false' && !$row['correct_bool']); ?>
        <label class="opt-line <?= $isCorrect?'correct':'' ?>">
          <input type="radio" disabled <?= $isCorrect?'checked':'' ?>>
          <span data-lang-text="en"><?= $tf[1] ?></span><span data-lang-text="hi" class="lang-hidden"><?= $tf[2] ?></span>
          <?php if ($isCorrect): ?><span class="correct-marker"><i class="fas fa-check me-1"></i>Correct</span><?php endif; ?>
        </label>
      <?php endforeach; ?>
    <?php elseif ($row['question_type']==='short_answer'): ?>
      <input type="text" class="form-control" placeholder="Student answer…" disabled>
      <div class="mt-3 p-3" style="background:#f0fdf4; border-left:4px solid #16a34a">
        <b class="text-success">Correct answer:</b>
        <span data-opt-lang="en"><?= h($row['correct_text']) ?></span>
        <?php if (!empty($row['correct_text_hi'])): ?>
          <span data-opt-lang="hi" class="hidden"><?= h($row['correct_text_hi']) ?></span>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <input type="number" step="any" class="form-control" placeholder="Student numeric answer…" disabled>
      <div class="mt-3 p-3" style="background:#f0fdf4; border-left:4px solid #16a34a">
        <b class="text-success">Correct answer:</b> <?= h($row['correct_numeric']) ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<script>
function setLang(lang) {
  document.querySelector('.preview-body').setAttribute('data-lang', lang);
  document.querySelectorAll('[data-lang-text]').forEach(el => el.classList.toggle('lang-hidden', el.getAttribute('data-lang-text') !== lang));
  document.querySelectorAll('[data-q-lang]').forEach(el => el.classList.toggle('hidden', el.getAttribute('data-q-lang') !== lang));
  document.querySelectorAll('[data-opt-lang]').forEach(el => el.classList.toggle('hidden', el.getAttribute('data-opt-lang') !== lang));
  document.querySelectorAll('[data-lang-btn]').forEach(b => b.classList.toggle('active', b.getAttribute('data-lang-btn') === lang));
}
setLang('en');
</script>
</body></html>
