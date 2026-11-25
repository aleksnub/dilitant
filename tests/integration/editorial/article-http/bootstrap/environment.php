<?php
/**
 * Предварительная проверка HTTP Test Environment.
 *
 * Нормативная основа:
 *     HTTP-D01 — Environment
 *     HTTP-D02 — Fixtures & Authentication
 *     HF-D01 — Shared Test Bootstrap
 *
 * Ответственность:
 *     - проверка режима исполнения;
 *     - проверка переменных окружения;
 *     - проверка обязательных секретов;
 *     - запрет повторной инициализации;
 *     - проверка установки WordPress;
 *     - проверка идентичности wp-config.php;
 *     - проверка полномочий PHP CLI.
 *
 * Компонент не загружает WordPress
 * и не изменяет базу данных.
 */

declare(strict_types=1);


if (PHP_SAPI !== 'cli') {
    http_response_code(403);

    exit(1);
}


/*
 * --------------------------------------------------
 * 1. Проверка обязательных параметров
 * --------------------------------------------------
 */

function dilitant_http_verify_environment_variables(): void
{
    $expected = [
        'DILITANT_TEST_MODE' =>
            '1',

        'DILITANT_HTTP_TEST_MODE' =>
            '1',

        'DILITANT_TEST_DB_NAME' =>
            DILITANT_TEST_DB_NAME,

        'DILITANT_TEST_DB_USER' =>
            'dilitant_test',

        'DILITANT_TEST_DB_HOST' =>
            'dilitant_land_db:3306',

        'DILITANT_HTTP_TEST_URL' =>
            'http://127.0.0.1:8095',
    ];


    foreach (
        $expected
        as $name => $value
    ) {
        if (getenv($name) !== $value) {
            throw new DilitantHttpEnvironmentException(
                "Unexpected environment variable: {$name}"
            );
        }
    }
}


/*
 * --------------------------------------------------
 * 2. Проверка обязательных секретов
 * --------------------------------------------------
 */

function dilitant_http_verify_environment_secrets(): void
{
    $password = getenv(
        'DILITANT_TEST_DB_PASSWORD'
    );


    if (
        !is_string($password)
        || $password === ''
    ) {
        throw new DilitantHttpEnvironmentException(
            'Test database password is unavailable.'
        );
    }


    $secret = getenv(
        'DILITANT_HTTP_TEST_SECRET'
    );


    if (
        !is_string($secret)
        || preg_match(
            '/\A[0-9a-f]{128}\z/',
            $secret
        ) !== 1
    ) {
        throw new DilitantHttpEnvironmentException(
            'Invalid HTTP test secret.'
        );
    }


    unset(
        $password,
        $secret
    );
}


/*
 * --------------------------------------------------
 * 3. Запрет повторной инициализации
 * --------------------------------------------------
 */

function dilitant_http_verify_uninitialized_wordpress(): void
{
    $forbidden_constants = [
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


    foreach (
        $forbidden_constants
        as $constant
    ) {
        if (defined($constant)) {
            throw new DilitantHttpEnvironmentException(
                "WordPress configuration already defined: "
                . $constant
            );
        }
    }


    if (
        function_exists('wp_get_current_user')
        || function_exists('wp_create_nonce')
        || class_exists('wpdb', false)
        || isset($GLOBALS['wpdb'])
        || did_dilitant_http_bootstrap_initialize()
    ) {
        throw new DilitantHttpEnvironmentException(
            'WordPress has already been initialized.'
        );
    }
}


/**
 * Проверяет, завершалась ли ранее
 * инициализация HTTP Bootstrap.
 */
function did_dilitant_http_bootstrap_initialize(): bool
{
    return defined(
        'DILITANT_HTTP_BOOTSTRAP_READY'
    );
}


/*
 * --------------------------------------------------
 * 4. Проверка пользователя PHP
 * --------------------------------------------------
 */

function dilitant_http_verify_cli_identity(): void
{
    if (
        !function_exists('posix_geteuid')
        || !function_exists('posix_getegid')
    ) {
        throw new DilitantHttpEnvironmentException(
            'POSIX process identity is unavailable.'
        );
    }


    if (posix_geteuid() !== 33) {
        throw new DilitantHttpEnvironmentException(
            'HTTP test PHP must execute as UID 33.'
        );
    }


    if (posix_getegid() !== 33) {
        throw new DilitantHttpEnvironmentException(
            'HTTP test PHP must execute as GID 33.'
        );
    }
}


/*
 * --------------------------------------------------
 * 5. Проверка файлов WordPress
 * --------------------------------------------------
 */

function dilitant_http_verify_wordpress_source(): void
{
    $root = '/var/www/html';


    if (
        realpath($root)
        !== $root
    ) {
        throw new DilitantHttpEnvironmentException(
            'Unexpected WordPress root.'
        );
    }


    $required = [
        $root . '/wp-load.php',

        $root . '/wp-settings.php',

        $root . '/wp-config.php',

        $root
            . '/wp-content/themes/dilitant-land/functions.php',
    ];


    foreach ($required as $path) {
        if (
            !is_file($path)
            || !is_readable($path)
        ) {
            throw new DilitantHttpEnvironmentException(
                "Required WordPress file unavailable: {$path}"
            );
        }
    }
}


/*
 * --------------------------------------------------
 * 6. Проверка идентичности HTTP-конфигурации
 * --------------------------------------------------
 *
 * Python Environment монтирует:
 *
 *     /tests/integration/editorial/article-http/wp-config.php
 *
 * в:
 *
 *     /var/www/html/wp-config.php
 *
 * Перед загрузкой WordPress подтверждаем
 * совпадение содержимого файлов.
 */

function dilitant_http_verify_config_identity(): void
{
    $expected_path =
        dirname(__DIR__)
        . '/wp-config.php';


    $mounted_path =
        '/var/www/html/wp-config.php';


    foreach (
        [
            $expected_path,
            $mounted_path,
        ] as $path
    ) {
        if (
            !is_file($path)
            || !is_readable($path)
            || is_link($path)
        ) {
            throw new DilitantHttpEnvironmentException(
                'HTTP configuration file is unavailable '
                . 'or has an unexpected type.'
            );
        }
    }


    $expected_hash = hash_file(
        'sha256',
        $expected_path
    );


    $actual_hash = hash_file(
        'sha256',
        $mounted_path
    );


    if (
        !is_string($expected_hash)
        || !is_string($actual_hash)
    ) {
        throw new DilitantHttpEnvironmentException(
            'Unable to verify HTTP configuration identity.'
        );
    }


    if (
        !hash_equals(
            $expected_hash,
            $actual_hash
        )
    ) {
        throw new DilitantHttpEnvironmentException(
            'Mounted WordPress configuration differs '
            . 'from the HTTP test configuration.'
        );
    }
}


/*
 * --------------------------------------------------
 * 7. Общая предварительная проверка
 * --------------------------------------------------
 */

function dilitant_http_verify_bootstrap_environment(): void
{
    if (PHP_SAPI !== 'cli') {
        throw new DilitantHttpEnvironmentException(
            'HTTP test Bootstrap requires PHP CLI.'
        );
    }


    dilitant_http_verify_environment_variables();

    dilitant_http_verify_environment_secrets();

    dilitant_http_verify_uninitialized_wordpress();

    dilitant_http_verify_cli_identity();

    dilitant_http_verify_wordpress_source();

    dilitant_http_verify_config_identity();
}