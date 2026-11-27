<?php
declare(strict_types=1);

/** @var array $page */

$admin = App\Admin::current();
$sections = [
    'dashboard' => ['/admin/', 'Dashboard'],
    'films' => ['/admin/films.php', 'Films'],
];
?>
<header class="adm-top">
  <a class="wm" href="/admin/" aria-label="Cinemax admin home"><b>CENTURY</b><span>CINEMAX</span></a>
  <span class="adm-badge">Admin</span>
  <?php if ($admin): ?>
    <nav class="adm-nav" aria-label="Admin">
      <?php foreach ($sections as $key => [$href, $label]): ?>
        <a href="<?= $href ?>"<?= $page['active'] === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="adm-me">
      <a href="/" target="_blank" rel="noopener">View site</a>
      <form method="post" action="/admin/logout.php"><?= csrf_field() ?><button type="submit">Log out <span class="sr"><?= e($admin['email']) ?></span></button></form>
    </div>
  <?php endif; ?>
</header>
