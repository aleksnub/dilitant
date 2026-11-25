<?php
/**
 * Интеграционные испытания публичной
 * доступности статей и авторизации Preview.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *
 * Обязательные сценарии:
 *     AM-T09.1–AM-T09.9;
 *     AM-T10.1–AM-T10.3;
 *     AM-T11.1–AM-T11.8.
 *
 * Всего: 20 сценариев.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}


/*
 * Контроль штатного завершения.
 */
$visibility_run_completed = false;

register_shutdown_function(
    static function () use (
        &$visibility_run_completed
    ): void {
        if (!$visibility_run_completed) {
            fwrite(
                STDERR,
                "FAIL: Article Visibility runner did not complete.\n"
            );

            exit(1);
        }
    }
);


/*
 * Изолированная тестовая среда.
 */
require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/fixtures.php';


/*
 * Повторно используем проверенные механизмы
 * сохранения глобального состояния WordPress.
 */
require_once __DIR__
    . '/article-catalog-environment.php';


/*
 * Компоненты AM-D03.
 */
require_once __DIR__
    . '/article-visibility/environment.php';

require_once __DIR__
    . '/article-visibility/assertions.php';

require_once __DIR__
    . '/article-visibility/routing.php';

require_once __DIR__
    . '/article-visibility/public-visibility.php';

require_once __DIR__
    . '/article-visibility/password-protection.php';

require_once __DIR__
    . '/article-visibility/preview-authorization.php';

require_once __DIR__
    . '/article-visibility/runner.php';


/*
 * Запуск нормативного набора испытаний.
 */
try {
    $exit_code =
        dilitant_visibility_run();

    dilitant_test_assert(
        in_array($exit_code, [0, 1], true),
        'Invalid visibility runner exit code.'
    );

    $visibility_run_completed = true;

    exit($exit_code);

} catch (Throwable $error) {
    fwrite(
        STDERR,
        "FAIL: Article Visibility execution\n"
        . $error->getMessage()
        . PHP_EOL
    );

    $visibility_run_completed = true;

    exit(1);
}