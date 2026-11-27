<?php
declare(strict_types=1);

// Staff sign-in. Admin accounts come from database/seed.php and config/secrets.php.

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Admin;
use App\Throttle;

$raw = $_POST['next'] ?? $_GET['next'] ?? '';
$next = local_path(is_string($raw) ? $raw : '', '/admin/');
if (!str_starts_with($next, '/admin/') || str_starts_with($next, '/admin/login') || str_starts_with($next, '/admin/logout')) {
    $next = '/admin/';
}
if (Admin::current() !== null) {
    redirect($next);
}

$email = '';
$error = null;
if (is_post()) {
    $email = mb_substr(is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '', 0, 190);
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if (Throttle::tooMany('admin-login', 10, 600)) {
        $error = 'Too many tries. Wait a few minutes and try again.';
    } elseif (($admin = Admin::attempt($email, $password)) !== null) {
        Throttle::clear('admin-login');
        Admin::logIn($admin);
        redirect($next, 303);
    } else {
        Throttle::hit('admin-login', 600);
        $error = 'That email and password don’t match an admin account.';
    }
}

header('X-Robots-Tag: noindex, nofollow');
admin_open('Log in');
?>
<div class="adm-login">
  <h1>Admin log in</h1>
  <p>For Cinemax staff. Customers can <a href="/login.php">log in here</a>.</p>
  <form method="post" action="/admin/login.php" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <label class="field"><span>Email</span><input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required></label>
    <label class="field"><span>Password</span><input type="password" name="password" autocomplete="current-password" required></label>
    <?php if ($error !== null): ?>
      <p class="err" role="alert"><?= e($error) ?></p>
    <?php endif; ?>
    <button class="btn btn-y" type="submit">Log in</button>
  </form>
</div>
<?php
page_close();
