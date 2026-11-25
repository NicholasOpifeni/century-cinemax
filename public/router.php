<?php
declare(strict_types=1);

/*
 * Router for PHP's built-in server.
 * Real files under public/ are served as they are. /film and /film.php both run film.php,
 * /admin/ runs admin/index.php, and anything else gets the 404 page.
 */

$root = __DIR__;
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

$run = static function (string $file) use ($root): bool {
    $_SERVER['SCRIPT_FILENAME'] = $file;
    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = substr($file, strlen($root));
    chdir(dirname($file));
    require $file;
    return true;
};

$inside = static fn (string|false $file): bool =>
    $file !== false && str_starts_with($file, $root . DIRECTORY_SEPARATOR);

// No dotfiles, no null bytes, and the router itself isn't a page.
$blocked = str_contains($path, "\0")
    || preg_match('#/\.#', $path) === 1
    || $path === '/router.php';

if (!$blocked) {
    $target = realpath($root . $path);

    if ($inside($target) || $target === $root) {
        if (is_dir($target)) {
            if (!str_ends_with($path, '/')) {
                header('Location: ' . $path . '/', true, 301);
                return true;
            }
            $index = $target . '/index.php';
            if (is_file($index)) {
                return $run($index);
            }
        } elseif (is_file($target)) {
            return str_ends_with($target, '.php') ? $run($target) : false;
        }
    }

    $page = realpath($root . rtrim($path, '/') . '.php');
    if ($inside($page) && is_file($page)) {
        return $run($page);
    }
}

http_response_code(404);
return $run($root . '/404.php');
