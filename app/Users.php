<?php
declare(strict_types=1);

namespace App;

/**
 * Customer accounts. Booking never needs one; an account keeps tickets together and fills in
 * the phone number at checkout. Customers log in with their phone number or email.
 */
final class Users
{
    public const MIN_PASSWORD = 8;
    private const MAX_PASSWORD = 200;
    private const RESET_MINUTES = 60;

    private static ?array $current = null;
    private static bool $loaded = false;

    /** The logged-in customer, or null. */
    public static function current(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['user_id'] ?? null;
            if (is_int($id)) {
                self::$current = db_run('SELECT id, name, phone, email FROM users WHERE id = ?', [$id])->fetch() ?: null;
                if (self::$current === null) {
                    unset($_SESSION['user_id']); // the account has gone
                } else {
                    self::$current['id'] = (int) self::$current['id'];
                }
            }
        }
        return self::$current;
    }

    /** Sends visitors who aren't logged in to the login page, and back here afterwards. */
    public static function requireLogin(): array
    {
        $user = self::current();
        if ($user === null) {
            redirect('/login.php?next=' . rawurlencode(local_path($_SERVER['REQUEST_URI'] ?? '/account.php')));
        }
        return $user;
    }

    /** Finds an account by phone number or email, the way people type them. */
    public static function findByLogin(string $login): ?array
    {
        $login = trim($login);
        if (str_contains($login, '@')) {
            $row = db_run('SELECT id, name, phone, email, password_hash FROM users WHERE email = ?', [mb_strtolower($login)])->fetch();
        } else {
            $phone = normalise_phone($login);
            $row = $phone !== null ? db_run('SELECT id, name, phone, email, password_hash FROM users WHERE phone = ?', [$phone])->fetch() : false;
        }
        return $row ?: null;
    }

    /**
     * The account for a login and password, or null. When there's no such account a password is
     * still checked, against a hash of random bytes, so the reply takes as long either way and
     * can't be used to find out which numbers have accounts.
     */
    public static function attempt(string $login, string $password): ?array
    {
        $user = self::findByLogin($login);
        if ($user === null) {
            password_verify($password, '$2y$12$/YEcf/GwnjxSuB6qUFx8IeaHxrJ8RPB90bFsc/ZdbNVcRV/TnDjIi');
            return null;
        }
        return self::passwordMatches($user, $password) ? $user : null;
    }

    /** Checks a password, quietly upgrading its hash if PHP's default has moved on. */
    public static function passwordMatches(array $user, string $password): bool
    {
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        return true;
    }

    /** What's wrong with a new password, or null. */
    public static function passwordProblem(string $password): ?string
    {
        return match (true) {
            mb_strlen($password) < self::MIN_PASSWORD => 'Use at least ' . self::MIN_PASSWORD . ' characters for your password.',
            mb_strlen($password) > self::MAX_PASSWORD => 'That password is too long. Use ' . self::MAX_PASSWORD . ' characters or fewer.',
            default => null,
        };
    }

    public static function create(string $name, string $phone, ?string $email, string $password): array
    {
        db_run(
            'INSERT INTO users (name, phone, email, password_hash) VALUES (?, ?, ?, ?)',
            [$name, $phone, $email, password_hash($password, PASSWORD_DEFAULT)],
        );
        return ['id' => (int) db()->lastInsertId(), 'name' => $name, 'phone' => $phone, 'email' => $email];
    }

    /** A new session ID on every login, so a session ID planted before login is worthless after it. */
    public static function logIn(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        db_run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        self::$loaded = false;
    }

    public static function logOut(): void
    {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
        self::$current = null;
    }

    /**
     * This customer's tickets: bookings made while logged in, and any booked as a guest with the
     * same phone number. Paid and cancelled ones only, soonest first, then the most recent past ones.
     *
     * @return array{upcoming: list<array>, past: list<array>}
     */
    public static function tickets(array $user, int $pastLimit = 20): array
    {
        $select = "SELECT b.ref, b.status, b.seat_labels, b.total, s.starts_at, s.format, h.number AS hall, c.name AS cinema_name,
                          f.title, f.slug, f.poster_path
                   FROM bookings b
                   JOIN showtimes s ON s.id = b.showtime_id
                   JOIN halls h ON h.id = s.hall_id
                   JOIN cinemas c ON c.id = h.cinema_id
                   JOIN films f ON f.id = s.film_id
                   WHERE b.status IN ('paid', 'cancelled')
                     AND (b.user_id = ? OR (b.phone = ? AND b.phone <> ?))";
        $params = [$user['id'], $user['phone'], Bookings::DEMO_PHONE];
        $hydrate = function (array $row): array {
            $row['starts_at'] = new \DateTimeImmutable($row['starts_at']);
            return $row;
        };
        return [
            'upcoming' => array_map($hydrate, db_run("$select AND s.starts_at > NOW() ORDER BY s.starts_at", $params)->fetchAll()),
            'past' => array_map($hydrate, db_run("$select AND s.starts_at <= NOW() ORDER BY s.starts_at DESC LIMIT " . max(1, $pastLimit), $params)->fetchAll()),
        ];
    }

    /**
     * Makes a reset link that works once, for an hour, and writes it to the mail log.
     * Only a hash of the token is stored, so the database alone can't be used to reset anyone.
     */
    public static function startReset(array $user): void
    {
        $token = bin2hex(random_bytes(32));
        db_run('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL', [$user['id']]);
        db_run(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL ? MINUTE)',
            [$user['id'], hash('sha256', $token), self::RESET_MINUTES],
        );
        $to = $user['name'] . ', ' . format_phone($user['phone']) . ($user['email'] ? ', ' . $user['email'] : '');
        MailLog::send(
            $to,
            'Reset your Cinemax password',
            "Hello {$user['name']},\n\nSomeone asked to reset the password for your Cinemax account. If it was you, open this link within an hour and choose a new one:\n\n"
            . app_url('/reset.php?token=' . $token)
            . "\n\nIf it wasn't you, you can ignore this. Your password hasn't changed.",
        );
    }

    /** The account a reset link belongs to, if the link is real, unused and in date. */
    public static function findReset(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }
        $row = db_run(
            'SELECT r.id AS reset_id, u.id, u.name, u.phone, u.email
             FROM password_resets r JOIN users u ON u.id = r.user_id
             WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW()',
            [hash('sha256', $token)],
        )->fetch();
        return $row ?: null;
    }

    public static function finishReset(array $reset, string $password): void
    {
        db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $reset['id']]);
        db_run('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$reset['id']]);
    }
}
