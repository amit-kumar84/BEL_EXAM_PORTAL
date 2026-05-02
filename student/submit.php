<?php
require_once __DIR__ . '/../includes/helpers.php';
$u = require_login('student');
$aid = (int)($_GET['attempt'] ?? 0);
$stmt = db()->prepare('SELECT * FROM attempts WHERE id=? AND user_id=?');
$stmt->execute([$aid, $u['id']]);
$att = $stmt->fetch();
if (!$att) { flash('Attempt not found', 'error'); redirect(url('student/dashboard.php')); }
$just_submitted = ($att['status'] !== 'submitted');

if ($just_submitted) {
    // ---- Grade ---------------------------------------------------------------
    $qs = db()->prepare('SELECT * FROM questions WHERE exam_id=? AND deleted_at IS NULL');
    $qs->execute([$att['exam_id']]);
    $score = 0.0; $total = 0.0; $correct = 0; $wrong = 0; $skipped = 0;
    foreach ($qs->fetchAll() as $q) {
        $total += (float)$q['marks'];
        $as = db()->prepare('SELECT selected_json FROM attempt_answers WHERE attempt_id=? AND question_id=?');
        $as->execute([$aid, $q['id']]);
        $sel = $as->fetchColumn();
        $sel = $sel ? json_decode($sel, true) : null;
        if ($sel === null) { $skipped++; continue; }
        $isCorrect = false;
        if ($q['question_type'] === 'mcq' || $q['question_type'] === 'multi_select') {
            $co = db()->prepare('SELECT opt_order FROM question_options WHERE question_id=? AND is_correct=1');
            $co->execute([$q['id']]);
            $c = array_map('intval', $co->fetchAll(PDO::FETCH_COLUMN)); sort($c);
            $g = array_map('intval', $sel['selected'] ?? []); sort($g);
            $isCorrect = $g === $c;
        } elseif ($q['question_type'] === 'true_false') {
            $isCorrect = (bool)($sel['bool'] ?? false) === (bool)$q['correct_bool'];
        } elseif ($q['question_type'] === 'short_answer') {
            $a = trim((string)($sel['text'] ?? ''));
            // Accept either EN or HI correct text (bilingual)
            $isCorrect = (strcasecmp($a, trim((string)$q['correct_text'])) === 0)
                      || (!empty($q['correct_text_hi']) && strcasecmp($a, trim((string)$q['correct_text_hi'])) === 0);
        } elseif ($q['question_type'] === 'numeric') {
            $isCorrect = isset($sel['numeric']) && abs((float)$sel['numeric'] - (float)$q['correct_numeric']) < 1e-6;
        }
        if ($isCorrect) { $score += (float)$q['marks']; $correct++; }
        else            { $score -= (float)$q['negative_marks']; $wrong++; }
        db()->prepare('UPDATE attempt_answers SET is_correct=? WHERE attempt_id=? AND question_id=?')->execute([$isCorrect ? 1 : 0, $aid, $q['id']]);
    }
    $score = max(0, round($score, 2));
    $total = round($total, 2);
    db()->prepare('UPDATE attempts SET status="submitted", submitted_at=NOW(), score=?, total=? WHERE id=?')->execute([$score, $total, $aid]);
    $att['score'] = $score; $att['total'] = $total;
}

$ex = db()->prepare('SELECT * FROM exams WHERE id=?'); $ex->execute([$att['exam_id']]); $exam = $ex->fetch();
$pct = $att['total'] > 0 ? round(((float)$att['score'] / (float)$att['total']) * 100, 1) : 0;
$pass = $pct >= 40;

// If we haven't already computed, recompute totals quickly for display
if (!isset($correct)) {
    $st1 = db()->prepare('SELECT COUNT(*) FROM attempt_answers WHERE attempt_id=? AND is_correct=1');
    $st1->execute([$aid]); $correct = (int)$st1->fetchColumn();
    $st2 = db()->prepare('SELECT COUNT(*) FROM attempt_answers WHERE attempt_id=? AND is_correct=0 AND selected_json IS NOT NULL');
    $st2->execute([$aid]); $wrong = (int)$st2->fetchColumn();
    $tq = (int)db()->query('SELECT COUNT(*) FROM questions WHERE exam_id='.(int)$att['exam_id'].' AND deleted_at IS NULL')->fetchColumn();
    $skipped = max(0, $tq - $correct - $wrong);
}

$dashboard_url = url('student/dashboard.php');
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Exam Submitted · <?= h($exam['exam_name']) ?></title>
<link rel="stylesheet" href="<?= url('assets/lib/bootstrap/css/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= url('assets/lib/fontawesome/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
<style>
:root { --navy:#0E2A47; --saffron:#FF9933; --green:#138808; }
body { background:linear-gradient(135deg,#eef2f8 0%,#e4ecf7 100%); margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:30px 20px; font-family:'Segoe UI',sans-serif; }
.wrap { max-width:780px; width:100%; }
.tricolour { height:6px; display:flex; border-radius:6px 6px 0 0; overflow:hidden; }
.tricolour span { flex:1; } .tricolour .o { background:var(--saffron); } .tricolour .w { background:#fff; border-top:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0; } .tricolour .g { background:var(--green); }
.card-main { background:#fff; border-radius:0 0 6px 6px; box-shadow:0 14px 40px rgba(15,23,42,.18); overflow:hidden; }
.hero { background:linear-gradient(135deg,var(--navy) 0%,#081a2e 100%); color:#fff; padding:26px 28px 22px; position:relative; overflow:hidden; }
.hero::before { content:''; position:absolute; top:-50px; right:-50px; width:220px; height:220px; border-radius:50%; background:rgba(255,153,51,.15); }
.hero::after  { content:''; position:absolute; bottom:-80px; left:-80px; width:280px; height:280px; border-radius:50%; background:rgba(19,136,8,.10); }
.hero-inner { position:relative; display:flex; align-items:center; gap:18px; }
.hero img { width:62px; height:62px; object-fit:contain; background:#fff; padding:6px; border-radius:4px; }
.hero .govt { font-size:10px; letter-spacing:.25em; color:var(--saffron); font-weight:700; text-transform:uppercase; }
.hero h2 { margin:4px 0 2px; font-size:20px; font-weight:800; }
.hero h3 { margin:0; font-size:13px; font-weight:600; opacity:.85; }
.hero .sub { font-size:11px; margin-top:4px; opacity:.7; }
.content { padding:30px 32px 34px; }

.success-tick { width:110px; height:110px; margin:0 auto 18px; position:relative; }
.success-tick svg { width:100%; height:100%; }
.success-tick .ring { fill:none; stroke:var(--green); stroke-width:6; stroke-linecap:round; transform-origin:50% 50%; animation:draw-ring .9s cubic-bezier(.4,0,.2,1) forwards; }
.success-tick .check { fill:none; stroke:var(--green); stroke-width:8; stroke-linecap:round; stroke-linejoin:round; stroke-dasharray:60; stroke-dashoffset:60; animation:draw-check .45s .6s cubic-bezier(.4,0,.2,1) forwards; }
@keyframes draw-ring { from { stroke-dasharray:0 300; } to { stroke-dasharray:300 0; } }
@keyframes draw-check { to { stroke-dashoffset:0; } }
.confetti { position:absolute; inset:-40px; pointer-events:none; }
.confetti span { position:absolute; width:6px; height:10px; animation:fall 2.5s ease-out forwards; opacity:0; }
@keyframes fall { 0%{opacity:1; transform:translateY(-20px) rotate(0)} 100%{opacity:0; transform:translateY(150px) rotate(360deg)} }

.title { text-align:center; font-size:26px; font-weight:800; color:var(--navy); margin:0 0 6px; letter-spacing:-.01em; }
.subtitle { text-align:center; color:#475569; margin-bottom:22px; font-size:14px; }
.subtitle-hi { text-align:center; color:#64748b; font-size:13px; margin-bottom:22px; }

.stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:22px; }
.stats-grid .stat { background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; padding:16px 10px; text-align:center; transition:transform .15s; }
.stats-grid .stat:hover { transform:translateY(-2px); }
.stats-grid .stat .n { font-size:28px; font-weight:800; line-height:1; }
.stats-grid .stat .l { font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:#64748b; margin-top:6px; }
.stats-grid .stat.c { background:#f0fdf4; border-color:#86efac; } .stats-grid .stat.c .n { color:var(--green); }
.stats-grid .stat.w { background:#fef2f2; border-color:#fca5a5; } .stats-grid .stat.w .n { color:#dc2626; }
.stats-grid .stat.s { background:#fffbeb; border-color:#fcd34d; } .stats-grid .stat.s .n { color:#b45309; }
.stats-grid .stat.t { background:var(--navy); border-color:var(--navy); color:#fff; } .stats-grid .stat.t .n { color:#fff; } .stats-grid .stat.t .l { color:rgba(255,255,255,.7); }

.progress-wrap { margin:20px 0; }
.progress-wrap .label { display:flex; justify-content:space-between; margin-bottom:8px; font-size:12px; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:.06em; }
.progress-bar-outer { height:12px; background:#e2e8f0; border-radius:6px; overflow:hidden; position:relative; }
.progress-bar-inner { height:100%; background:linear-gradient(90deg,var(--saffron) 0%,var(--green) 100%); border-radius:6px; transition:width 1.2s cubic-bezier(.4,0,.2,1); }

.verdict { padding:18px; border-radius:4px; text-align:center; margin:20px 0; font-weight:700; font-size:18px; letter-spacing:.03em; }
.verdict.pass { background:linear-gradient(135deg,#d1fae5 0%,#ecfdf5 100%); color:#065f46; border:2px solid var(--green); }
.verdict.fail { background:linear-gradient(135deg,#fee2e2 0%,#fef2f2 100%); color:#991b1b; border:2px solid #dc2626; }
.verdict .pct { font-size:32px; font-weight:800; display:block; margin-bottom:4px; }

.meta-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; padding:16px 18px; background:#f8fafc; border-radius:4px; margin-top:18px; font-size:13px; }
.meta-row .item { }
.meta-row .item .l { font-size:10px; letter-spacing:.08em; color:#64748b; text-transform:uppercase; margin-bottom:3px; }
.meta-row .item .v { font-weight:600; color:#0f172a; }

.actions { display:flex; gap:12px; justify-content:center; margin-top:24px; flex-wrap:wrap; }
.btn-primary-navy { background:var(--navy); color:#fff; border:2px solid var(--navy); padding:12px 28px; border-radius:4px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:8px; transition:all .15s; }
.btn-primary-navy:hover { background:#081a2e; color:#fff; transform:translateY(-1px); box-shadow:0 6px 14px rgba(14,42,71,.3); }
.btn-outline-navy { background:transparent; color:var(--navy); border:2px solid var(--navy); padding:12px 28px; border-radius:4px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
.btn-outline-navy:hover { background:var(--navy); color:#fff; }
.countdown { text-align:center; margin-top:14px; color:#64748b; font-size:12px; }
.countdown b { color:var(--navy); font-size:16px; }
.footer-note { text-align:center; font-size:11px; color:#94a3b8; margin-top:18px; padding-top:14px; border-top:1px solid #e2e8f0; }
</style></head>
<body>
<div class="wrap">
  <div class="tricolour"><span class="o"></span><span class="w"></span><span class="g"></span></div>
  <div class="card-main">
    <div class="hero">
      <div class="hero-inner">
        <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" alt="BEL">
        <div>
          <div class="govt">भारत सरकार · Government of India</div>
          <h2>भारत इलेक्ट्रॉनिक्स लिमिटेड</h2>
          <h3>Bharat Electronics Limited · Kotdwar Unit</h3>
          <div class="sub">Ministry of Defence · A Navratna Company</div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="success-tick">
        <div class="confetti">
          <?php for ($i=0;$i<14;$i++): $left = rand(5,95); $delay = rand(0,800)/1000; $color = ['#FF9933','#138808','#0E2A47','#fff'][rand(0,3)]; ?>
            <span style="left:<?= $left ?>%; animation-delay:<?= $delay ?>s; background:<?= $color ?>; top:<?= rand(0,30) ?>px"></span>
          <?php endfor; ?>
        </div>
        <svg viewBox="0 0 100 100">
          <circle class="ring" cx="50" cy="50" r="44" />
          <path class="check" d="M30 52 L45 67 L72 38" />
        </svg>
      </div>

      <h1 class="title">Exam Submitted Successfully</h1>
      <div class="subtitle">Your responses have been recorded securely.</div>
      <div class="subtitle-hi" lang="hi">आपकी परीक्षा सफलतापूर्वक जमा कर दी गई है · उत्तर सुरक्षित रूप से दर्ज किए गए हैं</div>

      <!-- Results hidden — will be released by admin -->
      <div style="background:#f0f9ff; border:1px solid #0ea5e9; border-radius:4px; padding:18px; margin:20px 0; text-align:center; color:#0369a1; font-weight:600;">
        <i class="fas fa-info-circle me-2"></i>Your results will be displayed after evaluation by the examination committee.
        <br><span lang="hi" style="display:block; margin-top:6px; font-size:13px; font-weight:500;">परीक्षा समिति द्वारा मूल्यांकन के बाद परिणाम दिखाए जाएंगे।</span>
      </div>

      <div class="meta-row">
        <div class="item"><div class="l">Examination · परीक्षा</div><div class="v"><?= h($exam['exam_name']) ?></div></div>
        <div class="item"><div class="l">Candidate · अभ्यर्थी</div><div class="v"><?= h($u['name']) ?></div></div>
        <div class="item"><div class="l">Roll / Staff ID</div><div class="v" style="font-family:monospace"><?= h($u['roll_number']) ?></div></div>
        <div class="item"><div class="l">Submitted At · जमा किया</div><div class="v"><?= date('d M Y, h:i A', strtotime($att['submitted_at'] ?? 'now')) ?></div></div>
      </div>

      <div class="actions">
        <a href="<?= h($dashboard_url) ?>" class="btn-primary-navy" id="go-now"><i class="fas fa-gauge-high"></i> Go to Dashboard</a>
        <a href="<?= h($dashboard_url) ?>#history" class="btn-outline-navy"><i class="fas fa-clock-rotate-left"></i> View My Attempts</a>
      </div>
      <div class="countdown">Redirecting automatically in <b id="count">10</b> seconds · <span lang="hi">स्वतः पुनर्निर्देशन</span></div>

      <div class="footer-note">
        <i class="fas fa-lock me-1"></i> Your responses are digitally stored and tamper-evident. Final result subject to review by examination committee.
        <br><span lang="hi">आपके उत्तर डिजिटल रूप से सुरक्षित एवं पुष्टिकरण योग्य हैं। अंतिम परिणाम परीक्षा समिति द्वारा निर्धारित होगा।</span>
      </div>
    </div>
  </div>
</div>

<script>
// Countdown + auto-redirect
let s = 10;
const t = document.getElementById('count');
const si = setInterval(() => { s--; t.textContent = s; if (s <= 0) { clearInterval(si); window.location = '<?= h($dashboard_url) ?>'; } }, 1000);
// If the exam was in fullscreen, release it gracefully
if (document.fullscreenElement) { try { document.exitFullscreen(); } catch(e) {} }
</script>
</body></html>
<?php exit;
