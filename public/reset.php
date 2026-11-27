<?php
declare(strict_types=1);

// Choose a new password from a reset link. The link works once, for an hour.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Users;

$raw = $_POST['token'] ?? $_GET['token'] ?? '';
$token = is_string($raw) ? $raw : '';
$reset = Users::findReset($token);
$error = null;

if ($reset !== null && is_post()) {
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $error = Users::passwordProblem($password);
    if ($error === null) {
        Users::finishReset($reset, $password);
        Users::logIn($reset);
        flash('Your password is changed, and you’re logged in.');
        redirect('/account.php', 303);
    }
}

if ($reset === null) {
    http_response_code(410);
}
page_open([
    'title' => 'Choose a new password',
    'noindex' => true,
]);
?>
<div class="plain auth">
  <?php if ($reset === null): ?>
    <h1>This link doesn’t work any more</h1>
    <p>Reset links work once, for an hour. Ask for a new one and use it straight away.</p>
    <a class="btn btn-y" href="/forgot.php">Get a new link</a>
  <?php else: ?>
    <h1>Choose a new password</h1>
    <p>For the account of <?= e($reset['name']) ?>, <?= e(format_phone($reset['phone'])) ?>.</p>
    <form method="post" action="/reset.php" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <label class="field">
        <span>New password <small>(at least <?= Users::MIN_PASSWORD ?> characters)</small></span>
        <input type="password" name="password" autocomplete="new-password" minlength="<?= Users::MIN_PASSWORD ?>" required<?= $error !== null ? ' aria-invalid="true" aria-describedby="reset-error"' : '' ?>>
      </label>
      <?php if ($error !== null): ?>
        <p class="err" id="reset-error" role="alert"><?= e($error) ?></p>
      <?php endif; ?>
      <button class="btn btn-y" type="submit">Save new password</button>
    </form>
  <?php endif; ?>
</div>
<?php
page_close();
