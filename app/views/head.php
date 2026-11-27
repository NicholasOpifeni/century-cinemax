<?php
declare(strict_types=1);

/** @var array $page */

$title = $page['title'] !== '' ? $page['title'] . ' | Cinemax' : 'Cinemax | Book cinema tickets in Kampala';
$filmCss = film_colour_css($page['films']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($page['description']) ?>">
<?php if ($page['noindex']): ?>
<meta name="robots" content="noindex">
<?php endif; ?>
<?php if ($page['refresh'] !== null): ?>
<meta http-equiv="refresh" content="<?= (int) $page['refresh'] ?>">
<?php endif; ?>
<meta name="theme-color" content="#07070B">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="preload" href="/assets/fonts/geist-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<?php foreach ($page['styles'] as $style): ?>
<link rel="stylesheet" href="<?= e(asset('css/' . $style)) ?>">
<?php endforeach; ?>
<script nonce="<?= e(csp_nonce()) ?>">document.documentElement.classList.add('js')</script>
<?php if ($filmCss !== ''): ?>
<style nonce="<?= e(csp_nonce()) ?>"><?= $filmCss ?></style>
<?php endif; ?>
<script src="<?= e(asset('js/site.js')) ?>" defer></script>
<?php foreach ($page['scripts'] as $script): ?>
<script src="<?= e(asset('js/' . $script)) ?>" defer></script>
<?php endforeach; ?>
</head>
<body<?= $page['body_class'] !== '' ? ' class="' . e($page['body_class']) . '"' : '' ?>>
<a class="skip" href="#main">Skip to content</a>
