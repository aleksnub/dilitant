<?php
/**
 * Загрузка WordPress для HTTP-испытаний.
 *
 * Нормативная основа:
 *     HTTP-D01 — Environment
 *     HTTP-D02 — Fixtures & Authentication
 *     HF-D01 — Shared Test Bootstrap
 *
 * Ответственность:
 *     - загрузка действительной установки WordPress;
 *     - использование смонтированного wp-config.php;
 *     - сохранение общих HTTP и CLI salts;
 *     - подтверждение завершения загрузки.
 *
 * Компонент не формирует отдельную
 * конфигурацию базы данных.
 *
 * Компонент не генерирует ключи безопасности.
 *
 * Компонент подключается исключительно
 * из глобальной области видимости Bootstrap.
 */

declare(strict_types=1);


if (PHP_SAPI !== 'cli') {
    http_response_code(403);

    exit(1);
}


/*
 * --------------------------------------------------
 * 1. Проверка разрешения загрузки
 * --------------------------------------------------
 */

if (
    !defined('DILITANT_HTTP_BOOTSTRAP_PRECHECKED')
    || DILITANT_HTTP_BOOTSTRAP_PRECHECKED !== true
) {
    throw new RuntimeException(
        'HTTP Bootstrap preflight has not completed.'
    );
}


/*
 * --------------------------------------------------
 * 2. Загрузка WordPress
 * --------------------------------------------------
 *
 * Используется стандартный механизм:
 *
 *     wp-load.php
 *         ↓
 *     wp-config.php
 *         ↓
 *     wp-settings.php
 *
 * HTTP wp-config.php уже реализует
 * необходимые проверки изоляции базы.
 *
 * Общие salts остаются идентичными
 * для CLI и Apache текущего контейнера.
 */

require_once '/var/www/html/wp-load.php';


/*
 * --------------------------------------------------
 * 3. Проверка завершения загрузки
 * --------------------------------------------------
 */

if (
    !defined('ABSPATH')
    || !defined('DB_NAME')
    || !class_exists('wpdb', false)
    || !function_exists('is_blog_installed')
    || !function_exists('did_action')
) {
    throw new DilitantHttpWordPressException(
        'WordPress initialization is incomplete.'
    );
}


if (did_action('init') < 1) {
    throw new DilitantHttpWordPressException(
        'WordPress init action has not completed.'
    );
}


if (did_action('wp_loaded') < 1) {
    throw new DilitantHttpWordPressException(
        'WordPress wp_loaded action has not completed.'
    );
}


if (!is_blog_installed()) {
    throw new DilitantHttpWordPressException(
        'Test WordPress installation is unavailable.'
    );
}


if (
    is_multisite()
) {
    throw new DilitantHttpWordPressException(
        'Multisite is not supported by HTTP test Bootstrap.'
    );
}