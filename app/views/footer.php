<?php
declare(strict_types=1);

/** @var array $page */

$flashes = take_flashes();
?>
<?php if ($page['footer']): ?>
<footer class="foot">
  <div>
    <?= wordmark() ?>
    <p>A concept redesign of Century Cinemax’s booking site, made as a portfolio project and not affiliated with Century Cinemax. No payments are taken and bookings are for demonstration only. Film data and images from TMDB. This product uses the TMDB API but is not endorsed or certified by TMDB.</p>
  </div>
  <div>
    <h2>Films</h2>
    <ul>
      <li><a href="/#now">Now showing</a></li>
      <li><a href="/#soon">Coming soon</a></li>
      <li><a href="/#cinemas">3D and Dolby Atmos</a></li>
    </ul>
  </div>
  <div>
    <h2>Cinemas</h2>
    <ul>
      <?php foreach (site_cinemas() as $cinema): ?>
        <li><a href="/#cinemas"><?= e($cinema['name']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div>
    <h2>Help</h2>
    <ul>
      <li><a href="/find-booking.php">Find my booking</a></li>
      <li><a href="/contact.php?topic=changes">Refunds and changes</a></li>
      <li><a href="/contact.php">Contact us</a></li>
    </ul>
  </div>
</footer>
<?php endif; ?>
<div id="layer">
  <div class="toasts" role="status" aria-live="polite">
    <?php foreach ($flashes as $flash): ?>
      <div class="toast<?= $flash['type'] === 'error' ? ' bad' : '' ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
