<?php
declare(strict_types=1);

// Create an account: name, phone number, an optional email and a password.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Users;

$raw = $_POST['next'] ?? $_GET['next'] ?? '';
$next = local_path(is_string($raw) ? $raw : '', '/account.php');
if (preg_match('#^/(login|register|logout|forgot|reset)\b#', $next)) {
    $next = '/account.php';
}
if (Users::current() !== null) {
    redirect($next);
}

$input = ['name' => '', 'phone' => '', 'email' => ''];
$errors = [];
if (is_post()) {
    foreach ($input as $key => $_) {
        $input[$key] = mb_substr(is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '', 0, 190);
    }
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $phone = normalise_phone($input['phone']);
    $email = $input['email'] === '' ? null : mb_strtolower($input['email']);

    if ($input['name'] === '' || mb_strlen($input['name']) > 100) {
        $errors['name'] = 'Enter your name, as you’d like it on your tickets.';
    }
    if ($phone === null) {
        $errors['phone'] = 'Enter the 9 digits of your number after +256, like 772 123 456.';
    } elseif (db_run('SELECT 1 FROM users WHERE phone = ?', [$phone])->fetchColumn()) {
        $errors['phone'] = 'There’s already an account for ' . format_phone($phone) . '. Log in, or reset its password.';
    }
    if ($email !== null && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190)) {
        $errors['email'] = 'That email address doesn’t look right. Check it, or leave it empty.';
    } elseif ($email !== null && db_run('SELECT 1 FROM users WHERE email = ?', [$email])->fetchColumn()) {
        $errors['email'] = 'That email is already on an account. Log in, or reset its password.';
    }
    if (($problem = Users::passwordProblem($password)) !== null) {
        $errors['password'] = $problem;
    }

    if (!$errors) {
        try {
            $user = Users::create($input['name'], $phone, $email, $password);
        } catch (PDOException $e) {
            // Someone registered the same phone or email a moment ago.
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }
            $errors['phone'] = 'There’s already an account for that phone number or email. Log in instead.';
        }
        if (!$errors) {
            Users::logIn($user);
            flash('Your account is ready. Tickets booked with ' . format_phone($phone) . ' show up here.');
            redirect($next, 303);
        }
    }
}

/** The error under a field, and the attributes that link it to the field. */
$fieldError = static function (string $key) use ($errors): array {
    return isset($errors[$key])
        ? [' aria-invalid="true" aria-describedby="err-' . $key . '"', '<p class="err" id="err-' . $key . '">' . e($errors[$key]) . '</p>']
        : ['', ''];
};

page_open([
    'title' => 'Create an account',
    'description' => 'Create a Century Cinemax account to keep your tickets together.',
    'noindex' => true,
]);
?>
<div class="plain auth">
  <h1>Create an account</h1>
  <p>Keep your tickets together, and your phone number is filled in at checkout.</p>
  <form method="post" action="/register.php" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <?php [$attrs, $message] = $fieldError('name'); ?>
    <label class="field">
      <span>Your name</span>
      <input name="name" value="<?= e($input['name']) ?>" autocomplete="name" maxlength="100" required<?= $attrs ?>>
    </label>
    <?= $message ?>
    <?php [$attrs, $message] = $fieldError('phone'); ?>
    <span class="field-label" id="reg-phone">Phone number</span>
    <label class="phone"><span>+256</span><input name="phone" value="<?= e($input['phone']) ?>" inputmode="tel" autocomplete="tel-national" placeholder="772 123 456" aria-labelledby="reg-phone" required<?= $attrs ?>></label>
    <?= $message ?>
    <?php [$attrs, $message] = $fieldError('email'); ?>
    <label class="field">
      <span>Email <small>(optional)</small></span>
      <input type="email" name="email" value="<?= e($input['email']) ?>" autocomplete="email"<?= $attrs ?>>
    </label>
    <?= $message ?>
    <?php [$attrs, $message] = $fieldError('password'); ?>
    <label class="field">
      <span>Password <small>(at least <?= Users::MIN_PASSWORD ?> characters)</small></span>
      <input type="password" name="password" autocomplete="new-password" minlength="<?= Users::MIN_PASSWORD ?>" required<?= $attrs ?>>
    </label>
    <?= $message ?>
    <button class="btn btn-y" type="submit">Create account</button>
  </form>
  <p class="switch">Already have one? <a href="/login.php<?= $next !== '/account.php' ? '?next=' . e(rawurlencode($next)) : '' ?>">Log in</a></p>
</div>
<?php
page_close();
