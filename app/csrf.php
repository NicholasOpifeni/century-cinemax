<?php
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Forms send the token as _csrf, JSON requests as an X-CSRF-Token header. */
function csrf_verify(): void
{
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $known = $_SESSION['_csrf'] ?? '';

    if (!is_string($sent) || $known === '' || !hash_equals($known, $sent)) {
        abort(403, 'This page timed out. Go back, refresh it and try again.');
    }
}
