<?php
declare(strict_types=1);

// Log in with a phone number or email. Guests can still book without an account.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Throttle;
use App\Users;

$raw = $_POST['next'] ?? $_GET['next'] ?? '';
$next = local_path(is_string($raw) ? $raw : '', '/account.php');
if (preg_match('#^/(login|register|logout|forgot|reset)\b#', $next)) {
    $next = '/account.php';
}
if (Users::current() !== null) {
    redirect($next);
}

$login = '';
$error = null;
if (is_post()) {
    $login = mb_substr(is_string($_POST['login'] ?? null) ? trim($_POST['login']) : '', 0, 190);
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (Throttle::tooMany('login', 10, 600)) {
        $error = 'That’s a lot of tries. Wait a few minutes and try again, or reset your password.';
    } else {
        $user = Users::attempt($login, $password);
        if ($user !== null) {
            Throttle::clear('login');
            Users::logIn($user);
            flash('Welcome back, ' . strtok($user['name'], ' ') . '.');
            redirect($next, 303);
        }
        Throttle::hit('login', 600);
        $error = 'That phone number or email and password don’t match. Check them and try again.';
    }
}

page_open([
    'title' => 'Log in',
    'description' => 'Log in to see your Century Cinemax tickets.',
    'noindex' => true,
]);
?>
<div class="plain auth">
  <h1>Log in</h1>
  <p>See all your tickets in one place. You don’t need an account to book.</p>
  <form method="post" action="/login.php" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <label class="field">
      <span>Phone number or email</span>
      <input name="login" value="<?= e($login) ?>" autocomplete="username" placeholder="772 123 456" required>
    </label>
    <label class="field">
      <span>Password</span>
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <?php if ($error !== null): ?>
      <p class="err" role="alert"><?= e($error) ?></p>
    <?php endif; ?>
    <a class="aside" href="/forgot.php">Forgot your password?</a>
    <button class="btn btn-y" type="submit">Log in</button>
  </form>
  <p class="switch">New here? <a href="/register.php<?= $next !== '/account.php' ? '?next=' . e(rawurlencode($next)) : '' ?>">Create an account</a></p>
</div>
<?php
page_close();
