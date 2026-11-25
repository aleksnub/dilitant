<?php
/**
 * Координация HTTP Test Bootstrap.
 *
 * Нормативная основа:
 *     HTTP-D02 — Fixtures & Authentication
 *     HF-D01 — Shared Test Bootstrap
 *
 * Ответственность:
 *     - координация предварительных проверок;
 *     - разрешение загрузки WordPress;
 *     - координация итоговых проверок;
 *     - публикация признака готовности;
 *     - предоставление интерфейса
 *       повторной проверки окружения.
 *
 * Компонент не создаёт испытательные объекты.
 */

declare(strict_types=1);


if (PHP_SAPI !== 'cli') {
    http_response_code(403);

    exit(1);
}


/*
 * --------------------------------------------------
 * 1. Предварительная инициализация
 * --------------------------------------------------
 */

function dilitant_http_bootstrap_preinitialize(): void
{
    if (
        defined('DILITANT_HTTP_BOOTSTRAP_PRECHECKED')
        || defined('DILITANT_HTTP_BOOTSTRAP_READY')
    ) {
        throw new DilitantHttpEnvironmentException(
            'HTTP Bootstrap initialization already started.'
        );
    }


    /*
     * Проверяем окружение до загрузки WordPress.
     */

    dilitant_http_verify_bootstrap_environment();


    /*
     * Разрешаем подключение wordpress.php.
     */

    define(
        'DILITANT_HTTP_BOOTSTRAP_PRECHECKED',
        true
    );
}


/*
 * --------------------------------------------------
 * 2. Завершение инициализации
 * --------------------------------------------------
 *
 * Вызывается после действительной загрузки
 * WordPress и подключения Article Fixtures.
 */

function dilitant_http_bootstrap_finalize(): void
{
    if (
        !defined('DILITANT_HTTP_BOOTSTRAP_PRECHECKED')
        || DILITANT_HTTP_BOOTSTRAP_PRECHECKED !== true
    ) {
        throw new DilitantHttpEnvironmentException(
            'HTTP Bootstrap preflight is incomplete.'
        );
    }


    if (
        defined('DILITANT_HTTP_BOOTSTRAP_READY')
    ) {
        throw new DilitantHttpEnvironmentException(
            'HTTP Bootstrap already finalized.'
        );
    }


    /*
     * Проверяем фактическое окружение.
     */

    dilitant_http_verify_wordpress_isolation();


    /*
     * Проверяем все необходимые интерфейсы.
     */

    dilitant_http_verify_bootstrap_interfaces();


    /*
     * Только после успешного завершения
     * проверок публикуем признак готовности.
     */

    define(
        'DILITANT_HTTP_BOOTSTRAP_READY',
        true
    );
}


/*
 * --------------------------------------------------
 * 3. Проверка готовности
 * --------------------------------------------------
 *
 * Используется последующими компонентами:
 *
 *     Fixtures
 *
 *     Authentication
 *
 *     Manifest
 *
 *     Autosave
 *
 *     Cleanup
 */

function dilitant_http_require_bootstrap(): void
{
    if (
        !defined('DILITANT_HTTP_BOOTSTRAP_READY')
        || DILITANT_HTTP_BOOTSTRAP_READY !== true
    ) {
        throw new DilitantHttpEnvironmentException(
            'HTTP Bootstrap is not initialized.'
        );
    }


    /*
     * Признак готовности не заменяет
     * проверку текущего состояния.
     *
     * Перед последующими операциями
     * повторно подтверждаем изоляцию.
     */

    dilitant_http_verify_wordpress_isolation();
}


/*
 * --------------------------------------------------
 * 4. Получение идентичности окружения
 * --------------------------------------------------
 *
 * Предоставляет машинные сведения
 * для последующих испытательных компонентов.
 *
 * Не возвращает:
 *
 *     - пароль базы;
 *     - authentication keys;
 *     - salts;
 *     - секрет контейнера;
 *     - session tokens;
 *     - authentication cookies.
 */

function dilitant_http_get_bootstrap_identity(): array
{
    dilitant_http_require_bootstrap();


    global $wpdb;


    return [
        'schema_version' =>
            1,

        'database' =>
            $wpdb->get_var(
                'SELECT DATABASE()'
            ),

        'database_user' =>
            $wpdb->get_var(
                'SELECT CURRENT_USER()'
            ),

        'table_prefix' =>
            $wpdb->prefix,

        'base_table_prefix' =>
            $wpdb->base_prefix,

        'home' =>
            home_url(),

        'siteurl' =>
            site_url(),

        'theme' =>
            get_stylesheet(),

        'uid' =>
            posix_geteuid(),

        'gid' =>
            posix_getegid(),

        'active_plugins' =>
            get_option(
                'active_plugins'
            ),

        'permalink_structure' =>
            get_option(
                'permalink_structure'
            ),

        'bootstrap_ready' =>
            true,
    ];
}