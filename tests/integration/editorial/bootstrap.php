<?php
/**
 * Инициализация интеграционных испытаний Editorial.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-T01 — AM-T05
 *
 * Испытания допускаются исключительно
 * в выделенной тестовой базе данных.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

const DILITANT_TEST_DB_NAME = 'dilitant_land_test';
const DILITANT_TEST_TABLE_PREFIX = 'wp_test_';


/**
 * Аварийное завершение инициализации.
 */
function dilitant_test_abort(string $message): never
{
    fwrite(STDERR, "ABORT: {$message}\n");

    exit(1);
}


/*
 * Проверка явного разрешения запуска испытаний.
 */
if (getenv('DILITANT_TEST_MODE') !== '1') {
    dilitant_test_abort(
        'Test mode is not explicitly enabled.'
    );
}


/*
 * Запрет повторной инициализации WordPress.
 */
if (defined('ABSPATH') || defined('DB_NAME')) {
    dilitant_test_abort(
        'WordPress has already been initialized.'
    );
}


/*
 * Получение реквизитов тестовой базы данных.
 */
$db_name = getenv('DILITANT_TEST_DB_NAME');
$db_user = getenv('DILITANT_TEST_DB_USER');
$db_password = getenv('DILITANT_TEST_DB_PASSWORD');
$db_host = getenv('DILITANT_TEST_DB_HOST');

if ($db_name !== DILITANT_TEST_DB_NAME) {
    dilitant_test_abort(
        'Unexpected database name.'
    );
}

if ($db_user !== 'dilitant_test') {
    dilitant_test_abort(
        'Unexpected database user.'
    );
}

if ($db_host !== 'dilitant_land_db:3306') {
    dilitant_test_abort(
        'Unexpected database host.'
    );
}

if ($db_password === false || $db_password === '') {
    dilitant_test_abort(
        'Test database password is missing.'
    );
}


/*
 * Корневой каталог WordPress внутри контейнера.
 */
$wp_root = '/var/www/html/';

if (!is_file($wp_root . 'wp-settings.php')) {
    dilitant_test_abort(
        'WordPress installation not found.'
    );
}


/*
 * Предварительная проверка подключения.
 *
 * Выполняется до загрузки WordPress.
 */
mysqli_report(
    MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT
);

try {
    $connection = new mysqli(
        'dilitant_land_db',
        $db_user,
        $db_password,
        $db_name,
        3306
    );

    $result = $connection->query(
        'SELECT DATABASE()'
    );

    $actual_database = $result->fetch_row()[0] ?? null;

    if ($actual_database !== DILITANT_TEST_DB_NAME) {
        dilitant_test_abort(
            'Database identity verification failed.'
        );
    }

    /*
     * Проверяем, что тестовому пользователю
     * недоступна рабочая база.
     */
    $production_access = false;

    try {
        $connection->select_db('dilitant_land_wp');
        $production_access = true;
    } catch (mysqli_sql_exception $error) {
        if ((int) $error->getCode() !== 1044) {
            throw $error;
        }
    }

    if ($production_access) {
        dilitant_test_abort(
            'Test user has access to production database.'
        );
    }

    $connection->close();

} catch (Throwable $error) {
    dilitant_test_abort(
        'Database isolation verification failed: '
        . $error->getMessage()
    );
}


/*
 * Проверка потенциальных механизмов подмены
 * подключения и объектного кеширования.
 *
 * Такие компоненты не допускаются в данной
 * конфигурации интеграционных испытаний.
 */
foreach ([
    'db.php',
    'object-cache.php',
    'advanced-cache.php',
] as $dropin) {
    if (is_file($wp_root . 'wp-content/' . $dropin)) {
        dilitant_test_abort(
            "Unsupported WordPress drop-in: {$dropin}"
        );
    }
}


/*
 * Формирование отдельной конфигурации.
 *
 * Рабочий wp-config.php намеренно не загружается.
 */
define('ABSPATH', $wp_root);

define('DB_NAME', $db_name);
define('DB_USER', $db_user);
define('DB_PASSWORD', $db_password);
define('DB_HOST', $db_host);
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

define('WP_ENVIRONMENT_TYPE', 'local');

define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', true);
define('WP_DEBUG_LOG', false);

define('WP_CACHE', false);
define('DISABLE_WP_CRON', true);

define('AUTOMATIC_UPDATER_DISABLED', true);
define('DISALLOW_FILE_MODS', true);
define('WP_HTTP_BLOCK_EXTERNAL', true);

define('WP_HOME', 'http://dilitant-test.invalid');
define('WP_SITEURL', 'http://dilitant-test.invalid');

/*
 * Исключаем автоматическую загрузку MU-плагинов.
 */
define(
    'WPMU_PLUGIN_DIR',
    '/nonexistent/dilitant-test-mu-plugins'
);

$table_prefix = DILITANT_TEST_TABLE_PREFIX;


/*
 * Изолированные ключи безопасности WordPress.
 */
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
    define(
        $key,
        bin2hex(random_bytes(32))
    );
}


/*
 * Проверяем параметры установленной тестовой среды
 * до загрузки плагинов и темы WordPress.
 */
try {
    $connection = new mysqli(
        'dilitant_land_db',
        $db_user,
        $db_password,
        $db_name,
        3306
    );

    $result = $connection->query(
        "SELECT option_name, option_value
         FROM wp_test_options
         WHERE option_name IN (
             'active_plugins',
             'template',
             'stylesheet'
         )"
    );

    $options = [];

    while ($row = $result->fetch_assoc()) {
        $options[$row['option_name']] =
            $row['option_value'];
    }

    $connection->close();

    if (($options['active_plugins'] ?? null) !== 'a:0:{}') {
        dilitant_test_abort(
            'Active plugins are not allowed.'
        );
    }

    if (
        ($options['template'] ?? null) !== 'dilitant-land'
        || ($options['stylesheet'] ?? null) !== 'dilitant-land'
    ) {
        dilitant_test_abort(
            'Unexpected test WordPress theme.'
        );
    }

} catch (Throwable $error) {
    dilitant_test_abort(
        'Test WordPress is not initialized correctly: '
        . $error->getMessage()
    );
}


/*
 * Возвращаем стандартное поведение mysqli
 * перед загрузкой WordPress.
 */
mysqli_report(MYSQLI_REPORT_OFF);


/*
 * Загрузка WordPress.
 */
require ABSPATH . 'wp-settings.php';

global $wpdb;


/*
 * Повторная проверка фактического подключения.
 */
if ($wpdb->prefix !== DILITANT_TEST_TABLE_PREFIX) {
    dilitant_test_abort(
        'Unexpected WordPress table prefix.'
    );
}

if (
    $wpdb->get_var('SELECT DATABASE()')
    !== DILITANT_TEST_DB_NAME
) {
    dilitant_test_abort(
        'WordPress is connected to an unexpected database.'
    );
}

if (!is_blog_installed()) {
    dilitant_test_abort(
        'Test WordPress installation is not initialized.'
    );
}

if (!empty(get_option('active_plugins', []))) {
    dilitant_test_abort(
        'Unexpected active WordPress plugins.'
    );
}

if (get_stylesheet() !== 'dilitant-land') {
    dilitant_test_abort(
        'Unexpected active WordPress theme.'
    );
}


/*
 * Проверка испытуемого интерфейса Article Model.
 */
$required_functions = [
    'dilitant_land_get_blog_category_id',
    'dilitant_land_get_blog_category_ids',
    'dilitant_land_is_blog_article',
    'dilitant_land_get_blog_articles',
];

foreach ($required_functions as $function) {
    if (!function_exists($function)) {
        dilitant_test_abort(
            "Required function missing: {$function}"
        );
    }
}


/**
 * Проверяет логическое условие.
 *
 * Не зависит от настройки zend.assertions.
 */
function dilitant_test_assert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}


/**
 * Проверяет строгое равенство значений.
 */
function dilitant_test_assert_same(
    mixed $expected,
    mixed $actual,
    string $message
): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message
            . "\nExpected: "
            . var_export($expected, true)
            . "\nActual: "
            . var_export($actual, true)
        );
    }
}


/**
 * Нормализует массив идентификаторов.
 *
 * Дубликаты намеренно сохраняются,
 * поскольку их появление является
 * потенциальным дефектом выборки.
 */
function dilitant_test_normalize_ids(
    array $ids
): array {
    $ids = array_map('intval', $ids);

    sort($ids, SORT_NUMERIC);

    return array_values($ids);
}


/**
 * Получает идентификаторы записей WP_Query.
 */
function dilitant_test_query_ids(
    WP_Query $query
): array {
    $ids = [];

    foreach ($query->posts as $post) {
        dilitant_test_assert(
            $post instanceof WP_Post,
            'Query returned an unexpected object.'
        );

        $ids[] = (int) $post->ID;
    }

    return dilitant_test_normalize_ids($ids);
}


echo "PASS: database isolation\n";
echo "PASS: isolated WordPress test environment\n";
echo "Database: " . DB_NAME . PHP_EOL;
echo "Table prefix: " . $wpdb->prefix . PHP_EOL;
echo "Theme: " . get_stylesheet() . PHP_EOL;