<?php

declare(strict_types=1);

function dilitant_cli_configure_wordpress(
    array $config,
    string $root
): void {
    $constants = [
        'ABSPATH' => $root,
        'DB_NAME' => $config['name'],
        'DB_USER' => $config['user'],
        'DB_PASSWORD' => $config['password'],
        'DB_HOST' => $config['host'],
        'DB_CHARSET' => 'utf8mb4',
        'DB_COLLATE' => '',
        'WP_ENVIRONMENT_TYPE' => 'local',
        'WP_DEBUG' => true,
        'WP_DEBUG_DISPLAY' => true,
        'WP_DEBUG_LOG' => false,
        'WP_CACHE' => false,
        'DISABLE_WP_CRON' => true,
        'AUTOMATIC_UPDATER_DISABLED' => true,
        'DISALLOW_FILE_MODS' => true,
        'WP_HTTP_BLOCK_EXTERNAL' => true,
        'WP_HOME' => 'http://dilitant-test.invalid',
        'WP_SITEURL' => 'http://dilitant-test.invalid',
        'WPMU_PLUGIN_DIR' => '/nonexistent/dilitant-test-mu-plugins',
    ];

    foreach ($constants as $name => $value) {
        if (defined($name)) {
            dilitant_test_abort("Configuration already defined: {$name}");
        }

        define($name, $value);
    }

    foreach ([
        'AUTH_KEY',
        'SECURE_AUTH_KEY',
        'LOGGED_IN_KEY',
        'NONCE_KEY',
        'AUTH_SALT',
        'SECURE_AUTH_SALT',
        'LOGGED_IN_SALT',
        'NONCE_SALT',
    ] as $key) {
        define($key, bin2hex(random_bytes(32)));
    }

    $GLOBALS['table_prefix'] = DILITANT_TEST_TABLE_PREFIX;
}