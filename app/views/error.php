<?php
declare(strict_types=1);

/** @var int $status */
/** @var string $message */

$headings = [
    403 => 'That didn’t go through',
    404 => 'We couldn’t find that page',
    500 => 'Something went wrong on our side',
];
$heading = $headings[$status] ?? 'Something went wrong';
$message = $message !== '' ? $message : 'Try again in a moment.';

page_open(['title' => $heading, 'noindex' => true]);
?>
<section class="plain">
  <h1><?= e($heading) ?></h1>
  <p><?= e($message) ?></p>
  <a class="btn btn-sm btn-y" href="/">Back to home</a>
</section>
<?php
page_close();
