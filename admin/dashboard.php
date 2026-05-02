<?php $ADMIN_TITLE = 'Live Control Panel'; require __DIR__ . '/_shell_top.php';
$pdo = db();
$totals = [
  'students' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE role="student"')->fetchColumn(),
  'exams'    => (int)$pdo->query('SELECT COUNT(*) FROM exams')->fetchColumn(),
  'live'     => (int)$pdo->query('SELECT COUNT(*) FROM attempts WHERE status="in_progress"')->fetchColumn(),
  'today'    => (int)$pdo->query('SELECT COUNT(*) FROM attempts WHERE status="submitted" AND DATE(submitted_at)=CURDATE()')->fetchColumn(),
];
$active = $pdo->query('SELECT e.id, e.exam_name, e.end_time, (SELECT COUNT(*) FROM attempts WHERE exam_id=e.id AND status="in_progress") live FROM exams e WHERE NOW() BETWEEN e.start_time AND e.end_time')->fetchAll();
$hot = $pdo->query('SELECT u.name, u.roll_number, COUNT(v.id) c FROM violations v JOIN users u ON u.id=v.user_id GROUP BY v.user_id ORDER BY c DESC LIMIT 5')->fetchAll();
$avg = $pdo->query('SELECT e.exam_name, AVG(a.score/a.total)*100 pct, COUNT(a.id) n FROM attempts a JOIN exams e ON e.id=a.exam_id WHERE a.status="submitted" AND a.total>0 GROUP BY a.exam_id ORDER BY MAX(a.submitted_at) DESC LIMIT 5')->fetchAll();
$live = $pdo->query('SELECT a.id, a.ends_at, u.name, u.roll_number, e.exam_name, (SELECT COUNT(*) FROM violations WHERE attempt_id=a.id) vcount FROM attempts a JOIN users u ON u.id=a.user_id JOIN exams e ON e.id=a.exam_id WHERE a.status="in_progress" LIMIT 20')->fetchAll();
?>
<div class="exam-card mb-3 p-0 overflow-hidden">
  <div class="tricolor"><span></span><span></span><span></span></div>
  <div class="d-flex align-items-center justify-content-between gap-3 p-3 p-md-4 flex-wrap" style="background:#fff">
    <div class="d-flex align-items-center gap-3">
      <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" alt="BEL" style="width:130px; height:72px; object-fit:contain">
    </div>
    <div class="text-center flex-grow-1" style="min-width:260px">
      <h1 class="fw-bold mb-1" style="font-size:clamp(1.4rem, 2.8vw, 3rem); line-height:1.05">Bharat Electronics Limited</h1>
      <div class="fw-semibold" style="font-size:clamp(.95rem, 1.3vw, 1.1rem)">Government of India, Ministry of Defence, A Navratna Company</div>
      <div class="fw-bold mt-2" style="font-size:clamp(.95rem, 1.2vw, 1.05rem)">Kotdwar Unit Dashboard</div>
    </div>
    <div class="text-end d-none d-lg-block">
      <div class="border border-2 border-dark rounded-circle d-inline-flex align-items-center justify-content-center" style="width:82px; height:82px; background:#f8fafc;">
        <div class="small fw-bold text-center" style="line-height:1.1">BEL<br>ADMIN</div>
      </div>
    </div>
  </div>
</div>
<div class="d-flex justify-content-between mb-3">
  <p class="text-secondary small mb-0">Auto-refresh every 10 seconds · <?= date('H:i:s') ?></p>
  <span class="badge bg-success"><span class="pulse-dot"></span> LIVE</span>
</div>
<div class="row g-3">
  <?php foreach ([
    ['Total Students',$totals['students'],'fa-users','text-primary','students.php'],
    ['Exams',$totals['exams'],'fa-book-open','text-success','exams.php'],
    ['Live Attempts',$totals['live'],'fa-bolt','text-warning',null],
    ['Submitted Today',$totals['today'],'fa-paper-plane','text-danger','results.php'],
  ] as $c): ?>
  <div class="col-md-3">
    <?php $inner = '<div class="exam-card h-100"><div class="'.$c[3].' mb-2"><i class="fas '.$c[2].' fa-lg"></i></div><div class="display-6 fw-bold">'.$c[1].'</div><div class="small text-uppercase text-secondary">'.$c[0].'</div></div>'; ?>
    <?= $c[4] ? '<a href="'.url('admin/'.$c[4]).'" class="text-decoration-none text-dark">'.$inner.'</a>' : $inner ?>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mt-2">
  <div class="col-lg-6">
    <div class="exam-card">
      <h6 class="border-bottom pb-2 mb-3"><i class="far fa-clock me-2"></i>Active Examination Windows</h6>
      <?php if (!$active): ?><p class="text-muted text-center small py-3">No exam in active window.</p>
      <?php else: ?><table class="data-table"><thead><tr><th>Exam</th><th>Live</th><th>Closes</th></tr></thead><tbody>
        <?php foreach ($active as $a): ?><tr><td><?= h($a['exam_name']) ?></td><td><span class="badge bg-warning text-dark"><?= (int)$a['live'] ?></span></td><td class="small text-muted"><?= fmt_dt($a['end_time']) ?></td></tr><?php endforeach; ?>
      </tbody></table><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="exam-card">
      <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-triangle-exclamation me-2"></i>Violation Hotspots</h6>
      <?php if (!$hot): ?><p class="text-muted text-center small py-3">No violations recorded.</p>
      <?php else: ?><ul class="list-unstyled mb-0"><?php foreach ($hot as $h): ?>
        <li class="d-flex justify-content-between border-bottom py-1"><span><b><?= h($h['name']) ?></b> <small class="text-muted"><?= h($h['roll_number']) ?></small></span><span class="badge bg-danger"><?= (int)$h['c'] ?></span></li>
      <?php endforeach; ?></ul><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="exam-card">
      <h6 class="border-bottom pb-2 mb-3">Recent Average Scores</h6>
      <?php if (!$avg): ?><p class="text-muted text-center small py-3">No submissions yet.</p>
      <?php else: foreach ($avg as $a): $pct = round((float)$a['pct'], 1); ?>
        <div class="mb-2"><div class="d-flex justify-content-between small"><span class="fw-medium"><?= h($a['exam_name']) ?></span><span class="text-muted"><?= $pct ?>% · <?= (int)$a['n'] ?> attempts</span></div>
          <div class="progress" style="height:8px"><div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div></div></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="exam-card">
      <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-bolt me-2"></i>Currently Writing</h6>
      <?php if (!$live): ?><p class="text-muted text-center small py-3">Nobody is writing now.</p>
      <?php else: ?><table class="data-table"><thead><tr><th>Candidate</th><th>Exam</th><th>Viol.</th><th>Ends</th></tr></thead><tbody>
        <?php foreach ($live as $l): ?><tr><td><b><?= h($l['name']) ?></b> <small class="text-muted"><?= h($l['roll_number']) ?></small></td><td class="small"><?= h($l['exam_name']) ?></td><td><span class="badge <?= $l['vcount']>=3?'bg-danger':($l['vcount']>0?'bg-warning text-dark':'bg-secondary') ?>"><?= (int)$l['vcount'] ?></span></td><td class="small text-muted"><?= date('H:i', strtotime($l['ends_at'])) ?></td></tr><?php endforeach; ?>
      </tbody></table><?php endif; ?>
    </div>
  </div>
</div>
<script>setTimeout(() => location.reload(), 10000);</script>
<style>.pulse-dot{display:inline-block;width:8px;height:8px;background:#fff;border-radius:50%;margin-right:4px;animation:pulse 1.5s infinite}</style>
<?php require __DIR__ . '/_shell_bottom.php'; ?>
