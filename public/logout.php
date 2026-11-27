<?php
declare(strict_types=1);

// Log out is a POST from the menu or the account page, so another site can't log people out with a link.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Users;

if (is_post()) {
    Users::logOut();
    flash('You’re logged out.');
}
redirect('/', 303);
