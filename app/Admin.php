<?php
declare(strict_types=1);

namespace App;

/**
 * Staff sign-in for /admin/. Admin accounts are separate from customer accounts and use their own
 * session key, so being logged in as a customer never opens the admin pages.
 */
final class Admin
{
    private static ?array $current = null;
    private static bool $loaded = false;

    public static function current(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['admin_id'] ?? null;
            if (is_int($id)) {
                self::$current = db_run('SELECT id, name, email FROM admins WHERE id = ?', [$id])->fetch() ?: null;
                if (self::$current === null) {
                    unset($_SESSION['admin_id']);
                }
            }
        }
        return self::$current;
    }

    /** Every admin page starts with this. Signed-out visitors get a 302 to the admin login. */
    public static function require(): array
    {
        $admin = self::current();
        if ($admin === null) {
            redirect('/admin/login.php?next=' . rawurlencode(local_path($_SERVER['REQUEST_URI'] ?? '/admin/', '/admin/')));
        }
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
        return $admin;
    }

    /** The admin for an email and password, or null. Takes as long whether or not the email exists. */
    public static function attempt(string $email, string $password): ?array
    {
        $admin = db_run('SELECT id, name, email, password_hash FROM admins WHERE email = ?', [mb_strtolower(trim($email))])->fetch();
        if (!$admin) {
            password_verify($password, '$2y$12$/YEcf/GwnjxSuB6qUFx8IeaHxrJ8RPB90bFsc/ZdbNVcRV/TnDjIi');
            return null;
        }
        if (!password_verify($password, $admin['password_hash'])) {
            return null;
        }
        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            db_run('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
        }
        return $admin;
    }

    public static function logIn(array $admin): void
    {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        db_run('UPDATE admins SET last_login_at = NOW() WHERE id = ?', [$admin['id']]);
        self::$loaded = false;
    }

    public static function logOut(): void
    {
        unset($_SESSION['admin_id']);
        session_regenerate_id(true);
        self::$current = null;
    }
}
