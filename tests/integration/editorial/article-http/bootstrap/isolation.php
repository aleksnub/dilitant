<?php
/**
 * Проверка изоляции HTTP Test WordPress.
 *
 * Нормативная основа:
 *     HTTP-D01 — Environment
 *     HTTP-D02 — Fixtures & Authentication
 *     HF-D01 — Shared Test Bootstrap
 *
 * Ответственность:
 *     - проверка конфигурации базы данных;
 *     - проверка фактического подключения;
 *     - проверка таблиц WordPress;
 *     - проверка активной темы;
 *     - проверка отсутствия плагинов;
 *     - проверка локального URL;
 *     - проверка обязательных ограничений;
 *     - проверка общих ключей авторизации.
 *
 * Компонент не изменяет состояние WordPress.
 */

declare(strict_types=1);


if (PHP_SAPI !== 'cli') {
    http_response_code(403);

    exit(1);
}


/*
 * --------------------------------------------------
 * 1. Проверка конфигурации базы данных
 * --------------------------------------------------
 */

function dilitant_http_verify_database_configuration(): void
{
    $expected = [
        'DB_NAME' =>
            DILITANT_TEST_DB_NAME,

        'DB_USER' =>
            'dilitant_test',

        'DB_HOST' =>
            'dilitant_land_db:3306',
    ];


    foreach (
        $expected
        as $constant => $value
    ) {
        if (
            !defined($constant)
            || constant($constant) !== $value
        ) {
            throw new DilitantHttpIsolationException(
                "Unexpected database configuration: "
                . $constant
            );
        }
    }
}


/*
 * --------------------------------------------------
 * 2. Проверка действительного подключения
 * --------------------------------------------------
 */

function dilitant_http_verify_database_connection(): void
{
    global $wpdb;


    if (!$wpdb instanceof wpdb) {
        throw new DilitantHttpIsolationException(
            'WordPress database connection is unavailable.'
        );
    }


    $database = $wpdb->get_var(
        'SELECT DATABASE()'
    );


    if ($database !== DILITANT_TEST_DB_NAME) {
        throw new DilitantHttpIsolationException(
            'Unexpected active database.'
        );
    }


    $account = $wpdb->get_var(
        'SELECT CURRENT_USER()'
    );


    if ($account !== 'dilitant_test@%') {
        throw new DilitantHttpIsolationException(
            'Unexpected authenticated database account.'
        );
    }


    if (
        $wpdb->prefix
        !== DILITANT_TEST_TABLE_PREFIX
    ) {
        throw new DilitantHttpIsolationException(
            'Unexpected WordPress table prefix.'
        );
    }


    if (
        $wpdb->base_prefix
        !== DILITANT_TEST_TABLE_PREFIX
    ) {
        throw new DilitantHttpIsolationException(
            'Unexpected WordPress base table prefix.'
        );
    }
}


/*
 * --------------------------------------------------
 * 3. Проверка активной темы
 * --------------------------------------------------
 */

function dilitant_http_verify_active_theme(): void
{
    if (
        get_template()
        !== 'dilitant-land'
    ) {
        throw new DilitantHttpIsolationException(
            'Unexpected WordPress template.'
        );
    }


    if (
        get_stylesheet()
        !== 'dilitant-land'
    ) {
        throw new DilitantHttpIsolationException(
            'Unexpected WordPress stylesheet.'
        );
    }
}


/*
 * --------------------------------------------------
 * 4. Проверка изоляции плагинов
 * --------------------------------------------------
 */

function dilitant_http_verify_plugin_isolation(): void
{
    if (
        get_option('active_plugins')
        !== []
    ) {
        throw new DilitantHttpIsolationException(
            'Unexpected active WordPress plugins.'
        );
    }


    if (
        !defined('WPMU_PLUGIN_DIR')
        || WPMU_PLUGIN_DIR
            !== '/var/www/dilitant-http-disabled-mu-plugins'
    ) {
        throw new DilitantHttpIsolationException(
            'Unexpected MU plugin directory.'
        );
    }


    if (
        file_exists(WPMU_PLUGIN_DIR)
        || is_link(WPMU_PLUGIN_DIR)
    ) {
        throw new DilitantHttpIsolationException(
            'MU plugin directory must be unavailable.'
        );
    }


    $dropins = [
        'db.php',

        'object-cache.php',

        'advanced-cache.php',

        'sunrise.php',
    ];


    foreach ($dropins as $name) {
        $path =
            ABSPATH
            . 'wp-content/'
            . $name;


        if (
            file_exists($path)
            || is_link($path)
        ) {
            throw new DilitantHttpIsolationException(
                "Unexpected WordPress drop-in: {$name}"
            );
        }
    }
}


/*
 * --------------------------------------------------
 * 5. Проверка идентичности URL
 * --------------------------------------------------
 */

function dilitant_http_verify_site_identity(): void
{
    $expected_url =
        'http://127.0.0.1:8095';


    if (
        !defined('WP_HOME')
        || WP_HOME !== $expected_url
    ) {
        throw new DilitantHttpIsolationException(
            'Unexpected WP_HOME.'
        );
    }


    if (
        !defined('WP_SITEURL')
        || WP_SITEURL !== $expected_url
    ) {
        throw new DilitantHttpIsolationException(
            'Unexpected WP_SITEURL.'
        );
    }


    if (
        home_url()
        !== $expected_url
    ) {
        throw new DilitantHttpIsolationException(
            'Unexpected effective home URL.'
        );
    }


    if (
        site_url()
        !== $expected_url
    ) {
        throw new DilitantHttpIsolationException(
            'Unexpected effective site URL.'
        );
    }


    if (
        force_ssl_admin() !== false
    ) {
        throw new DilitantHttpIsolationException(
            'Unexpected SSL administration configuration.'
        );
    }
}


/*
 * --------------------------------------------------
 * 6. Проверка ограничений окружения
 * --------------------------------------------------
 */

function dilitant_http_verify_wordpress_restrictions(): void
{
    $expected = [
        'WP_ENVIRONMENT_TYPE' =>
            'local',

        'WP_CACHE' =>
            false,

        'DISABLE_WP_CRON' =>
            true,

        'DISALLOW_FILE_EDIT' =>
            true,

        'DISALLOW_FILE_MODS' =>
            true,

        'AUTOMATIC_UPDATER_DISABLED' =>
            true,

        'WP_HTTP_BLOCK_EXTERNAL' =>
            true,

        'WP_DEBUG' =>
            true,

        'WP_DEBUG_DISPLAY' =>
            false,

        'WP_DEBUG_LOG' =>
            false,

        'MULTISITE' =>
            false,
    ];


    foreach (
        $expected
        as $constant => $value
    ) {
        if (
            !defined($constant)
            || constant($constant) !== $value
        ) {
            throw new DilitantHttpIsolationException(
                "Unexpected WordPress restriction: "
                . $constant
            );
        }
    }
}


/*
 * --------------------------------------------------
 * 7. Проверка общих ключей безопасности
 * --------------------------------------------------
 *
 * HTTP wp-config.php формирует каждый ключ:
 *
 *     hash_hmac(
 *         'sha512',
 *         $key_name,
 *         $container_secret
 *     )
 *
 * Повторно вычисляем ожидаемые значения.
 *
 * Ключи и секреты не выводятся
 * и не передаются в общие assertions.
 */

function dilitant_http_verify_authentication_keys(): void
{
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
        throw new DilitantHttpIsolationException(
            'HTTP authentication secret is unavailable.'
        );
    }


    $keys = [
        'AUTH_KEY',

        'SECURE_AUTH_KEY',

        'LOGGED_IN_KEY',

        'NONCE_KEY',

        'AUTH_SALT',

        'SECURE_AUTH_SALT',

        'LOGGED_IN_SALT',

        'NONCE_SALT',
    ];


    foreach ($keys as $name) {
        if (!defined($name)) {
            throw new DilitantHttpIsolationException(
                "Required authentication key is missing: {$name}"
            );
        }


        $actual = constant(
            $name
        );


        $expected = hash_hmac(
            'sha512',
            $name,
            $secret
        );


        if (
            !is_string($actual)
            || !hash_equals(
                $expected,
                $actual
            )
        ) {
            throw new DilitantHttpIsolationException(
                "Authentication key identity mismatch: {$name}"
            );
        }
    }


    unset(
        $secret,
        $expected,
        $actual
    );
}


/*
 * --------------------------------------------------
 * 8. Полная проверка изоляции
 * --------------------------------------------------
 */

function dilitant_http_verify_wordpress_isolation(): void
{
    dilitant_http_verify_environment_variables();

    dilitant_http_verify_cli_identity();

    dilitant_http_verify_database_configuration();

    dilitant_http_verify_database_connection();

    dilitant_http_verify_active_theme();

    dilitant_http_verify_plugin_isolation();

    dilitant_http_verify_site_identity();

    dilitant_http_verify_wordpress_restrictions();

    dilitant_http_verify_authentication_keys();
}