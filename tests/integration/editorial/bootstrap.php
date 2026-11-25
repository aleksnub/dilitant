<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

require_once __DIR__ . '/support/constants.php';
require_once __DIR__ . '/bootstrap/errors.php';
require_once __DIR__ . '/bootstrap/environment.php';
require_once __DIR__ . '/bootstrap/database.php';
require_once __DIR__ . '/bootstrap/configuration.php';
require_once __DIR__ . '/bootstrap/verification.php';

dilitant_cli_verify_environment();

$wp_root = '/var/www/html/';
$config = dilitant_cli_database_config();

dilitant_cli_verify_source($wp_root);
dilitant_cli_verify_database($config);
dilitant_cli_configure_wordpress($config, $wp_root);

unset($config);

require_once __DIR__ . '/bootstrap/wordpress.php';
require_once __DIR__ . '/support/assertions.php';

dilitant_cli_verify_wordpress();
dilitant_cli_verify_interfaces();
dilitant_cli_report_ready();