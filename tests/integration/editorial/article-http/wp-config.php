<?php
/**
 * Изолированная конфигурация WordPress
 * для интеграционных HTTP-испытаний.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *     HTTP-D01 — HTTP Environment
 *
 * Ответственность:
 *     - проверка тестового окружения;
 *     - изоляция базы данных;
 *     - изоляция конфигурации WordPress;
 *     - общие salts для CLI и HTTP;
 *     - блокировка сторонних плагинов;
 *     - безопасная загрузка WordPress.
 *
 * Использование в рабочем окружении
 * запрещено.
 */

declare(strict_types=1);


/**
 * Завершает загрузку при нарушении
 * инварианта тестового окружения.
 *
 * Пароли и значения секретов
 * в диагностике не выводятся.
 */
function dilitant_http_config_abort(
    string $reason
): never {
    error_log(
        'DILITANT HTTP TEST: ' . $reason
    );

    if (PHP_SAPI === 'cli') {
        fwrite(
            STDERR,
            'FAIL: HTTP test environment: '
            . $reason
            . PHP_EOL
        );

    } else {
        if (!headers_sent()) {
            http_response_code(503);

            header(
                'Content-Type: text/plain; charset=UTF-8'
            );

            header(
                'Cache-Control: no-store'
            );
        }

        echo 'HTTP test environment unavailable.';
    }

    exit(1);
}


/**
 * Проверяет обязательное значение
 * переменной окружения.
 */
function dilitant_http_config_require_env(
    string $name,
    string $expected
): void {
    if (getenv($name) !== $expected) {
        dilitant_http_config_abort(
            'Unexpected environment variable: '
            . $name
        );
    }
}


/*
 * 1. Проверка режима исполнения.
 */
dilitant_http_config_require_env(
    'DILITANT_TEST_MODE',
    '1'
);

dilitant_http_config_require_env(
    'DILITANT_HTTP_TEST_MODE',
    '1'
);


/*
 * 2. Проверка разрешённой базы.
 */
dilitant_http_config_require_env(
    'DILITANT_TEST_DB_NAME',
    'dilitant_land_test'
);

dilitant_http_config_require_env(
    'DILITANT_TEST_DB_USER',
    'dilitant_test'
);

dilitant_http_config_require_env(
    'DILITANT_TEST_DB_HOST',
    'dilitant_land_db:3306'
);


/*
 * 3. Проверка адреса HTTP-стенда.
 */
dilitant_http_config_require_env(
    'DILITANT_HTTP_TEST_URL',
    'http://127.0.0.1:8095'
);


/*
 * 4. Проверка обязательных секретов.
 *
 * Секреты существуют только
 * в окружении временного контейнера.
 */
$dilitant_http_db_password =
    getenv('DILITANT_TEST_DB_PASSWORD');

$dilitant_http_secret =
    getenv('DILITANT_HTTP_TEST_SECRET');

if (
    !is_string($dilitant_http_db_password)
    || $dilitant_http_db_password === ''
) {
    dilitant_http_config_abort(
        'Database password is missing.'
    );
}

if (
    !is_string($dilitant_http_secret)
    || preg_match(
        '/\A[0-9a-f]{128}\z/',
        $dilitant_http_secret
    ) !== 1
) {
    dilitant_http_config_abort(
        'Invalid HTTP test secret.'
    );
}


/*
 * 5. Определение корневого каталога.
 */
if (!defined('ABSPATH')) {
    define(
        'ABSPATH',
        __DIR__ . '/'
    );
}

if (
    realpath(ABSPATH)
    !== '/var/www/html'
) {
    dilitant_http_config_abort(
        'Unexpected WordPress root.'
    );
}


/*
 * 6. Запрет предварительно загруженной
 * конфигурации базы данных.
 */
foreach (
    [
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
        'DB_HOST',
    ] as $constant
) {
    if (defined($constant)) {
        dilitant_http_config_abort(
            'Database configuration already defined.'
        );
    }
}


/*
 * 7. Проверка WordPress drop-ins.
 *
 * db.php может подменить подключение
 * к базе данных.
 *
 * object-cache.php может использовать
 * внешнее или рабочее хранилище.
 *
 * advanced-cache.php может возвращать
 * ранее сформированные ответы.
 */
foreach (
    [
        'db.php',
        'object-cache.php',
        'advanced-cache.php',
        'sunrise.php',
    ] as $dropin
) {
    $dropin_path =
        ABSPATH
        . 'wp-content/'
        . $dropin;

    if (
        file_exists($dropin_path)
        || is_link($dropin_path)
    ) {
        dilitant_http_config_abort(
            'Forbidden WordPress drop-in: '
            . $dropin
        );
    }
}


/*
 * 8. Независимая проверка MariaDB.
 *
 * Выполняется до подключения
 * wp-settings.php.
 */
if (!extension_loaded('mysqli')) {
    dilitant_http_config_abort(
        'mysqli extension is unavailable.'
    );
}

mysqli_report(
    MYSQLI_REPORT_OFF
);

$dilitant_http_db =
    mysqli_init();

if (!$dilitant_http_db instanceof mysqli) {
    dilitant_http_config_abort(
        'Unable to initialize mysqli.'
    );
}

$dilitant_http_db->options(
    MYSQLI_OPT_CONNECT_TIMEOUT,
    5
);


if (
    !@$dilitant_http_db->real_connect(
        'dilitant_land_db',
        'dilitant_test',
        $dilitant_http_db_password,
        'dilitant_land_test',
        3306
    )
) {
    dilitant_http_config_abort(
        'Unable to connect to test database.'
    );
}


/*
 * Проверяем действительную базу,
 * а не только переданные реквизиты.
 */
$dilitant_http_result =
    $dilitant_http_db->query(
        'SELECT DATABASE() AS database_name,
                CURRENT_USER() AS database_user'
    );

if (
    !$dilitant_http_result
    instanceof mysqli_result
) {
    dilitant_http_config_abort(
        'Unable to verify database identity.'
    );
}

$dilitant_http_identity =
    $dilitant_http_result->fetch_assoc();

$dilitant_http_result->free();


if (
    !is_array($dilitant_http_identity)
    || ($dilitant_http_identity['database_name'] ?? null)
        !== 'dilitant_land_test'
    || ($dilitant_http_identity['database_user'] ?? null)
        !== 'dilitant_test@%'
) {
    dilitant_http_config_abort(
        'Unexpected database identity.'
    );
}


/*
 * 9. Проверка изоляции рабочей базы.
 *
 * Операция select_db() не изменяет
 * данные рабочей базы.
 *
 * Тестовый пользователь не должен
 * иметь права переключения на неё.
 */
if (
    @$dilitant_http_db->select_db(
        'dilitant_land_wp'
    )
) {
    $dilitant_http_db->close();

    dilitant_http_config_abort(
        'Test account can access production database.'
    );
}

if (
    $dilitant_http_db->errno !== 1044
) {
    $dilitant_http_db->close();

    dilitant_http_config_abort(
        'Unexpected production isolation result.'
    );
}


/*
 * Повторно подтверждаем возможность
 * работы с разрешённой тестовой БД.
 */
if (
    !$dilitant_http_db->select_db(
        'dilitant_land_test'
    )
) {
    dilitant_http_config_abort(
        'Unable to restore test database selection.'
    );
}


/*
 * 10. Проверка обязательных таблиц.
 */
$dilitant_http_expected_tables = [
    'wp_test_commentmeta',
    'wp_test_comments',
    'wp_test_links',
    'wp_test_options',
    'wp_test_postmeta',
    'wp_test_posts',
    'wp_test_term_relationships',
    'wp_test_term_taxonomy',
    'wp_test_termmeta',
    'wp_test_terms',
    'wp_test_usermeta',
    'wp_test_users',
];

$dilitant_http_result =
    $dilitant_http_db->query(
        'SHOW TABLES'
    );

if (
    !$dilitant_http_result
    instanceof mysqli_result
) {
    dilitant_http_config_abort(
        'Unable to inspect test tables.'
    );
}

$dilitant_http_actual_tables = [];

while (
    $row =
        $dilitant_http_result->fetch_row()
) {
    $dilitant_http_actual_tables[] =
        $row[0];
}

$dilitant_http_result->free();


sort(
    $dilitant_http_expected_tables
);

sort(
    $dilitant_http_actual_tables
);

if (
    $dilitant_http_actual_tables
    !== $dilitant_http_expected_tables
) {
    dilitant_http_config_abort(
        'Unexpected test database table set.'
    );
}


/*
 * 11. Проверка конфигурации темы
 * и отсутствия активных плагинов.
 */
$dilitant_http_result =
    $dilitant_http_db->query(
        "SELECT option_name, option_value
         FROM wp_test_options
         WHERE option_name IN (
             'active_plugins',
             'template',
             'stylesheet'
         )"
    );

if (
    !$dilitant_http_result
    instanceof mysqli_result
) {
    dilitant_http_config_abort(
        'Unable to inspect WordPress options.'
    );
}

$dilitant_http_options = [];

while (
    $row =
        $dilitant_http_result->fetch_assoc()
) {
    $dilitant_http_options[
        $row['option_name']
    ] = $row['option_value'];
}

$dilitant_http_result->free();


if (
    ($dilitant_http_options['active_plugins'] ?? null)
        !== 'a:0:{}'
    || ($dilitant_http_options['template'] ?? null)
        !== 'dilitant-land'
    || ($dilitant_http_options['stylesheet'] ?? null)
        !== 'dilitant-land'
) {
    dilitant_http_config_abort(
        'Unexpected WordPress configuration.'
    );
}


$dilitant_http_db->close();


/*
 * 12. Конфигурация базы WordPress.
 */
define(
    'DB_NAME',
    'dilitant_land_test'
);

define(
    'DB_USER',
    'dilitant_test'
);

define(
    'DB_PASSWORD',
    $dilitant_http_db_password
);

define(
    'DB_HOST',
    'dilitant_land_db:3306'
);

define(
    'DB_CHARSET',
    'utf8mb4'
);

define(
    'DB_COLLATE',
    ''
);

$table_prefix = 'wp_test_';


/*
 * 13. Общие ключи аутентификации.
 *
 * Один временный секрет используется
 * для детерминированного формирования
 * восьми независимых ключей.
 *
 * Все CLI и HTTP-процессы контейнера
 * получают одинаковые значения.
 *
 * При создании нового контейнера
 * формируется новый секрет.
 */
foreach (
    [
        'AUTH_KEY',
        'SECURE_AUTH_KEY',
        'LOGGED_IN_KEY',
        'NONCE_KEY',
        'AUTH_SALT',
        'SECURE_AUTH_SALT',
        'LOGGED_IN_SALT',
        'NONCE_SALT',
    ] as $key
) {
    define(
        $key,

        hash_hmac(
            'sha512',
            $key,
            $dilitant_http_secret
        )
    );
}


/*
 * Удаляем временные переменные
 * с секретными значениями.
 */
unset(
    $dilitant_http_db_password,
    $dilitant_http_secret
);


/*
 * 14. Адрес изолированного сайта.
 *
 * Переопределяем home и siteurl
 * без изменения сохранённых
 * значений тестовой базы.
 */
define(
    'WP_HOME',
    'http://127.0.0.1:8095'
);

define(
    'WP_SITEURL',
    'http://127.0.0.1:8095'
);


/*
 * HTTP-стенд работает исключительно
 * на локальном HTTP без TLS.
 */
define(
    'FORCE_SSL_ADMIN',
    false
);


/*
 * 15. Ограничения окружения.
 */
define(
    'WP_ENVIRONMENT_TYPE',
    'local'
);

define(
    'WP_CACHE',
    false
);

define(
    'DISABLE_WP_CRON',
    true
);

define(
    'DISALLOW_FILE_EDIT',
    true
);

define(
    'DISALLOW_FILE_MODS',
    true
);

define(
    'AUTOMATIC_UPDATER_DISABLED',
    true
);

define(
    'WP_HTTP_BLOCK_EXTERNAL',
    true
);

define(
    'WP_DEBUG',
    true
);

define(
    'WP_DEBUG_DISPLAY',
    false
);

define(
    'WP_DEBUG_LOG',
    false
);

@ini_set(
    'display_errors',
    '0'
);


/*
 * 16. Изоляция MU Plugins.
 *
 * Рабочие must-use plugins
 * не должны загружаться.
 */
define(
    'WPMU_PLUGIN_DIR',
    '/var/www/dilitant-http-disabled-mu-plugins'
);

if (
    file_exists(WPMU_PLUGIN_DIR)
    || is_link(WPMU_PLUGIN_DIR)
) {
    dilitant_http_config_abort(
        'Unexpected MU plugin directory.'
    );
}


/*
 * 17. Запрет multisite.
 */
define(
    'MULTISITE',
    false
);


/*
 * 18. Загрузка WordPress.
 */
require_once ABSPATH
    . 'wp-settings.php';


/*
 * 19. Проверка фактического состояния
 * уже загруженного WordPress.
 */
global $wpdb;

if (
    !$wpdb instanceof wpdb
    || $wpdb->prefix !== 'wp_test_'
    || $wpdb->base_prefix !== 'wp_test_'
) {
    dilitant_http_config_abort(
        'Unexpected loaded WordPress table prefix.'
    );
}

if (
    $wpdb->get_var('SELECT DATABASE()')
    !== 'dilitant_land_test'
) {
    dilitant_http_config_abort(
        'Unexpected loaded WordPress database.'
    );
}

if (
    get_option('active_plugins') !== []
    || get_template() !== 'dilitant-land'
    || get_stylesheet() !== 'dilitant-land'
) {
    dilitant_http_config_abort(
        'Unexpected loaded WordPress configuration.'
    );
}

if (
    home_url() !== WP_HOME
    || site_url() !== WP_SITEURL
) {
    dilitant_http_config_abort(
        'Unexpected loaded WordPress URL.'
    );
}