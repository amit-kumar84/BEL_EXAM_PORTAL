<?php
// admin/export-classroom-pdf.php — Print-ready A4 attendance sheet with summary header,
// student rows (name, DOB, status, violations) and signature columns for student + invigilator.
require_once __DIR__ . '/../includes/helpers.php';
$me = require_login('admin');
ensure_phase3_migrations();

$eid = (int)($_GET['exam_id'] ?? 0);
$ex = db()->prepare('SELECT e.*, u.name AS host_name, u.email AS host_email FROM exams e LEFT JOIN users u ON u.id=e.created_by WHERE e.id=? AND e.deleted_at IS NULL');
$ex->execute([$eid]);
$exam = $ex->fetch();
if (!$exam) die('Exam not found');
$access = exam_access_for($eid, $me);
if (!$access) die('Forbidden');

$st = db()->prepare(
  'SELECT u.name, u.roll_number, u.dob, u.photo_path, u.category,
          a.status, a.started_at, a.submitted_at, a.score, a.total,
          (SELECT COUNT(*) FROM violations WHERE user_id=u.id AND attempt_id=a.id) AS violations
     FROM users u
     JOIN exam_assignments ea ON ea.user_id=u.id
     LEFT JOIN attempts a ON a.user_id=u.id AND a.exam_id=? AND a.id=(SELECT MAX(id) FROM attempts WHERE user_id=u.id AND exam_id=?)
    WHERE ea.exam_id=? AND u.deleted_at IS NULL
    ORDER BY u.roll_number, u.name');
$st->execute([$eid, $eid, $eid]);
$rows = $st->fetchAll();

$counts = ['registered'=>count($rows), 'writing'=>0, 'submitted'=>0, 'absent'=>0, 'violations'=>0];
foreach ($rows as $r) {
    if ($r['status'] === 'in_progress') $counts['writing']++;
    elseif ($r['status'] === 'submitted') $counts['submitted']++;
    else $counts['absent']++;
    $counts['violations'] += (int)$r['violations'];
}

// Split into pages of 25 students each so signature column fits cleanly on A4 portrait
$perPage = 25;
$pages = $rows ? array_chunk($rows, $perPage) : [[]];
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Attendance Sheet · <?= h($exam['exam_name']) ?></title>
<link rel="stylesheet" href="<?= url('assets/lib/bootstrap/css/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= url('assets/lib/fontawesome/css/all.min.css') ?>">
<style>
@page { size:A4; margin:10mm 8mm; }
* { box-sizing:border-box; }
body { font-family:'Segoe UI',sans-serif; color:#0f172a; background:#f1f5f9; padding:20px; margin:0; }
:root { --navy:#0E2A47; --saffron:#FF9933; --green:#138808; }
.no-print { max-width:1000px; margin:0 auto 14px; display:flex; justify-content:space-between; }
.page { background:#fff; width:210mm; min-height:297mm; margin:0 auto 18px; padding:10mm 8mm; box-shadow:0 2px 10px rgba(15,23,42,.08); page-break-after:always; }
.page:last-child { page-break-after:auto; }
.tri { display:flex; height:4px; margin:0 -8mm 8px; } .tri span { flex:1; }
.tri .o { background:var(--saffron); } .tri .w { background:#fff; border-top:1px solid #eee; border-bottom:1px solid #eee; } .tri .g { background:var(--green); }
.head { display:flex; align-items:center; gap:10px; border-bottom:2px solid var(--navy); padding-bottom:6px; margin-bottom:8px; }
.head img { width:54px; height:54px; object-fit:contain; }
.head .h { flex:1; }
.head .h .gi { font-size:9px; font-weight:700; letter-spacing:.18em; color:var(--saffron); text-transform:uppercase; }
.head .h h1 { margin:1px 0 0; font-size:14px; font-weight:800; }
.head .h h2 { margin:1px 0 0; font-size:11px; font-weight:700; }
.head .h .sub { font-size:9px; color:#475569; margin-top:1px; }
.head .tag { border:2px solid var(--navy); padding:4px 8px; text-align:center; }
.head .tag .t1 { font-size:8px; letter-spacing:.06em; color:#64748b; }
.head .tag .t2 { font-family:monospace; font-weight:800; font-size:11px; }

.banner { background:var(--navy); color:#fff; text-align:center; padding:5px; font-weight:700; letter-spacing:.06em; font-size:11px; margin-bottom:8px; }
.summary { display:grid; grid-template-columns:repeat(4,1fr); gap:6px; margin-bottom:8px; }
.summary div { padding:6px 8px; background:#f8fafc; border:1px solid #e2e8f0; font-size:10px; }
.summary div b { font-size:14px; display:block; line-height:1; color:var(--navy); }
.summary .lbl { font-size:8px; letter-spacing:.06em; text-transform:uppercase; color:#64748b; margin-top:1px; }

.meta-row { display:grid; grid-template-columns:1fr 1fr 1fr; gap:6px; font-size:9px; margin-bottom:6px; padding:4px 8px; background:#f8fafc; border-left:3px solid var(--navy); }
.meta-row b { color:var(--navy); }

table.attn { width:100%; border-collapse:collapse; font-size:9px; }
table.attn th { background:var(--navy); color:#fff; padding:4px 5px; text-align:left; font-size:8px; letter-spacing:.04em; text-transform:uppercase; }
table.attn td { padding:5px; border-bottom:1px solid #e2e8f0; vertical-align:middle; }
table.attn tr:nth-child(even) td { background:#f8fafc; }
table.attn .num { width:24px; text-align:center; font-weight:700; }
table.attn .name { font-weight:600; }
table.attn .dob { font-family:monospace; font-size:9px; }
table.attn .roll { font-family:monospace; font-size:9px; }
table.attn .cat { font-size:8px; color:#64748b; text-transform:uppercase; }
table.attn .status { font-weight:700; font-size:9px; text-align:center; padding:2px 5px; }
.status.s-PRESENT { color:var(--green); background:#f0fdf4; }
.status.s-ABSENT { color:#b91c1c; background:#fef2f2; }
.status.s-WRITING { color:#b45309; background:#fffbeb; }
table.attn .viol { text-align:center; font-weight:700; font-size:10px; }
table.attn .sig-cell { width:90px; height:34px; border:1px dashed #cbd5e1; }

.foot-sig { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; margin-top:14px; padding-top:8px; border-top:1px solid var(--navy); }
.foot-sig .s { text-align:center; font-size:9px; color:#64748b; }
.foot-sig .s .line { border-bottom:1.5px solid #475569; height:36px; margin-bottom:3px; }
.foot-sig .s b { color:#0f172a; }

.foot { display:flex; justify-content:space-between; font-size:8px; color:#64748b; margin-top:6px; }

@media print { body { background:#fff; padding:0; } .no-print { display:none !important; } .page { box-shadow:none; margin:0; width:auto; min-height:auto; } }
</style></head><body>
<div class="no-print">
  <a href="<?= url('admin/monitor-exam.php?exam_id='.$eid) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Monitor</a>
  <button onclick="window.print()" class="btn btn-navy"><i class="fas fa-print me-1"></i>Print / Save as PDF</button>
</div>

<?php foreach ($pages as $pi => $pageRows): ?>
<div class="page">
  <div class="tri"><span class="o"></span><span class="w"></span><span class="g"></span></div>
  <div class="head">
    <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" alt="BEL">
    <div class="h">
      <div class="gi">भारत सरकार · GOVERNMENT OF INDIA</div>
      <h1>भारत इलेक्ट्रॉनिक्स लिमिटेड · Bharat Electronics Limited</h1>
      <h2>Kotdwar Unit · Uttarakhand · Ministry of Defence · A Navratna Company</h2>
      <div class="sub">Examination Attendance Sheet · परीक्षा उपस्थिति पत्रक</div>
    </div>
    <div class="tag">
      <div class="t1">Page</div>
      <div class="t2"><?= ($pi+1) ?> / <?= count($pages) ?></div>
      <div class="t1 mt-1">Code</div>
      <div class="t2" style="font-size:10px"><?= h($exam['exam_code']) ?></div>
    </div>
  </div>

  <?php if ($pi === 0): ?>
    <div class="banner">EXAMINATION ATTENDANCE RECORD &middot; <?= h($exam['exam_name']) ?></div>
    <div class="meta-row">
      <div><b>Examination:</b> <?= h($exam['exam_name']) ?></div>
      <div><b>Window:</b> <?= fmt_dt($exam['start_time']) ?> &rarr; <?= fmt_dt($exam['end_time']) ?></div>
      <div><b>Duration:</b> <?= (int)$exam['duration_minutes'] ?> min</div>
      <div><b>Hosted By:</b> <?= h($exam['host_name'] ?? '—') ?></div>
      <div><b>Host Email:</b> <?= h($exam['host_email'] ?? '—') ?></div>
      <div><b>Generated:</b> <?= date('d M Y, H:i') ?> by <?= h($me['name']) ?></div>
    </div>
    <div class="summary">
      <div><b><?= $counts['registered'] ?></b><div class="lbl">Registered</div></div>
      <div><b style="color:#16a34a"><?= $counts['submitted'] ?></b><div class="lbl">Submitted (Present)</div></div>
      <div><b style="color:#b45309"><?= $counts['writing'] ?></b><div class="lbl">Writing</div></div>
      <div><b style="color:#dc2626"><?= $counts['absent'] ?></b><div class="lbl">Absent</div></div>
    </div>
  <?php else: ?>
    <div class="banner">Continued — <?= h($exam['exam_name']) ?> · Page <?= $pi+1 ?></div>
  <?php endif; ?>

  <table class="attn">
    <thead><tr>
      <th class="num">#</th>
      <th>Candidate Name</th>
      <th>Roll / Staff ID</th>
      <th>DOB</th>
      <th>Category</th>
      <th>Status</th>
      <th>Violations</th>
      <th style="width:90px">Student Signature</th>
    </tr></thead><tbody>
    <?php foreach ($pageRows as $i => $r):
      $status = !$r['status'] ? 'ABSENT' : ($r['status'] === 'submitted' ? 'PRESENT' : 'WRITING');
      $rowNum = $pi*$perPage + $i + 1; ?>
      <tr>
        <td class="num"><?= $rowNum ?></td>
        <td class="name"><?= h($r['name']) ?></td>
        <td class="roll"><?= h($r['roll_number']) ?></td>
        <td class="dob"><?= h($r['dob']) ?></td>
        <td class="cat"><?= strtoupper(h($r['category'])) ?></td>
        <td class="status s-<?= $status ?>"><?= $status ?></td>
        <td class="viol"><?= (int)$r['violations'] ? (int)$r['violations'] : '—' ?></td>
        <td class="sig-cell"></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$pageRows): ?>
      <tr><td colspan="8" class="text-center text-muted py-4">No registered students.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <?php if ($pi === count($pages)-1): ?>
  <div class="foot-sig">
    <div class="s"><div class="line"></div><b>Invigilator Signature</b><br>निरीक्षक हस्ताक्षर</div>
    <div class="s"><div class="line"></div><b>Examination Officer</b><br>परीक्षा अधिकारी</div>
    <div class="s"><div class="line"></div><b>Date &amp; Stamp</b><br>दिनांक एवं मुहर</div>
  </div>
  <?php endif; ?>
  <div class="foot">
    <span>BEL Kotdwar · Examination Attendance Record · For authorised use only</span>
    <span>© <?= date('Y') ?> Bharat Electronics Limited · Page <?= $pi+1 ?> / <?= count($pages) ?></span>
  </div>
</div>
<?php endforeach; ?>
</body></html>
