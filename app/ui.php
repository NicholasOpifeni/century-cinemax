<?php
declare(strict_types=1);

use App\Tmdb;

/** The mockup's icon set, as inline SVG. */
function icon(string $name): string
{
    static $icons = [
        'search' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="7"/><path d="m20 20-4.5-4.5"/></svg>',
        'chev' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>',
        'pin' => '<svg width="15" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z"/></svg>',
        'left' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="m15 19-7-7 7-7"/></svg>',
        'right' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>',
        'play' => '<svg width="16" height="18" viewBox="0 0 16 18" fill="currentColor" aria-hidden="true"><path d="M1 1v16l14-8z"/></svg>',
        'playBig' => '<svg width="22" height="24" viewBox="0 0 16 18" fill="currentColor" aria-hidden="true"><path d="M2 1v16l13-8z"/></svg>',
        'pause' => '<svg width="14" height="16" viewBox="0 0 14 16" fill="currentColor" aria-hidden="true"><path d="M1 1h4v14H1zM9 1h4v14H9z"/></svg>',
        'soundOff' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H2v6h4l5 4z" fill="currentColor"/><path d="m22 9-6 6M16 9l6 6"/></svg>',
        'soundOn' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H2v6h4l5 4z" fill="currentColor"/><path d="M15.5 8.5a5 5 0 0 1 0 7M19 5a10 10 0 0 1 0 14"/></svg>',
        'clock' => '<svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 10.6 3.3 2-1 1.6L11 13.5V7h2Z"/></svg>',
        'chat' => '<svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 3h16a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H9l-5 4v-4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg>',
        'cc' => '<svg width="19" height="15" viewBox="0 0 26 20" fill="currentColor" aria-hidden="true"><path d="M3 0h20a3 3 0 0 1 3 3v14a3 3 0 0 1-3 3H3a3 3 0 0 1-3-3V3a3 3 0 0 1 3-3Zm6.2 6C6.8 6 5.4 7.7 5.4 10s1.4 4 3.8 4c1.4 0 2.4-.6 3-1.6l-1.5-.9c-.3.5-.8.8-1.4.8-1.1 0-1.8-.9-1.8-2.3S8 7.7 9.1 7.7c.6 0 1.1.3 1.4.8l1.5-.9C11.5 6.6 10.5 6 9.2 6Zm8 0c-2.4 0-3.8 1.7-3.8 4s1.4 4 3.8 4c1.4 0 2.4-.6 3-1.6l-1.5-.9c-.3.5-.8.8-1.4.8-1.1 0-1.8-.9-1.8-2.3s.7-2.3 1.8-2.3c.6 0 1.1.3 1.4.8l1.5-.9c-.5-1-1.5-1.6-2.8-1.6Z"/></svg>',
        'cal' => '<svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 2h2v2h6V2h2v2h3a1 1 0 0 1 1 1v15a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h3Zm12 8H5v9h14Z"/></svg>',
        'seat' => '<svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 3h12a2 2 0 0 1 2 2v7H4V5a2 2 0 0 1 2-2Zm-4 9h2v2h16v-2h2v6h-2v3h-2v-3H6v3H4v-3H2Z"/></svg>',
        'ticket' => '<svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M3 7a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-3a2 2 0 0 0 0-4z"/></svg>',
        'menu' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
        'close' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>',
        'minus' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/></svg>',
        'plus' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
    ];
    return $icons[$name] ?? '';
}

/** "Fri 27 Nov" */
function d_short(DateTimeInterface $date): string
{
    return $date->format('D j M');
}

/** "Friday, 27 NOV", as on the film page. */
function d_long(DateTimeInterface $date): string
{
    return $date->format('l, j ') . strtoupper($date->format('M'));
}

/** "2 hours 38 minutes" */
function run_long(?int $minutes): string
{
    if (!$minutes) {
        return 'Runtime to be confirmed';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h . ' hour' . ($h > 1 ? 's' : '') . ($m ? " $m minutes" : '');
}

/** "2HR 38MIN" */
function run_caps(?int $minutes): string
{
    return $minutes ? sprintf('%dHR %02dMIN', intdiv($minutes, 60), $minutes % 60) : 'RUNTIME TBC';
}

/** "2h 38m" */
function run_short(?int $minutes): string
{
    return $minutes ? sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60) : 'TBC';
}

/** Shortens text at a word break, with an ellipsis. */
function clip(string $text, int $length): string
{
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    $cut = mb_substr($text, 0, $length);
    $space = mb_strrpos($cut, ' ');
    return rtrim(mb_substr($cut, 0, $space ?: $length), ',.;: ') . '…';
}

function format_label(string $format): string
{
    return $format === 'Dolby Atmos' ? 'Atmos' : $format;
}

/** TMDB's audience score once enough people have rated the film, otherwise its age rating. */
function rating_badge(array $film, bool $withCount = false): string
{
    if ($film['vote_count'] >= 20 && $film['vote_average'] !== null) {
        $html = '<span class="imdb" title="TMDB audience rating">TMDB</span><span>' . e(number_format($film['vote_average'], 1)) . '</span>';
        return $withCount ? $html . '<span class="dim">(' . e(number_format($film['vote_count'])) . ')</span>' : $html;
    }
    return '<span class="imdb" title="Age rating">' . e($film['certificate']) . '</span>';
}

/** Asset URL with the file's modified time, so browsers pick up changes. */
function asset(string $path): string
{
    $file = ROOT . '/public/assets/' . $path;
    return '/assets/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function wordmark(string $tag = 'a'): string
{
    $inner = '<b>CENTURY</b><span>CINEMAX</span>';
    return $tag === 'a'
        ? '<a class="wm" href="/" aria-label="Century Cinemax home">' . $inner . '</a>'
        : '<span class="wm" aria-label="Century Cinemax">' . $inner . '</span>';
}

/**
 * Poster with the film's title behind it, which shows when there's no image or it fails to load.
 * $extra is markup to place over the image, already escaped.
 */
function pic(array $film, string $size = 'w342', string $class = '', bool $lazy = true, string $extra = ''): string
{
    $url = Tmdb::imageUrl($film['poster_path'] ?? null, $size);
    $img = $url ? '<img src="' . e($url) . '" alt=""' . ($lazy ? ' loading="lazy"' : '') . ' decoding="async">' : '';
    return '<div class="pic ' . e($class) . '"><span class="pt">' . e($film['title']) . '</span>' . $img . $extra . '</div>';
}

/**
 * Where the muted trailer preview plays, and its pause and sound buttons. preview.js fills it
 * on laptop screens; elsewhere it stays empty and the still shows.
 */
function preview_layer(?string $trailerKey): string
{
    return '<div class="preview" data-preview data-key="' . e($trailerKey ?? '') . '" aria-hidden="true"></div>'
        . '<div class="pv-ctl" hidden>'
        . '<button class="pv-btn" type="button" data-pv="pause" aria-pressed="false" aria-label="Pause preview">' . icon('pause') . icon('play') . '</button>'
        . '<button class="pv-btn" type="button" data-pv="sound" aria-pressed="false" aria-label="Preview sound">' . icon('soundOff') . icon('soundOn') . '</button>'
        . '</div>';
}

/** The small hall plan on the film page: one dot per seat, dimmed when taken. */
function mini_map(array $map): string
{
    $html = '<div class="mini" aria-hidden="true">';
    foreach ($map['rows'] as $row) {
        $html .= '<div class="r">';
        for ($col = 1; $col <= $map['columns']; $col++) {
            $seat = $row['seats'][$col] ?? null;
            $html .= $seat === null ? '<i class="g"></i>' : ($seat['taken'] ? '<i class="x"></i>' : '<i></i>');
        }
        $html .= '</div>';
    }
    return $html . '</div>';
}

/** Backdrop still. It has no title stand-in, just the dark panel. */
function wide(array $film, string $size = 'w1280', bool $lazy = true): string
{
    $url = Tmdb::imageUrl($film['backdrop_path'] ?? null, $size);
    $img = $url ? '<img src="' . e($url) . '" alt=""' . ($lazy ? ' loading="lazy"' : '') . ' decoding="async">' : '';
    return '<div class="pic wide">' . $img . '</div>';
}

/** Cinemas for the header and footer. Error pages use these too, so a database outage gives an empty list. */
function site_cinemas(): array
{
    try {
        return App\Cinemas::all();
    } catch (PDOException) {
        return [];
    }
}

/** Class that gives an element the film's poster colours as --accent and --tone. */
function film_class(array $film): string
{
    return 'f-' . (int) $film['id'];
}

function film_colour_css(array $films): string
{
    $css = '';
    foreach ($films as $film) {
        $accent = (string) ($film['accent_hex'] ?? '');
        $tone = (string) ($film['tone_hex'] ?? '');
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $accent) && preg_match('/^#[0-9A-Fa-f]{6}$/', $tone)) {
            $css .= '.' . film_class($film) . "{--accent:$accent;--tone:$tone}";
        }
    }
    return $css;
}

/**
 * Settings for the page being drawn. Pages pass them to page_open().
 *   title, description   for the <title> and meta description
 *   header               '' (plain), 'over' (sits on the hero) or 'dark' (booking pages)
 *   active               which main nav link is current: now, soon, cinemas, offers
 *   films                films whose colours the page uses, for film_class()
 *   scripts              extra files in assets/js to load
 *   footer               false to leave the footer off
 *   noindex              true to keep the page out of search engines
 */
function page_settings(?array $settings = null): array
{
    static $page = [];
    if ($settings !== null) {
        $page = $settings + [
            'title' => '',
            'description' => 'Book tickets for Century Cinemax at Acacia Mall, Arena Mall and Metroplex Naalya in Kampala. Concept redesign.',
            'header' => '',
            'active' => null,
            'films' => [],
            'scripts' => [],
            'footer' => true,
            'noindex' => false,
            'body_class' => '',
        ];
    }
    return $page;
}

function page_open(array $settings = []): void
{
    $page = page_settings($settings);
    require ROOT . '/app/views/head.php';
    require ROOT . '/app/views/header.php';
    echo "<main id=\"main\">\n";
}

function page_close(): void
{
    $page = page_settings();
    echo "</main>\n";
    require ROOT . '/app/views/footer.php';
}
