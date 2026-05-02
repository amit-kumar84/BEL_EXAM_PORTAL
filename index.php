<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/lang.php';
$PAGE_TITLE = t('brand') . ' — ' . t('brand_unit');
$PUBLIC = true;
require __DIR__ . '/includes/header.php';
?>
<section class="bg-grid py-5">
  <div class="container py-4">
    <div class="row g-5 align-items-center">
      <div class="col-lg-7">
        <div class="text-uppercase fw-bold small mb-2" style="color:var(--bel-blue); letter-spacing:.15em"><?= t('gov_in_hi') ?> · <?= t('gov_in') ?> · PSU</div>
        <h1 class="display-5 fw-bold mb-3"><?= t('hero_title') ?></h1>
        <p class="lead text-secondary mb-4"><?= t('hero_sub') ?></p>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= url('student/login.php') ?>" class="btn btn-navy btn-lg"><i class="fas fa-user-graduate me-2"></i><?= t('cta_student') ?></a>
          <a href="<?= url('admin/login.php') ?>" class="btn btn-admin btn-lg"><i class="fas fa-shield-alt me-2"></i><?= t('cta_admin') ?></a>
        </div>
        <div class="small text-secondary mt-3"><?= t('cta_note') ?></div>
      </div>
      <div class="col-lg-5">
        <div class="exam-card p-0 overflow-hidden" style="border:1px solid #e2e8f0; border-radius:3px">
          <div class="d-flex justify-content-between align-items-center px-4 py-3" style="background:var(--navy); color:#fff; border-bottom: 2px solid var(--saffron)">
            <span class="fw-bold">Examination Console</span>
            <span class="exam-timer low d-inline-flex align-items-center gap-2" id="demo-countdown" style="font-size:14px; padding:4px 10px">
              <i class="fas fa-clock"></i>
              <span>59:42</span>
            </span>
          </div>
          <div class="p-3 d-flex flex-wrap justify-content-center">
            <?php $states = array_merge(array_fill(0, 9, 'answered'), array_fill(0, 3, 'marked'), array_fill(0, 2, 'not-answered'), array_fill(0, 2, 'ans-marked'), array_fill(0, 9, '')); ?>
            <?php for ($i = 1; $i <= 25; $i++): ?>
              <span class="palette-btn <?= $states[$i-1] ?? '' ?>"><?= $i ?></span>
            <?php endfor; ?>
          </div>
          <div class="p-3 bg-light d-grid gap-1" style="grid-template-columns: 1fr 1fr; font-size: 12px">
            <div><span class="palette-btn answered" style="width:14px;height:14px;font-size:0;margin:0 6px 0 0"></span>Answered</div>
            <div><span class="palette-btn not-answered" style="width:14px;height:14px;font-size:0;margin:0 6px 0 0"></span>Not Answered</div>
            <div><span class="palette-btn marked" style="width:14px;height:14px;font-size:0;margin:0 6px 0 0"></span>Marked</div>
            <div><span class="palette-btn ans-marked" style="width:14px;height:14px;font-size:0;margin:0 6px 0 0"></span>Ans + Mark</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
(function () {
  const timer = document.getElementById('demo-countdown');
  if (!timer) return;

  const valueEl = timer.querySelector('span:last-child');
  const startSeconds = 59 * 60 + 42;
  let remaining = startSeconds;

  function render() {
    const minutes = String(Math.floor(remaining / 60)).padStart(2, '0');
    const seconds = String(remaining % 60).padStart(2, '0');
    valueEl.textContent = `${minutes}:${seconds}`;
  }

  render();
  setInterval(() => {
    remaining -= 1;
    if (remaining < 0) remaining = startSeconds;
    render();
  }, 1000);
})();
</script>

<section class="bg-white py-5 border-top">
  <div class="container">
    <div class="row g-4 mb-5">
      <div class="col-lg-8">
        <h2 class="fw-bold"><?= t('about_h') ?></h2>
        <p class="text-secondary mt-3"><?= t('about_p1') ?></p>
        <p class="text-secondary"><?= t('about_p2') ?></p>
      </div>
      <div class="col-lg-4">
        <div class="p-4" style="background:#f8fafc; border:1px solid #e2e8f0;">
          <div class="fw-bold small text-uppercase mb-3" style="color:var(--bel-blue); letter-spacing:.12em"><?= t('quick_facts') ?></div>
          <ul class="list-unstyled small mb-0">
            <li>• Established: <b>1954</b></li>
            <li>• Status: <b>Navratna PSU</b></li>
            <li>• Ministry: <b>Defence (MoD), GoI</b></li>
            <li>• HQ: <b>Bengaluru, Karnataka</b></li>
            <li>• Unit: <b>Kotdwar, Uttarakhand</b></li>
            <li>• ISO 9001 / AS 9100D certified</li>
          </ul>
        </div>
      </div>
    </div>
    <div class="row g-4">
      <?php foreach ([['lock','feat1_h','feat1_t'],['microchip','feat2_h','feat2_t'],['clipboard-check','feat3_h','feat3_t']] as $f): ?>
        <div class="col-md-4">
          <div class="exam-card h-100">
            <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width:40px; height:40px; background:var(--navy); color:#fff; border-radius:3px"><i class="fas fa-<?= $f[0] ?>"></i></div>
            <h5 class="fw-bold"><?= t($f[1]) ?></h5>
            <p class="text-secondary small"><?= t($f[2]) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
