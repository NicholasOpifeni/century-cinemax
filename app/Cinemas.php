<?php
declare(strict_types=1);

namespace App;

final class Cinemas
{
    public const COOKIE = 'cinemax_cinema';

    /** @return list<array{id: int, slug: string, name: string, area: string, halls: int, has_atmos: bool}> */
    public static function all(): array
    {
        static $all = null;
        if ($all !== null) {
            return $all;
        }

        $rows = db()->query(
            'SELECT c.id, c.slug, c.name, c.area, COUNT(h.id) AS halls, COALESCE(MAX(h.is_atmos), 0) AS has_atmos
             FROM cinemas c LEFT JOIN halls h ON h.cinema_id = c.id
             GROUP BY c.id ORDER BY c.sort_order, c.id'
        )->fetchAll();

        return $all = array_map(fn (array $r): array => [
            'id' => (int) $r['id'],
            'slug' => $r['slug'],
            'name' => $r['name'],
            'area' => $r['area'],
            'halls' => (int) $r['halls'],
            'has_atmos' => (bool) $r['has_atmos'],
        ], $rows);
    }

    public static function bySlug(string $slug): ?array
    {
        foreach (self::all() as $cinema) {
            if ($cinema['slug'] === $slug) {
                return $cinema;
            }
        }
        return null;
    }

    /** The cinema the visitor last picked, or the first one. */
    public static function current(): ?array
    {
        return self::bySlug((string) ($_COOKIE[self::COOKIE] ?? '')) ?? (self::all()[0] ?? null);
    }

    public static function remember(string $slug): void
    {
        setcookie(self::COOKIE, $slug, [
            'expires' => time() + 365 * 86400,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $slug;
    }
}
