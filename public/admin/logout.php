<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Admin;

if (is_post()) {
    Admin::logOut();
    flash('You’re logged out of the admin.');
}
redirect('/admin/login.php', 303);
