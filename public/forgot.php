<?php
declare(strict_types=1);

// Forgot your password: makes a reset link and writes it to storage/logs/mail.log, because there's
// no mail server. The answer is the same whether or not the account exists, so this page can't be
// used to find out who has one.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Throttle;
use App\Users;

$login = '';
$sent = false;
$error = null;
if (is_post()) {
    $login = mb_substr(is_string($_POST['login'] ?? null) ? trim($_POST['login']) : '', 0, 190);
    if ($login === '') {
        $error = 'Enter the phone number or email on your account.';
    } elseif (Throttle::tooMany('forgot', 5, 600)) {
        $error = 'That’s a lot of reset links. Wait a few minutes and try again.';
    } else {
        Throttle::hit('forgot', 600);
        $user = Users::findByLogin($login);
        if ($user !== null) {
            Users::startReset($user);
        }
        $sent = true;
    }
}

page_open([
    'title' => 'Forgot your password',
    'description' => 'Get a link to choose a new Cinemax password.',
    'noindex' => true,
]);
?>
<div class="plain auth">
  <?php if ($sent): ?>
    <h1>Check with our staff</h1>
    <p>If there’s an account for <?= e($login) ?>, a reset link is ready. It works once, for an hour.</p>
    <p class="note-box">This demo can’t send email or text messages, so the link is written to the site’s mail log instead. Ask a member of staff to read it to you, then open it to choose a new password.</p>
    <p class="switch"><a href="/login.php">Back to log in</a></p>
  <?php else: ?>
    <h1>Forgot your password?</h1>
    <p>Enter the phone number or email on your account and we’ll make you a link to choose a new one.</p>
    <form method="post" action="/forgot.php" novalidate>
      <?= csrf_field() ?>
      <label class="field">
        <span>Phone number or email</span>
        <input name="login" value="<?= e($login) ?>" autocomplete="username" placeholder="772 123 456" required>
      </label>
      <?php if ($error !== null): ?>
        <p class="err" role="alert"><?= e($error) ?></p>
      <?php endif; ?>
      <button class="btn btn-y" type="submit">Make a reset link</button>
    </form>
    <p class="switch">Remembered it? <a href="/login.php">Log in</a></p>
  <?php endif; ?>
</div>
<?php
page_close();
