<?php
// admin/attempt-pdf.php — Print-ready (browser → Save as PDF) A4 multi-page
// result document with BEL letterhead, score summary on page-1, then the full
// answer key with student's response + correct answer per question.
// Designed for intranet — no external libraries, uses browser Print.
require_once __DIR__ . '/../includes/helpers.php';
$me = require_login('admin');
ensure_bilingual_columns();
ensure_softdelete_and_permissions();

$aid = (int)($_GET['id'] ?? 0);
$a = db()->prepare('SELECT a.*, u.name AS sname, u.email AS semail, u.roll_number, u.dob, u.photo_path, u.category, e.exam_name, e.exam_code, e.created_by AS exam_owner, e.duration_minutes, creator.name AS creator_name, creator.email AS creator_email
  FROM attempts a JOIN users u ON u.id=a.user_id JOIN exams e ON e.id=a.exam_id LEFT JOIN users creator ON creator.id=e.created_by WHERE a.id=?');
$a->execute([$aid]);
$att = $a->fetch();
if (!$att) die('Attempt not found');
if (empty($me['is_super']) && (int)$att['exam_owner'] !== (int)$me['id']) die('Forbidden');

$qs = db()->prepare('SELECT * FROM questions WHERE exam_id=? AND deleted_at IS NULL ORDER BY id');
$qs->execute([$att['exam_id']]);
$questions = $qs->fetchAll();
$optsMap = [];
if ($questions) {
    $ids = array_column($questions,'id');
    $in = str_repeat('?,', count($ids)-1) . '?';
    $o = db()->prepare("SELECT * FROM question_options WHERE question_id IN ($in) AND deleted_at IS NULL ORDER BY opt_order");
    $o->execute($ids);
    foreach ($o->fetchAll() as $r) $optsMap[$r['question_id']][] = $r;
}
$ans = [];
$ax = db()->prepare('SELECT * FROM attempt_answers WHERE attempt_id=?');
$ax->execute([$aid]);
foreach ($ax->fetchAll() as $r) $ans[$r['question_id']] = $r;

$viols = db()->prepare('SELECT * FROM violations WHERE attempt_id=? ORDER BY event_time');
$viols->execute([$aid]);
$violations = $viols->fetchAll();

$totalQ = count($questions);
$correctN = 0; $wrongN = 0; $skippedN = 0;
foreach ($questions as $q) {
    $aa = $ans[$q['id']] ?? null;
    if (!$aa || !$aa['selected_json']) $skippedN++;
    elseif ($aa['is_correct']) $correctN++;
    else $wrongN++;
}
$pct = ($att['total'] > 0) ? round(((float)$att['score'] / (float)$att['total']) * 100, 1) : 0;
$pass = $pct >= 40;   // 40% default pass mark — admins can adjust

function fmtSel($q, $a, $opts) {
    if (!$a || !$a['selected_json']) return '<span class="text-muted fst-italic">Not answered</span>';
    $s = json_decode($a['selected_json'], true);
    if (in_array($q['question_type'], ['mcq','multi_select'])) {
        $texts = [];
        foreach ($opts as $o) {
            if (in_array((int)$o['opt_order'], array_map('intval', $s['selected'] ?? []))) $texts[] = h($o['opt_text']);
        }
        return implode(', ', $texts) ?: '<span class="text-muted">—</span>';
    }
    if ($q['question_type']==='true_false') return !empty($s['bool']) ? 'True' : 'False';
    return h($s['text'] ?? $s['numeric'] ?? '');
}
function fmtCorrect($q, $opts) {
    if (in_array($q['question_type'], ['mcq','multi_select']))
        return implode(', ', array_map(fn($o)=>h($o['opt_text']), array_filter($opts, fn($o)=>$o['is_correct'])));
    if ($q['question_type']==='true_false') return $q['correct_bool'] ? 'True' : 'False';
    if ($q['question_type']==='short_answer') return h($q['correct_text']);
    return h($q['correct_numeric']);
}
$photoUrl = !empty($att['photo_path']) ? url($att['photo_path']) : '';
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Result · <?= h($att['sname']) ?> · <?= h($att['exam_name']) ?></title>
<link rel="stylesheet" href="<?= url('assets/lib/bootstrap/css/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= url('assets/lib/fontawesome/css/all.min.css') ?>">
<style>
@page { size: A4; margin: 12mm; }
* { box-sizing: border-box; }
body { font-family: 'Segoe UI', 'Helvetica Neue', sans-serif; color:#0f172a; background:#f1f5f9; margin:0; padding:20px; }
:root { --navy:#0E2A47; --saffron:#FF9933; --green:#138808; }
.page { background:#fff; width:210mm; min-height:297mm; margin:0 auto 20px; padding:14mm 12mm; position:relative; box-shadow:0 2px 10px rgba(15,23,42,.08); page-break-after:always; }
.page:last-child { page-break-after:auto; }
.tri { display:flex; height:4px; margin:0 -12mm 10px; }
.tri span { flex:1; }
.tri .o { background:var(--saffron); } .tri .w { background:#fff; border-top:1px solid #eee; border-bottom:1px solid #eee; } .tri .g { background:var(--green); }
.letterhead { display:flex; align-items:center; gap:14px; border-bottom:2px solid var(--navy); padding-bottom:10px; margin-bottom:12px; }
.letterhead img { width:66px; height:66px; object-fit:contain; }
.letterhead .h { flex:1; }
.letterhead .h .gi { font-size:10px; font-weight:700; letter-spacing:.2em; color:var(--saffron); text-transform:uppercase; }
.letterhead .h h1 { margin:2px 0 0; font-size:18px; font-weight:800; }
.letterhead .h h2 { margin:2px 0 0; font-size:14px; font-weight:700; }
.letterhead .h .sub { font-size:11px; color:#475569; margin-top:2px; }
.letterhead .tag { border:2px solid var(--navy); padding:5px 10px; text-align:center; }
.letterhead .tag .t1 { font-size:9px; letter-spacing:.08em; color:#64748b; }
.letterhead .tag .t2 { font-family:monospace; font-weight:800; font-size:12px; margin-top:2px; }
.ribbon { background:var(--navy); color:#fff; text-align:center; padding:7px; font-weight:700; letter-spacing:.08em; font-size:12px; }
.stu-block { display:flex; gap:18px; margin-top:14px; padding:14px; border:1px solid #e2e8f0; border-radius:3px; background:#f8fafc; }
.stu-block .photo, .stu-block .no-photo { width:100px; height:128px; flex-shrink:0; border:2px solid var(--navy); }
.stu-block .photo { object-fit:cover; }
.stu-block .no-photo { background:#e2e8f0; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:10px; text-align:center; padding:6px; border-style:dashed; }
.fld { margin-bottom:8px; }
.fld .l { font-size:9px; text-transform:uppercase; color:#64748b; letter-spacing:.08em; }
.fld .v { font-size:13px; font-weight:600; color:#0f172a; }
.fld.hero .v { font-size:16px; }
.score-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:10px; margin-top:14px; }
.score-card { border:1px solid #e2e8f0; border-radius:3px; padding:12px; text-align:center; }
.score-card .n { font-size:26px; font-weight:800; line-height:1; }
.score-card .l { font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:#64748b; margin-top:4px; }
.score-card.total { background:var(--navy); color:#fff; }
.score-card.correct { background:#f0fdf4; border-color:#86efac; } .score-card.correct .n { color:var(--green); }
.score-card.wrong { background:#fee2e2; border-color:#fca5a5; } .score-card.wrong .n { color:#b91c1c; }
.score-card.skipped { background:#fef3c7; border-color:#fde68a; } .score-card.skipped .n { color:#b45309; }
.verdict { margin-top:16px; padding:14px 18px; text-align:center; border-radius:3px; font-weight:800; font-size:18px; letter-spacing:.05em; }
.verdict.pass { background:#d1fae5; color:#065f46; border:2px solid var(--green); }
.verdict.fail { background:#fee2e2; color:#991b1b; border:2px solid #dc2626; }
.section-h { background:var(--navy); color:#fff; padding:7px 12px; margin:16px -4px 10px; font-weight:700; font-size:12px; letter-spacing:.08em; text-transform:uppercase; }
.violation-block { margin-top:10px; padding:10px; background:#fee2e2; border-left:4px solid #dc2626; font-size:11px; color:#991b1b; }
.violation-block ul { margin:4px 0 0 18px; padding:0; }
.qtable { width:100%; border-collapse:collapse; font-size:11px; margin-top:6px; }
.qtable th { background:var(--navy); color:#fff; padding:6px 8px; text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:.05em; }
.qtable td { padding:7px 8px; border-bottom:1px solid #e2e8f0; vertical-align:top; }
.qtable tr:nth-child(even) td { background:#f8fafc; }
.qtable .num { text-align:center; width:34px; font-weight:700; }
.qtable .res-c { color:var(--green); font-weight:700; }
.qtable .res-w { color:#b91c1c; font-weight:700; }
.qtable .res-s { color:#b45309; font-weight:600; font-style:italic; }
.qtable .ans { color:#0f172a; }
.qtable .correct { color:var(--green); font-weight:700; }
.foot { position:absolute; bottom:10mm; left:12mm; right:12mm; border-top:1px solid var(--navy); padding-top:6px; display:flex; justify-content:space-between; font-size:9px; color:#64748b; }
.sig-row { display:flex; gap:20px; margin-top:40px; }
.sig-row .sig { flex:1; text-align:center; font-size:10px; color:#64748b; }
.sig-row .sig .line { border-bottom:1px solid #475569; height:42px; margin-bottom:4px; }
.no-print { margin:0 auto 14px; max-width:900px; display:flex; justify-content:space-between; }
@media print {
  body { background:#fff; padding:0; }
  .page { box-shadow:none; margin:0; width:auto; min-height:auto; padding:0; }
  .no-print { display:none !important; }
}
</style></head><body>
<div class="no-print">
  <a href="<?= url('admin/attempt.php?id='.$aid) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Review</a>
  <button onclick="window.print()" class="btn btn-navy"><i class="fas fa-file-pdf me-1"></i>Print / Save as PDF</button>
</div>

<!-- =========== PAGE 1 — RESULT SUMMARY =========== -->
<div class="page">
  <div class="tri"><span class="o"></span><span class="w"></span><span class="g"></span></div>
  <div class="letterhead">
    <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" alt="BEL">
    <div class="h">
      <div class="gi">भारत सरकार · GOVERNMENT OF INDIA</div>
      <h1>भारत इलेक्ट्रॉनिक्स लिमिटेड</h1>
      <h2>Bharat Electronics Limited</h2>
      <div class="sub">कोटद्वार इकाई · Kotdwar Unit · Uttarakhand · Ministry of Defence · A Navratna Company</div>
    </div>
    <div class="tag">
      <div class="t1">Result / परिणाम</div>
      <div class="t2">BEL/<?= h($att['roll_number']) ?></div>
      <div class="t1 mt-1">Attempt #<?= (int)$att['attempt_no'] ?></div>
    </div>
  </div>
  <div class="ribbon">EXAMINATION RESULT · परीक्षा परिणाम</div>

  <div class="stu-block">
    <?php if ($photoUrl): ?>
      <img class="photo" src="<?= h($photoUrl) ?>" alt="">
    <?php else: ?>
      <div class="no-photo">अभ्यर्थी फोटो<br>Candidate Photo</div>
    <?php endif; ?>
    <div style="flex:1">
      <div class="fld hero"><div class="l">Candidate / अभ्यर्थी</div><div class="v"><?= h($att['sname']) ?></div></div>
      <div class="row"><div class="col-6">
        <div class="fld"><div class="l">Roll / Staff ID</div><div class="v" style="font-family:monospace"><?= h($att['roll_number']) ?></div></div>
        <div class="fld"><div class="l">Date of Birth</div><div class="v"><?= h($att['dob']) ?></div></div>
        <div class="fld"><div class="l">Category</div><div class="v"><?= strtoupper(h($att['category'])) ?></div></div>
      </div><div class="col-6">
        <div class="fld"><div class="l">Email</div><div class="v" style="font-size:11px"><?= h($att['semail']) ?></div></div>
        <div class="fld"><div class="l">Examination</div><div class="v"><?= h($att['exam_name']) ?> <?= $att['exam_code']?'<span class="text-muted">— '.h($att['exam_code']).'</span>':'' ?></div></div>
        <div class="fld"><div class="l">Submitted</div><div class="v"><?= fmt_dt($att['submitted_at']) ?></div></div>
      </div></div>
    </div>
  </div>

  <div class="section-h">Score Summary · अंक सारांश</div>
  <div class="score-grid">
    <div class="score-card total"><div class="n"><?= $att['score'] ?>/<?= $att['total'] ?></div><div class="l">Marks Obtained</div></div>
    <div class="score-card correct"><div class="n"><?= $correctN ?></div><div class="l">Correct</div></div>
    <div class="score-card wrong"><div class="n"><?= $wrongN ?></div><div class="l">Wrong</div></div>
    <div class="score-card skipped"><div class="n"><?= $skippedN ?></div><div class="l">Skipped</div></div>
  </div>

  <div class="verdict <?= $pass?'pass':'fail' ?>">
    <?= $pct ?>% · <?= $pass ? 'QUALIFIED / उत्तीर्ण' : 'NOT QUALIFIED / अनुत्तीर्ण' ?>
  </div>

  <div class="section-h">Examination Details · परीक्षा विवरण</div>
  <table style="width:100%; font-size:11px">
    <tr><td style="width:30%; color:#64748b">Total Questions</td><td><?= $totalQ ?></td>
        <td style="width:30%; color:#64748b">Duration</td><td><?= (int)$att['duration_minutes'] ?> min</td></tr>
    <tr><td style="color:#64748b">Attempted</td><td><?= $correctN + $wrongN ?> of <?= $totalQ ?></td>
        <td style="color:#64748b">Started</td><td><?= fmt_dt($att['started_at']) ?></td></tr>
    <tr><td style="color:#64748b">Hosted By</td><td><?= h($att['creator_name'] ?? '—') ?> <span class="text-muted" style="font-size:10px"><?= !empty($att['creator_email'])?'('.h($att['creator_email']).')':'' ?></span></td>
        <td style="color:#64748b">Ends At</td><td><?= fmt_dt($att['ends_at']) ?></td></tr>
  </table>

  <?php if ($violations): ?>
  <div class="violation-block">
    <b><i class="fas fa-triangle-exclamation"></i> Violations Detected (<?= count($violations) ?>)</b>
    <ul>
      <?php foreach ($violations as $v): ?>
        <li><b><?= h($v['event_type']) ?></b> — <?= h($v['description']) ?> <span style="color:#7f1d1d">[<?= date('H:i:s', strtotime($v['event_time'])) ?>]</span></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <div class="sig-row">
    <div class="sig"><div class="line"></div>Examiner Signature · परीक्षक हस्ताक्षर</div>
    <div class="sig"><div class="line"></div>Controller Stamp · नियंत्रक मुहर</div>
    <div class="sig"><div class="line"></div>Date · तिथि</div>
  </div>
  <div class="foot">
    <span>Issued by BEL Kotdwar · For authorised use only</span>
    <span>© <?= date('Y') ?> Bharat Electronics Limited · सर्वाधिकार सुरक्षित</span>
  </div>
</div>

<!-- =========== PAGE 2+ — ANSWER KEY =========== -->
<div class="page">
  <div class="tri"><span class="o"></span><span class="w"></span><span class="g"></span></div>
  <div class="letterhead">
    <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" alt="BEL">
    <div class="h">
      <div class="gi">BEL Kotdwar · Examination Portal</div>
      <h2 style="margin-top:4px">Answer Key &amp; Evaluation · उत्तर कुंजी एवं मूल्यांकन</h2>
      <div class="sub"><?= h($att['exam_name']) ?> · Candidate: <b><?= h($att['sname']) ?></b> (<?= h($att['roll_number']) ?>) · Attempt #<?= (int)$att['attempt_no'] ?></div>
    </div>
    <div class="tag">
      <div class="t1">Score</div>
      <div class="t2" style="font-size:14px"><?= $att['score'] ?>/<?= $att['total'] ?></div>
      <div class="t1 mt-1"><?= $pct ?>%</div>
    </div>
  </div>

  <table class="qtable">
    <thead><tr>
      <th class="num">#</th>
      <th style="width:34%">Question</th>
      <th style="width:24%">Student's Answer</th>
      <th style="width:24%">Correct Answer</th>
      <th style="width:8%">Marks</th>
      <th style="width:10%">Result</th>
    </tr></thead><tbody>
    <?php foreach ($questions as $i => $q): $aa = $ans[$q['id']] ?? null; $opts = $optsMap[$q['id']] ?? []; ?>
      <tr>
        <td class="num"><?= $i+1 ?></td>
        <td>
          <div><?= h($q['question_text']) ?></div>
          <?php if (!empty($q['question_text_hi'])): ?>
            <div style="color:#1e3a8a; font-size:10px; margin-top:3px" lang="hi"><?= h($q['question_text_hi']) ?></div>
          <?php endif; ?>
          <div style="color:#64748b; font-size:9px; margin-top:3px; text-transform:uppercase; letter-spacing:.04em"><?= str_replace('_',' ', $q['question_type']) ?> · <?= (float)$q['marks'] ?>m<?php if ((float)$q['negative_marks']>0) echo ' · −'.(float)$q['negative_marks']; ?></div>
        </td>
        <td class="ans"><?= fmtSel($q, $aa, $opts) ?></td>
        <td class="correct"><?= fmtCorrect($q, $opts) ?></td>
        <td style="text-align:center; font-weight:700">
          <?php
            if (!$aa || !$aa['selected_json']) echo '0';
            elseif ($aa['is_correct']) echo '+'.(float)$q['marks'];
            else echo (float)$q['negative_marks'] > 0 ? '−'.(float)$q['negative_marks'] : '0';
          ?>
        </td>
        <td>
          <?php if (!$aa || !$aa['selected_json']): ?><span class="res-s">Skipped</span>
          <?php elseif ($aa['is_correct']): ?><span class="res-c">✓ Correct</span>
          <?php else: ?><span class="res-w">✗ Wrong</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="foot">
    <span>Answer Key · <?= h($att['exam_name']) ?> · <?= h($att['sname']) ?></span>
    <span>Page auto-continues on print · © <?= date('Y') ?> BEL Kotdwar</span>
  </div>
</div>
</body></html>
