<?php
declare(strict_types=1);

use App\Cinemas;

/** @var array $page */

$cinemas = site_cinemas();
$current = $cinemas ? Cinemas::current() : null;
try {
    $user = App\Users::current();
} catch (PDOException) {
    $user = null; // error pages still draw a header when the database is down
}

$nav = ['now' => 'Now showing', 'soon' => 'Coming soon', 'cinemas' => 'Cinemas', 'offers' => 'Offers'];
$returnTo = local_path($_SERVER['REQUEST_URI'] ?? '/');
?>
<header class="top<?= $page['header'] !== '' ? ' ' . e($page['header']) : '' ?>">
  <?= wordmark() ?>
  <nav class="nav" aria-label="Main">
    <?php foreach ($nav as $key => $label): ?>
      <a href="/#<?= $key ?>"<?= $page['active'] === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <span class="vbar" aria-hidden="true"></span>
  <div class="tools">
    <?php if ($cinemas): ?>
      <form class="loc" method="post" action="/cinema.php" data-autosubmit>
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= e($returnTo) ?>">
        <?= icon('pin') ?>
        <label class="sr" for="cinema-pick">Cinema</label>
        <select id="cinema-pick" name="cinema">
          <?php foreach ($cinemas as $cinema): ?>
            <option value="<?= e($cinema['slug']) ?>"<?= $current && $cinema['id'] === $current['id'] ? ' selected' : '' ?>><?= e($cinema['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="chev"><?= icon('chev') ?></span>
        <noscript><button class="loc-go" type="submit">Go</button></noscript>
      </form>
    <?php endif; ?>
    <form class="search" role="search" method="get" action="/search.php">
      <label class="sr" for="q">Search films or actors</label>
      <input id="q" name="q" type="search" placeholder="Search films or actors" autocomplete="off" maxlength="80">
      <button class="icon" type="submit" aria-label="Search" aria-controls="q" aria-expanded="false"><?= icon('search') ?></button>
      <div class="results" id="results" hidden></div>
    </form>
    <a class="icon acct" href="<?= $user ? '/account.php' : '/login.php' ?>" aria-label="<?= $user ? 'My tickets' : 'Log in' ?>"<?= $page['active'] === 'account' ? ' aria-current="page"' : '' ?>><?= icon('user') ?></a>
    <details class="menu">
      <summary class="icon" aria-label="Menu"><?= icon('menu') ?></summary>
      <nav class="menu-panel" aria-label="Menu">
        <?php foreach ($nav as $key => $label): ?>
          <a href="/#<?= $key ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <hr>
        <a href="/find-booking.php">Find my booking</a>
        <?php if ($user): ?>
          <a href="/account.php">My tickets</a>
        <?php else: ?>
          <a href="/login.php">Log in</a>
          <a href="/register.php">Create an account</a>
        <?php endif; ?>
        <a href="/contact.php">Contact us</a>
        <?php if ($user): ?>
          <form method="post" action="/logout.php"><?= csrf_field() ?><button type="submit">Log out</button></form>
        <?php endif; ?>
      </nav>
    </details>
  </div>
</header>
