<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

http_response_code(404);
$status = 404;
$message = 'It may have moved, or the link has a typo.';

require ROOT . '/app/views/error.php';
