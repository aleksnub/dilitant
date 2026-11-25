<?php
/**
 * Точка входа интеграционных испытаний
 * политики выборки Article Model.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D02 — Защита инвариантов WP_Query
 *
 * Испытания:
 *     AM-T06 — тип записи;
 *     AM-T07 — статус и парольная политика;
 *     AM-T08 — дерево категорий;
 *     AM-QN  — нормализация аргументов;
 *     AM-QI  — интеграция аргументов.
 *
 * Обязательное количество сценариев: 52.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}


/*
 * Контроль штатного завершения.
 *
 * Наличие кода завершения 0 само по себе
 * недостаточно для признания испытаний
 * успешно выполненными.
 */
$article_query_run_completed = false;

register_shutdown_function(
    static function () use (
        &$article_query_run_completed
    ): void {
        if (!$article_query_run_completed) {
            fwrite(
                STDERR,
                "FAIL: Article Query runner did not complete.\n"
            );

            exit(1);
        }
    }
);


/*
 * Инициализация изолированной среды.
 */
require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/fixtures.php';


/*
 * Общие механизмы исполнения и проверки.
 */
require_once __DIR__
    . '/article-query/execution.php';

require_once __DIR__
    . '/article-query/assertions.php';


/*
 * Нормативные испытания.
 */
require_once __DIR__
    . '/article-query/test-t06.php';

require_once __DIR__
    . '/article-query/test-t07.php';

require_once __DIR__
    . '/article-query/test-t08.php';


/*
 * Дополнительные испытания.
 */
require_once __DIR__
    . '/article-query/test-normalization.php';

require_once __DIR__
    . '/article-query/test-integration.php';


/*
 * Исполнитель испытаний.
 */
require_once __DIR__
    . '/article-query/runner.php';


/*
 * Непосредственное выполнение.
 */
try {
    if (!function_exists(
        'dilitant_query_test_run'
    )) {
        throw new RuntimeException(
            'Article Query runner is unavailable.'
        );
    }

    $exit_code = dilitant_query_test_run();

    if (!in_array($exit_code, [0, 1], true)) {
        throw new RuntimeException(
            'Runner returned an invalid exit code.'
        );
    }

    $article_query_run_completed = true;

    exit($exit_code);

} catch (Throwable $error) {
    fwrite(
        STDERR,
        "FAIL: Article Query execution\n"
        . $error->getMessage()
        . PHP_EOL
    );

    $article_query_run_completed = true;

    exit(1);
}