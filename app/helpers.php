<?php
declare(strict_types=1);

/** Reads a value from config/secrets.php. Dots reach into arrays: config('db.host'). */
function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    $config ??= require ROOT . '/config/secrets.php';

    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function ugx(int $amount): string
{
    return 'UGX ' . number_format($amount);
}

/** Full URL for links that leave the site, like QR codes and calendar files. */
function app_url(string $path = '/'): string
{
    return rtrim((string) config('base_url'), '/') . '/' . ltrim($path, '/');
}

/** Only allows paths on this site, so ?next= can't send people elsewhere. */
function local_path(?string $to, string $fallback = '/'): string
{
    if ($to === null || $to === '' || $to[0] !== '/' || str_starts_with($to, '//') || str_contains($to, '\\')) {
        return $fallback;
    }
    return $to;
}

function redirect(string $to, int $status = 302): never
{
    header('Location: ' . local_path($to), true, $status);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function flash(string $message, string $type = 'info'): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** Returns the flash messages and clears them, so each shows once. */
function take_flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

function wants_json(): bool
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    return str_starts_with($path, '/api/')
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
}

function json_out(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function abort(int $status, string $message = ''): never
{
    http_response_code($status);

    if (wants_json()) {
        json_out(['error' => $message !== '' ? $message : 'Something went wrong.'], $status);
    }
    if ($status === 404) {
        require ROOT . '/public/404.php';
        exit;
    }

    require __DIR__ . '/views/error.php';
    exit;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    // Own cookie name: cookies ignore ports, so the default PHPSESSID would clash with other localhost projects.
    session_name('cinemax_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** One nonce per request, for the page's own <style> and <script> blocks. */
function csp_nonce(): string
{
    static $nonce = null;
    return $nonce ??= base64_encode(random_bytes(18));
}

function send_base_headers(): void
{
    $nonce = csp_nonce();
    $policy = [
        "default-src 'self'",
        // www.youtube.com serves the IFrame API used by the trailer previews.
        "script-src 'self' 'nonce-$nonce' https://www.youtube.com",
        "style-src 'self' 'nonce-$nonce'",
        'img-src \'self\' https://image.tmdb.org https://i.ytimg.com',
        "font-src 'self'",
        "connect-src 'self'",
        'frame-src https://www.youtube-nocookie.com',
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ];

    header('Content-Security-Policy: ' . implode('; ', $policy));
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
