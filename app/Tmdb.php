<?php
declare(strict_types=1);

namespace App;

use RuntimeException;

/** Small TMDB client. API responses and downloaded images are cached on disk for 12 hours. */
final class Tmdb
{
    private const API = 'https://api.themoviedb.org/3';
    private const IMAGES = 'https://image.tmdb.org/t/p/';

    public function __construct(
        private readonly string $token,
        private readonly string $cacheDir,
        private readonly int $ttl = 43200,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self((string) config('tmdb_token'), ROOT . '/storage/cache/tmdb');
    }

    public static function imageUrl(?string $path, string $size): ?string
    {
        return $path ? self::IMAGES . $size . $path : null;
    }

    /** @return list<array<string, mixed>> */
    public function search(string $query, ?int $year = null): array
    {
        $params = ['query' => $query, 'include_adult' => 'false', 'language' => 'en-US'];
        if ($year !== null) {
            $params['primary_release_year'] = (string) $year;
        }
        return $this->get('/search/movie', $params)['results'] ?? [];
    }

    /** Film details with its videos and credits, in one request. */
    public function movie(int $id): array
    {
        return $this->get("/movie/$id", [
            'language' => 'en-US',
            'append_to_response' => 'videos,credits',
            // Ramayana and Godzilla may only have Hindi or Japanese trailers.
            'include_video_language' => 'en,null,hi,ja',
        ]);
    }

    /** YouTube key of the official trailer, then any trailer, then a teaser. English ones first. */
    public static function bestTrailer(array $videos): ?string
    {
        $youtube = array_values(array_filter(
            $videos,
            fn (array $v): bool => ($v['site'] ?? '') === 'YouTube' && preg_match('/^[\w-]{6,20}$/', (string) ($v['key'] ?? '')) === 1,
        ));
        usort($youtube, fn (array $a, array $b): int => (($b['iso_639_1'] ?? '') === 'en') <=> (($a['iso_639_1'] ?? '') === 'en'));

        $tests = [
            fn (array $v): bool => $v['type'] === 'Trailer' && !empty($v['official']),
            fn (array $v): bool => $v['type'] === 'Trailer',
            fn (array $v): bool => $v['type'] === 'Teaser',
        ];
        foreach ($tests as $test) {
            foreach ($youtube as $video) {
                if ($test($video)) {
                    return $video['key'];
                }
            }
        }
        return null;
    }

    /** Downloads a poster or backdrop once and returns its bytes. */
    public function imageBytes(string $path, string $size = 'w92'): string
    {
        if (preg_match('#^/[\w-]+\.(jpg|jpeg|png|webp)$#i', $path) !== 1 || preg_match('/^w\d+$|^original$/', $size) !== 1) {
            throw new RuntimeException("That doesn't look like a TMDB image path: $path");
        }

        $file = $this->cacheDir . "/images/$size" . $path;
        if (is_file($file)) {
            return (string) file_get_contents($file);
        }

        // Images come from a different host, so the token isn't sent with them.
        $bytes = $this->fetch(self::IMAGES . $size . $path, []);
        $this->store($file, $bytes);
        return $bytes;
    }

    private function get(string $path, array $params): array
    {
        ksort($params);
        $url = self::API . $path . '?' . http_build_query($params);
        $file = $this->cacheDir . '/api/' . sha1($url) . '.json';

        if (is_file($file) && filemtime($file) > time() - $this->ttl) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $body = $this->fetch($url, ['Authorization: Bearer ' . $this->token, 'Accept: application/json']);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('TMDB sent back something that isn’t JSON.');
        }

        $this->store($file, $body);
        return $data;
    }

    private function fetch(string $url, array $headers, int $attempt = 1): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Cinemax/1.0',
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        $where = (string) strtok($url, '?');
        if ($body === false) {
            throw new RuntimeException("Couldn’t reach TMDB ($error).");
        }
        if ($status === 429 && $attempt < 3) {
            sleep($attempt);
            return $this->fetch($url, $headers, $attempt + 1);
        }
        if ($status === 401) {
            throw new RuntimeException('TMDB didn’t accept the token in config/secrets.php.');
        }
        if ($status >= 400) {
            throw new RuntimeException("TMDB answered $status for $where.");
        }
        return (string) $body;
    }

    private function store(string $file, string $contents): void
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Couldn’t create the cache folder $dir.");
        }
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, $contents);
        rename($tmp, $file);
    }
}
