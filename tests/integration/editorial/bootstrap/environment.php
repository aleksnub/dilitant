<?php

declare(strict_types=1);

function dilitant_cli_verify_environment(): void
{
    if (PHP_SAPI !== 'cli') {
        dilitant_test_abort('CLI execution required.');
    }

    if (getenv('DILITANT_TEST_MODE') !== '1') {
        dilitant_test_abort('Test mode is not explicitly enabled.');
    }

    if (getenv('DILITANT_HTTP_TEST_MODE') === '1') {
        dilitant_test_abort('Use HTTP Bootstrap in HTTP test mode.');
    }

    $constants = [
        'ABSPATH',
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
        'DB_HOST',
        'WP_HOME',
        'WP_SITEURL',
        'AUTH_KEY',
        'SECURE_AUTH_KEY',
        'LOGGED_IN_KEY',
        'NONCE_KEY',
        'AUTH_SALT',
        'SECURE_AUTH_SALT',
        'LOGGED_IN_SALT',
        'NONCE_SALT',
    ];

    foreach ($constants as $constant) {
        if (defined($constant)) {
            dilitant_test_abort(
                "WordPress configuration already defined: {$constant}"
            );
        }
    }
}

function dilitant_cli_database_config(): array
{
    $config = [
        'name' => getenv('DILITANT_TEST_DB_NAME'),
        'user' => getenv('DILITANT_TEST_DB_USER'),
        'password' => getenv('DILITANT_TEST_DB_PASSWORD'),
        'host' => getenv('DILITANT_TEST_DB_HOST'),
    ];

    if ($config['name'] !== DILITANT_TEST_DB_NAME) {
        dilitant_test_abort('Unexpected database name.');
    }

    if ($config['user'] !== 'dilitant_test') {
        dilitant_test_abort('Unexpected database user.');
    }

    if ($config['host'] !== 'dilitant_land_db:3306') {
        dilitant_test_abort('Unexpected database host.');
    }

    if (!is_string($config['password']) || $config['password'] === '') {
        dilitant_test_abort('Test database password is missing.');
    }

    return $config;
}

function dilitant_cli_verify_source(string $root): void
{
    if (!is_file($root . 'wp-settings.php')) {
        dilitant_test_abort('WordPress installation not found.');
    }

    foreach ([
        'db.php',
        'object-cache.php',
        'advanced-cache.php',
        'sunrise.php',
    ] as $dropin) {
        $path = $root . 'wp-content/' . $dropin;

        if (file_exists($path) || is_link($path)) {
            dilitant_test_abort("Unsupported WordPress drop-in: {$dropin}");
        }
    }

    $mu = '/nonexistent/dilitant-test-mu-plugins';

    if (file_exists($mu) || is_link($mu)) {
        dilitant_test_abort('Unexpected MU plugin directory.');
    }
}