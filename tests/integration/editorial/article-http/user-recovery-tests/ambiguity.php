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

require_once __DIR__ . '/ambiguity/common.php';
require_once __DIR__ . '/ambiguity/duplicate.php';
require_once __DIR__ . '/ambiguity/substitution.php';