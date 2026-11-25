<?php
declare(strict_types=1);

// Remembers the cinema picked in the header, then goes back to the page it was picked on.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Cinemas;

if (!is_post()) {
    redirect('/');
}

$cinema = Cinemas::bySlug((string) ($_POST['cinema'] ?? ''));
if ($cinema !== null) {
    Cinemas::remember($cinema['slug']);
}

redirect(local_path(is_string($_POST['return'] ?? null) ? $_POST['return'] : '/'), 303);
