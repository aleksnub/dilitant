<?php

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    || !defined('DILITANT_TEST_DB_NAME')
    || !defined('DB_NAME')
    || DB_NAME !== DILITANT_TEST_DB_NAME
) {
    exit(1);
}

require_once __DIR__ . '/environment/isolation.php';
require_once __DIR__ . '/environment/user-identity.php';
require_once __DIR__ . '/environment/user-creation.php';
require_once __DIR__ . '/environment/user-cleanup.php';
require_once __DIR__ . '/environment/users.php';
require_once __DIR__ . '/environment/user-context.php';
require_once __DIR__ . '/environment/post-context.php';
require_once __DIR__ . '/environment/cookie-context.php';