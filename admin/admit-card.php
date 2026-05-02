<?php
require_once __DIR__ . '/../includes/helpers.php'; require_login('admin');
$id = (int)($_GET['id'] ?? 0);
$s = db()->prepare('SELECT * FROM users WHERE id=? AND role="student"'); $s->execute([$id]); $stu = $s->fetch();
if (!$stu) die('Student not found');
// Show only exams assigned to this candidate (strict mode)
$assigned = assigned_exam_ids((int)$stu['id']);
if ($assigned) {
    $in = str_repeat('?,', count($assigned) - 1) . '?';
    $es = db()->prepare("SELECT * FROM exams WHERE id IN ($in) ORDER BY start_time");
    $es->execute($assigned);
    $exams = $es->fetchAll();
} else {
    $exams = [];
}
// Use local QR code generator endpoint (offline-friendly)
// QR encodes full URL to lookup page for easy scanning
$qrData = url('api/qr-lookup.php?data=' . urlencode('BEL-KOTDWAR|'.$stu['roll_number'].'|'.$stu['id']));
$qr = url('api/qrcode.php?size=120&data=' . urlencode($qrData));
$photoUrl = !empty($stu['photo_path']) ? url($stu['photo_path']) : '';
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Hall Ticket · <?= h($stu['name']) ?></title>
<!-- Bootstrap 5.3.2 - Local Offline Copy -->
<link rel="stylesheet" href="<?= url('assets/lib/bootstrap/css/bootstrap.min.css') ?>">
<!-- Custom Application Styles -->
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head><body style="background:#f1f5f9; padding:20px">
<div class="container" style="max-width:900px">
<div class="no-print d-flex justify-content-between mb-3">
  <a href="<?= url('admin/students.php') ?>" class="btn btn-outline-secondary btn-sm">← Back</a>
  <button onclick="window.print()" class="btn btn-navy"><i class="fas fa-print me-1"></i>Print / Save as PDF</button>
</div>
<div class="admit-card">
  <div class="tricolor"><span></span><span></span><span></span></div>
  <div class="d-flex gap-3 p-4 border-bottom border-2 border-dark" style="border-color:var(--navy)!important">
    <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" style="width:80px; height:80px; object-fit:contain" alt="BEL">
    <div class="flex-grow-1">
      <div class="small fw-bold text-uppercase" style="color:var(--saffron); letter-spacing:.15em">भारत सरकार · GOVERNMENT OF INDIA</div>
      <h3 class="fw-bold mt-1 mb-0">भारत इलेक्ट्रॉनिक्स लिमिटेड</h3>
      <h4 class="fw-bold mb-0">Bharat Electronics Limited</h4>
      <div class="small text-secondary">कोटद्वार इकाई · Kotdwar Unit · Uttarakhand · Ministry of Defence</div>
    </div>
    <div class="text-end"><div style="border:2px solid var(--navy); padding:6px 12px"><div class="small text-muted">Hall Ticket / प्रवेश पत्र</div><div class="font-monospace fw-bold">BEL/<?= h($stu['roll_number']) ?></div></div></div>
  </div>
  <div style="background:var(--navy); color:#fff; text-align:center; padding:8px; letter-spacing:.06em" class="fw-semibold">ONLINE EXAMINATION ADMIT CARD · ऑनलाइन परीक्षा प्रवेश पत्र</div>

  <div class="p-4 d-flex gap-3">
    <div class="flex-grow-1 row">
      <?php foreach ([
        ['Candidate Name','अभ्यर्थी का नाम',$stu['name'],'fw-bold'],
        ['Roll / Staff ID','रोल / स्टाफ़ आईडी',$stu['roll_number'],'font-monospace'],
        ['Date of Birth','जन्म तिथि',$stu['dob'],''],
        ['Category','श्रेणी',strtoupper($stu['category']),''],
        ['Email','ईमेल',$stu['email'],''],
        ['Username','उपयोगकर्ता नाम',$stu['username'],'font-monospace'],
        ['Password','पासवर्ड', $stu['plain_password'] ?: '— (reset in admin)','font-monospace fw-bold'],
      ] as $f): ?>
        <div class="col-6 mb-3"><div class="small text-uppercase text-muted" style="letter-spacing:.08em; font-size:10px"><?= $f[0] ?> · <?= $f[1] ?></div><div class="<?= $f[3] ?>"><?= h($f[2]) ?></div></div>
      <?php endforeach; ?>
    </div>
    <div class="text-center" style="width:170px">
      <?php if ($photoUrl): ?>
        <img src="<?= h($photoUrl) ?>" alt="Candidate Photo" style="width:110px; height:140px; object-fit:cover; border:2px solid var(--navy); margin:0 auto; display:block">
      <?php else: ?>
        <div style="width:110px; height:140px; border:2px dashed #94a3b8; display:flex; align-items:center; justify-content:center; font-size:10px; color:#64748b; text-align:center; margin:0 auto">अभ्यर्थी का फोटो<br>Candidate Photo<br><small>(paste here)</small></div>
      <?php endif; ?>
      <img src="<?= h($qr) ?>" style="width:100px; height:100px; border:1px solid #cbd5e1; margin-top:8px">
    </div>
  </div>

  <div class="px-4 pb-3">
    <h6 class="fw-bold border-bottom pb-1">SCHEDULED EXAMINATIONS · निर्धारित परीक्षाएँ</h6>
    <table class="table table-sm small"><thead><tr><th>Examination</th><th>Date & Time</th><th>Duration</th></tr></thead><tbody>
    <?php foreach ($exams as $e): ?><tr><td><?= h($e['exam_name']) ?></td><td><?= date('d M Y, H:i', strtotime($e['start_time'])) ?></td><td><?= (int)$e['duration_minutes'] ?> min</td></tr><?php endforeach; ?>
    <?php if (!$exams): ?><tr><td colspan="3" class="text-center text-muted">No examinations assigned to this candidate</td></tr><?php endif; ?>
    </tbody></table>
  </div>

  <div class="px-4 pb-3">
    <h6 class="fw-bold border-bottom pb-1">INSTRUCTIONS · निर्देश</h6>
    <ol class="small" style="font-size:11px">
      <li>Carry this admit card and a Government-issued photo ID to the examination centre. / इस प्रवेश पत्र और सरकारी फ़ोटो पहचान पत्र को परीक्षा केंद्र पर लाएँ।</li>
      <li>Login uses Roll/Staff ID, Date of Birth and the password issued by BEL. / लॉगिन हेतु रोल/स्टाफ़ आईडी, जन्म तिथि तथा BEL द्वारा जारी पासवर्ड का उपयोग करें।</li>
      <li>Examination runs in lockdown mode — fullscreen mandatory, tab switching, copy/paste and right-click are blocked. / परीक्षा लॉकडाउन मोड में होगी — पूर्ण स्क्रीन अनिवार्य है।</li>
      <li>Webcam will remain on for proctoring. After <?= MAX_VIOLATIONS ?> violations the exam auto-submits. / प्रॉक्टरिंग हेतु वेबकैम चालू रहेगा।</li>
      <li>Report at the centre 30 minutes before exam start. / परीक्षा प्रारंभ से 30 मिनट पूर्व केंद्र पर पहुँचें।</li>
    </ol>
  </div>

  <div class="px-4 pb-3 d-flex gap-4">
    <?php foreach ([['अभ्यर्थी हस्ताक्षर / Candidate Signature'],['निरीक्षक हस्ताक्षर / Invigilator'],['नियंत्रक मुहर / Controller Stamp']] as $sig): ?>
      <div class="flex-grow-1 text-center"><div style="border-bottom:1px solid #64748b; height:48px"></div><small class="text-muted" style="font-size:10px"><?= $sig[0] ?></small></div>
    <?php endforeach; ?>
  </div>

  <div style="background:var(--navy); color:#fff; text-align:center; padding:6px; font-size:10px; letter-spacing:.08em">
    Issued by BEL Kotdwar · Authorised use only · सर्वाधिकार सुरक्षित © <?= date('Y') ?> Bharat Electronics Limited
  </div>
</div>
</div></body></html>
