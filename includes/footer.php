<?php $PUBLIC = $PUBLIC ?? false; ?>
<?php if ($PUBLIC): ?>
<footer class="site-footer">
  <div class="tricolor"><span></span><span></span><span></span></div>
  <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2 py-1">
    <div class="d-flex align-items-center gap-3">
      <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" class="footer-logo" alt="BEL">
      <div>
        <div class="fw-bold text-white"><?= t('brand') ?></div>
        <div class="small text-secondary">© <?= date('Y') ?> · <?= t('footer_enterprise') ?></div>
      </div>
    </div>
    <small class="text-secondary"><?= t('footer_auth') ?></small>
  </div>
</footer>
<?php endif; ?>
<!-- Bootstrap 5.3.2 JS - Local Offline Copy -->
<script src="<?= url('assets/lib/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script>
  (() => {
    const defaultDelay = 5000;
    document.querySelectorAll('.flash-message').forEach((message) => {
      const delay = parseInt(message.dataset.autoDismiss || defaultDelay, 10);
      window.setTimeout(() => {
        message.classList.remove('show');
        window.setTimeout(() => message.remove(), 350);
      }, delay);
    });
  })();
</script>
</body></html>
