<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/lang.php';
ensure_bilingual_columns();   // auto-adds _hi columns if missing (safe no-op after first run)
ensure_phase3_migrations();

$u = require_login('student');
$eid = (int)($_GET['exam_id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM exams WHERE id=?');
$stmt->execute([$eid]);
$exam = $stmt->fetch();
if (!$exam) die('Exam not found');
if (!is_exam_assigned((int)$u['id'], $eid)) die('This examination is not assigned to your account.');

$now = time();
if ($now < strtotime($exam['start_time']) || $now > strtotime($exam['end_time'])) die('Exam not in active window');
$used = db()->prepare('SELECT COUNT(*) FROM attempts WHERE user_id=? AND exam_id=? AND status="submitted"');
$used->execute([$u['id'], $eid]);
if ((int)$used->fetchColumn() >= (int)$exam['max_attempts']) die('Max attempts reached');

// Find or create in-progress attempt
$stmt = db()->prepare('SELECT * FROM attempts WHERE user_id=? AND exam_id=? AND status="in_progress" LIMIT 1');
$stmt->execute([$u['id'], $eid]);
$att = $stmt->fetch();
if (!$att) {
    $ends = date('Y-m-d H:i:s', $now + (int)$exam['duration_minutes'] * 60);
    $atn = db()->prepare('SELECT COALESCE(MAX(attempt_no),0)+1 FROM attempts WHERE user_id=? AND exam_id=?');
    $atn->execute([$u['id'], $eid]);
    $an = (int)$atn->fetchColumn();
    $ins = db()->prepare('INSERT INTO attempts (user_id, exam_id, attempt_no, started_at, ends_at, status) VALUES (?, ?, ?, NOW(), ?, "in_progress")');
    $ins->execute([$u['id'], $eid, $an, $ends]);
    $att = db()->query('SELECT * FROM attempts WHERE id='.(int)db()->lastInsertId())->fetch();
}

// Load questions (English + Hindi both, without correct answers revealed)
$qs = db()->prepare('SELECT id, question_type, question_text, question_text_hi, marks, negative_marks FROM questions WHERE exam_id=? AND deleted_at IS NULL ORDER BY id');
$qs->execute([$eid]);
$questions = $qs->fetchAll();
$opts = [];
if ($questions) {
    $ids = array_column($questions, 'id');
    $in = str_repeat('?,', count($ids) - 1) . '?';
    $o = db()->prepare("SELECT id, question_id, opt_order, opt_text, opt_text_hi FROM question_options WHERE question_id IN ($in) ORDER BY question_id, opt_order");
    $o->execute($ids);
    foreach ($o->fetchAll() as $r) $opts[$r['question_id']][] = $r;
}

// Load existing answers
$ans = [];
$ax = db()->prepare('SELECT question_id, selected_json, marked_review FROM attempt_answers WHERE attempt_id=?');
$ax->execute([$att['id']]);
foreach ($ax->fetchAll() as $r) $ans[$r['question_id']] = $r;

$ends_ts = strtotime($att['ends_at']);

// Per-exam violation config (super-admin controlled, defaults all-ON).
$VIOL_CFG = exam_violation_config($exam);
$MAX_V = (int)($exam['max_violations'] ?? MAX_VIOLATIONS);
if ($MAX_V <= 0) $MAX_V = MAX_VIOLATIONS;
$FORCE_FS = (int)($exam['force_fullscreen'] ?? 1);

// Q-type localised labels (for the "Question N · <type> · X marks" line)
$QTYPE_EN = [
    'mcq' => 'Multiple Choice', 'multi_select' => 'Multi Select',
    'true_false' => 'True / False', 'short_answer' => 'Short Answer', 'numeric' => 'Numeric',
];
$QTYPE_HI = [
    'mcq' => 'बहुविकल्पीय', 'multi_select' => 'बहु-चयन',
    'true_false' => 'सत्य / असत्य', 'short_answer' => 'संक्षिप्त उत्तर', 'numeric' => 'संख्यात्मक',
];

$PAGE_TITLE = 'Exam in Progress';
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title><?= h($exam['exam_name']) ?></title>
<link rel="stylesheet" href="<?= url('assets/lib/bootstrap/css/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= url('assets/lib/fontawesome/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
<style>
/* Inline bilingual toggle styles (self-contained, works offline on intranet) */
.lang-switch { display:inline-flex; align-items:center; gap:4px; background:#0f172a; border:1px solid #334155; border-radius:4px; padding:3px; }
.lang-switch button { border:0; background:transparent; color:#cbd5e1; font-weight:600; font-size:12px; padding:4px 10px; border-radius:3px; cursor:pointer; letter-spacing:.03em; }
.lang-switch button.active { background:var(--saffron, #FF9933); color:#0f172a; }
.lang-switch button:hover:not(.active) { color:#fff; }
.per-q-toggle { background:#eef2ff; color:#1e3a8a; border:1px solid #c7d2fe; font-size:12px; font-weight:600; padding:4px 10px; border-radius:3px; cursor:pointer; }
.per-q-toggle:hover { background:#e0e7ff; }
.per-q-toggle .fa-language { margin-right:4px; }
[data-lang-text] { display:inline; }
[data-lang-text].lang-hidden { display:none !important; }
.q-box h4[data-hi-missing="1"] .hi-warn { display:inline-block; font-size:12px; color:#b45309; background:#fef3c7; padding:2px 8px; border-radius:3px; margin-left:6px; }
</style>
</head>
<body class="exam-wrap" data-lang="en">
<?php if ($FORCE_FS): ?>
<div id="fs-overlay" class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="z-index:2000;background:rgba(15,23,42,.96);">
  <div class="text-center text-white p-4" style="max-width:520px">
    <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" style="width:72px; height:72px; background:#fff; padding:8px; border-radius:4px; object-fit:contain" alt="BEL">
    <h3 class="fw-bold mt-3 mb-2">
      <span data-lang-text="en">Start in Fullscreen</span>
      <span data-lang-text="hi" class="lang-hidden">पूर्ण स्क्रीन में प्रारंभ करें</span>
    </h3>
    <p class="small text-secondary mb-4">
      <span data-lang-text="en">Click the button below to enter fullscreen and begin the lockdown exam session.</span>
      <span data-lang-text="hi" class="lang-hidden">परीक्षा प्रारंभ करने के लिए नीचे दिए बटन पर क्लिक करें और पूर्ण स्क्रीन में प्रवेश करें।</span>
    </p>
    <button id="fs-start-btn" class="btn btn-success btn-lg px-4">
      <i class="fas fa-expand me-2"></i>
      <span data-lang-text="en">Enter Fullscreen &amp; Start Exam</span>
      <span data-lang-text="hi" class="lang-hidden">पूर्ण स्क्रीन में जाएँ और परीक्षा प्रारंभ करें</span>
    </button>
  </div>
</div>
<?php endif; ?>
<header class="exam-header">
  <div class="d-flex align-items-center gap-2">
    <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" style="width:36px; height:36px; background:#fff; padding:4px; border-radius:3px; object-fit:contain" alt="BEL">
    <div>
      <div class="fw-bold small"><?= h($exam['exam_name']) ?></div>
      <div class="small" style="color:#94a3b8">
        <span data-lang-text="en">Attempt</span><span data-lang-text="hi" class="lang-hidden">प्रयास</span>
        #<?= (int)$att['attempt_no'] ?> ·
        <span data-lang-text="en">Q</span><span data-lang-text="hi" class="lang-hidden">प्र</span><span id="cur">1</span>/<?= count($questions) ?>
      </div>
    </div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <span class="badge bg-warning text-dark"><i class="fas fa-video me-1"></i>
      <span data-lang-text="en">LIVE</span><span data-lang-text="hi" class="lang-hidden">लाइव</span>
    </span>
    <span id="viol-pill" class="badge bg-warning text-dark"><i class="fas fa-triangle-exclamation me-1"></i> <span id="viol-count">0</span>/<?= (int)$MAX_V ?></span>
    <span id="timer" class="exam-timer">--:--</span>
    <!-- Offline language switch (default: English) -->
    <div class="lang-switch ms-3" role="group" aria-label="Language">
      <button type="button" id="lang-en-btn" class="active" data-lang-btn="en" onclick="setExamLanguage('en')">EN</button>
      <button type="button" id="lang-hi-btn" data-lang-btn="hi" onclick="setExamLanguage('hi')">हिं</button>
    </div>
  </div>
</header>
<div id="warn" class="warning-bar d-none"></div>
<div class="exam-body">
  <div class="exam-question" id="q-panel">
    <?php foreach ($questions as $i => $q):
      $a = $ans[$q['id']] ?? null;
      $sel = $a ? json_decode($a['selected_json'] ?? 'null', true) : null;
      $qHi = trim((string)($q['question_text_hi'] ?? ''));
      $hasHi = $qHi !== '';
    ?>
      <div class="q-box" data-qid="<?= $q['id'] ?>" data-idx="<?= $i ?>" style="display:<?= $i===0?'block':'none' ?>">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
          <div class="small text-uppercase text-secondary">
            <span data-lang-text="en">Question</span><span data-lang-text="hi" class="lang-hidden">प्रश्न</span>
            <?= $i+1 ?> ·
            <span data-lang-text="en"><?= h($QTYPE_EN[$q['question_type']] ?? $q['question_type']) ?></span>
            <span data-lang-text="hi" class="lang-hidden"><?= h($QTYPE_HI[$q['question_type']] ?? $q['question_type']) ?></span>
            · <?= (float)$q['marks'] ?>
            <span data-lang-text="en">marks</span><span data-lang-text="hi" class="lang-hidden">अंक</span>
            <?php if ((float)$q['negative_marks']>0) echo ' · −'.(float)$q['negative_marks']; ?>
          </div>
          <button type="button" class="per-q-toggle" data-q-lang-btn="<?= $q['id'] ?>" data-current="en" onclick="toggleQuestionLang(<?= $q['id'] ?>)">
            <i class="fas fa-language"></i>
            <span class="pq-label-en">हिंदी में दिखाएँ</span>
            <span class="pq-label-hi" style="display:none">English में दिखाएँ</span>
          </button>
        </div>
        <h4 class="mt-2 mb-4" data-hi-missing="<?= $hasHi?'0':'1' ?>">
          <span data-q-lang="en" data-qid="<?= $q['id'] ?>"><?= nl2br(h($q['question_text'])) ?></span>
          <span data-q-lang="hi" data-qid="<?= $q['id'] ?>" style="display:none">
            <?php if ($hasHi): ?>
              <?= nl2br(h($qHi)) ?>
            <?php else: ?>
              <?= nl2br(h($q['question_text'])) ?>
              <span class="hi-warn" style="display:inline-block; font-size:12px; color:#b45309; background:#fef3c7; padding:2px 8px; border-radius:3px; margin-left:6px">
                (इस प्रश्न का हिंदी अनुवाद उपलब्ध नहीं है)
              </span>
            <?php endif; ?>
          </span>
        </h4>
        <?php if ($q['question_type']==='mcq'): ?>
          <?php foreach ($opts[$q['id']] ?? [] as $o):
            $oHi = trim((string)($o['opt_text_hi'] ?? ''));
          ?>
            <label class="d-block p-3 border mb-2" style="cursor:pointer; border-radius:3px">
              <input type="radio" name="q<?= $q['id'] ?>" value="<?= $o['opt_order'] ?>" <?= $sel && in_array($o['opt_order'], $sel['selected'] ?? []) ? 'checked' : '' ?> onchange="saveAns(<?= $q['id'] ?>, {selected:[parseInt(this.value)]})">
              <span data-opt-lang="en" data-qid="<?= $q['id'] ?>"><?= h($o['opt_text']) ?></span>
              <span data-opt-lang="hi" data-qid="<?= $q['id'] ?>" style="display:none"><?= h($oHi !== '' ? $oHi : $o['opt_text']) ?></span>
            </label>
          <?php endforeach; ?>
        <?php elseif ($q['question_type']==='multi_select'): ?>
          <?php foreach ($opts[$q['id']] ?? [] as $o):
            $oHi = trim((string)($o['opt_text_hi'] ?? ''));
          ?>
            <label class="d-block p-3 border mb-2" style="cursor:pointer; border-radius:3px">
              <input type="checkbox" class="ms-<?= $q['id'] ?>" value="<?= $o['opt_order'] ?>" <?= $sel && in_array($o['opt_order'], $sel['selected'] ?? []) ? 'checked' : '' ?> onchange="saveMulti(<?= $q['id'] ?>)">
              <span data-opt-lang="en" data-qid="<?= $q['id'] ?>"><?= h($o['opt_text']) ?></span>
              <span data-opt-lang="hi" data-qid="<?= $q['id'] ?>" style="display:none"><?= h($oHi !== '' ? $oHi : $o['opt_text']) ?></span>
            </label>
          <?php endforeach; ?>
        <?php elseif ($q['question_type']==='true_false'): ?>
          <?php foreach ([['true','True','सत्य'],['false','False','असत्य']] as $tf): ?>
            <label class="d-block p-3 border mb-2" style="cursor:pointer; border-radius:3px">
              <input type="radio" name="q<?= $q['id'] ?>" value="<?= $tf[0] ?>" <?= $sel && (($tf[0]==='true')===!!$sel['bool']) ? 'checked' : '' ?> onchange="saveAns(<?= $q['id'] ?>, {bool: this.value==='true'})">
              <span data-lang-text="en"><?= $tf[1] ?></span>
              <span data-lang-text="hi" class="lang-hidden"><?= $tf[2] ?></span>
            </label>
          <?php endforeach; ?>
        <?php elseif ($q['question_type']==='short_answer'): ?>
          <input type="text" class="form-control" value="<?= h($sel['text'] ?? '') ?>" oninput="saveAns(<?= $q['id'] ?>, {text: this.value})">
        <?php else: /* numeric */ ?>
          <input type="number" step="any" class="form-control" value="<?= h($sel['numeric'] ?? '') ?>" oninput="saveAns(<?= $q['id'] ?>, {numeric: parseFloat(this.value)})">
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <aside class="exam-side">
    <div class="mb-3 bg-white p-2 text-center border">
      <video id="cam" autoplay muted playsinline style="width:200px; height:150px; background:#0f172a; object-fit:cover; border-radius:3px"></video>
    </div>
    <div class="small fw-bold text-uppercase text-secondary mb-2">
      <span data-lang-text="en">Question Palette</span>
      <span data-lang-text="hi" class="lang-hidden">प्रश्न पट</span>
    </div>
    <div id="palette">
      <?php foreach ($questions as $i => $q): ?>
        <span class="palette-btn <?= $i===0?'current':'' ?>" data-qid="<?= $q['id'] ?>" data-idx="<?= $i ?>" onclick="goTo(<?= $i ?>)"><?= $i+1 ?></span>
      <?php endforeach; ?>
    </div>
    <div class="mt-3 small d-grid gap-1" style="grid-template-columns:1fr 1fr">
      <div><span class="palette-btn answered" style="width:14px;height:14px;font-size:0;margin:0 6px 0 0"></span>
        <span data-lang-text="en">Answered</span><span data-lang-text="hi" class="lang-hidden">उत्तर दिया</span></div>
      <div><span class="palette-btn not-answered" style="width:14px;height:14px;font-size:0;margin:0 6px 0 0"></span>
        <span data-lang-text="en">Not Ans.</span><span data-lang-text="hi" class="lang-hidden">अनुत्तरित</span></div>
      <div><span class="palette-btn marked" style="width:14px;height:14px;font-size:0;margin:0 6px 0 0"></span>
        <span data-lang-text="en">Marked</span><span data-lang-text="hi" class="lang-hidden">चिह्नित</span></div>
      <div><span class="palette-btn ans-marked" style="width:14px;height:14px;font-size:0;margin:0 6px 0 0"></span>
        <span data-lang-text="en">Ans+Mark</span><span data-lang-text="hi" class="lang-hidden">उत्तर+चिह्न</span></div>
    </div>
  </aside>
</div>
<footer class="exam-footer">
  <button class="btn btn-outline-secondary" onclick="prev()"><i class="fas fa-chevron-left"></i>
    <span data-lang-text="en">Previous</span><span data-lang-text="hi" class="lang-hidden">पिछला</span>
  </button>
  <button class="btn btn-primary" onclick="toggleMark()"><i class="fas fa-flag me-1"></i>
    <span id="mark-lbl">
      <span data-lang-text="en">Mark for Review</span>
      <span data-lang-text="hi" class="lang-hidden">समीक्षा हेतु चिह्नित करें</span>
    </span>
  </button>
  <div class="flex-grow-1"></div>
  <button class="btn btn-success" onclick="next()">
    <span data-lang-text="en">Save &amp; Next</span><span data-lang-text="hi" class="lang-hidden">सहेजें व आगे</span>
    <i class="fas fa-chevron-right"></i>
  </button>
  <button class="btn btn-danger" onclick="submitExam()"><i class="fas fa-paper-plane me-1"></i>
    <span data-lang-text="en">Submit</span><span data-lang-text="hi" class="lang-hidden">जमा करें</span>
  </button>
</footer>

<script>
const ATTEMPT_ID = <?= (int)$att['id'] ?>;
const ENDS_TS = <?= $ends_ts * 1000 ?>;
const MAX_V = <?= (int)$MAX_V ?>;
const VIOLATION_CONFIG = <?= json_encode($VIOL_CFG) ?>;
const FORCE_FULLSCREEN = <?= $FORCE_FS ? 'true' : 'false' ?>;
const SUBMIT_URL = <?= json_encode(url('student/submit.php?attempt='.$att['id'])) ?>;
const SAVE_URL = <?= json_encode(url('api/save-answer.php')) ?>;
const VIOL_URL = <?= json_encode(url('api/violation.php')) ?>;
const INITIAL_MARKED = <?= json_encode(array_keys(array_filter($ans, fn($a)=>$a['marked_review']))) ?>;

// Localised confirm/alert strings (offline — intranet-safe)
const I18N = {
  en: { submitConfirm: 'Are you sure you want to submit the exam? You cannot change answers after submitting.' },
  hi: { submitConfirm: 'क्या आप वाकई परीक्षा जमा करना चाहते हैं? जमा करने के बाद उत्तर नहीं बदले जा सकते।' }
};

const overlay = document.getElementById('fs-overlay');
const startBtn = document.getElementById('fs-start-btn');
if (startBtn) {
  startBtn.addEventListener('click', async () => {
    try {
      await document.documentElement.requestFullscreen();
    } catch (err) {
      alert('Fullscreen was blocked by the browser. Please allow fullscreen and try again.');
      return;
    }
    overlay?.remove();
    if (typeof startLockdown === 'function') startLockdown();
  });
}
</script>
<script>
function beep(){
  try{
    const C = window.AudioContext || window.webkitAudioContext;
    const ctx = new C();
    const o = ctx.createOscillator();
    const g = ctx.createGain();
    o.type = 'sine'; o.frequency.value = 880;
    o.connect(g); g.connect(ctx.destination);
    g.gain.setValueAtTime(0.0001, ctx.currentTime);
    o.start();
    g.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime + 0.01);
    g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.2);
    o.stop(ctx.currentTime + 0.25);
  }catch(e){}
}

/* ==========================================================================
 * OFFLINE bilingual engine (EN/HI) — no Google Translate, no internet.
 * - Entire-screen toggle via EN / हिं buttons in header.
 * - Per-question toggle via the "हिंदी में दिखाएँ" button next to each Q.
 * - Per-question state overrides the global state for that specific question.
 * ==========================================================================*/
const QUESTION_LANG_OVERRIDES = Object.create(null); // { qid: 'en' | 'hi' }
let currentExamLang = 'en';

function applyUiLanguage(lang) {
  document.body.setAttribute('data-lang', lang);
  // Toggle generic [data-lang-text="en|hi"] visibility
  document.querySelectorAll('[data-lang-text]').forEach(el => {
    const tgt = el.getAttribute('data-lang-text');
    el.classList.toggle('lang-hidden', tgt !== lang);
  });
  // Header buttons active state
  document.querySelectorAll('[data-lang-btn]').forEach(b => {
    b.classList.toggle('active', b.getAttribute('data-lang-btn') === lang);
  });
  // Apply to every question according to its override (if any) else global
  document.querySelectorAll('.q-box').forEach(box => {
    const qid = box.getAttribute('data-qid');
    const effective = QUESTION_LANG_OVERRIDES[qid] || lang;
    applyQuestionLang(qid, effective);
  });
}

function applyQuestionLang(qid, lang) {
  document.querySelectorAll(`[data-q-lang][data-qid="${qid}"]`).forEach(el => {
    el.style.display = (el.getAttribute('data-q-lang') === lang) ? '' : 'none';
  });
  document.querySelectorAll(`[data-opt-lang][data-qid="${qid}"]`).forEach(el => {
    el.style.display = (el.getAttribute('data-opt-lang') === lang) ? '' : 'none';
  });
  // Update per-question toggle button label
  const btn = document.querySelector(`[data-q-lang-btn="${qid}"]`);
  if (btn) {
    btn.setAttribute('data-current', lang);
    const en = btn.querySelector('.pq-label-en');
    const hi = btn.querySelector('.pq-label-hi');
    if (en && hi) {
      en.style.display = (lang === 'en') ? '' : 'none';
      hi.style.display = (lang === 'hi') ? '' : 'none';
    }
  }
}

function setExamLanguage(lang) {
  if (lang !== 'en' && lang !== 'hi') return;
  currentExamLang = lang;
  // Clear per-question overrides so the global choice fully takes effect
  for (const k in QUESTION_LANG_OVERRIDES) delete QUESTION_LANG_OVERRIDES[k];
  applyUiLanguage(lang);
  try { localStorage.setItem('bel_exam_lang', lang); } catch(e) {}
}

function toggleQuestionLang(qid) {
  const key = String(qid);
  const effective = QUESTION_LANG_OVERRIDES[key] || currentExamLang;
  const next = effective === 'en' ? 'hi' : 'en';
  QUESTION_LANG_OVERRIDES[key] = next;
  applyQuestionLang(key, next);
}

// Restore last-used language (within same browser on student's PC)
try {
  const saved = localStorage.getItem('bel_exam_lang');
  if (saved === 'hi' || saved === 'en') currentExamLang = saved;
} catch(e) {}
document.addEventListener('DOMContentLoaded', () => applyUiLanguage(currentExamLang));
// Also apply immediately in case script runs after DOM is ready
applyUiLanguage(currentExamLang);
</script>
<script src="<?= url('assets/js/lockdown.js') ?>"></script>
</body></html>
