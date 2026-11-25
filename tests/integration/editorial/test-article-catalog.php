<?php
/**
 * Исполнитель интеграционных испытаний
 * публичного каталога статей.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial, редакция 1.1
 *     EDC-01 — Article Model
 *     AM-D02 — Защита инвариантов WP_Query
 *     AM-D05 — Пагинация каталога
 *
 * Ответственность:
 *     - инициализация тестовой среды;
 *     - подключение компонентов;
 *     - регистрация обязательных сценариев;
 *     - проверка полноты набора;
 *     - исполнение испытаний;
 *     - восстановление окружения;
 *     - очистка тестовых данных;
 *     - формирование итогового отчёта.
 *
 * Обязательные сценарии:
 *     AM-CQ-01 — AM-CQ-10.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}


/*
 * Контроль штатного завершения.
 *
 * Преждевременное завершение PHP
 * не признаётся успешным исполнением.
 */
$catalog_run_completed = false;

register_shutdown_function(
    static function () use (
        &$catalog_run_completed
    ): void {
        if (!$catalog_run_completed) {
            fwrite(
                STDERR,
                "FAIL: Article Catalog runner did not complete.\n"
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
 * Общие механизмы Article Query.
 */
require_once __DIR__
    . '/article-query/execution.php';

require_once __DIR__
    . '/article-query/assertions.php';


/*
 * Окружение испытаний каталога.
 */
require_once __DIR__
    . '/article-catalog-environment.php';


/*
 * Нормативные сценарии.
 */
require_once __DIR__
    . '/article-catalog-scenarios.php';


/*
 * Обязательное количество сценариев.
 */
const DILITANT_CATALOG_EXPECTED_TOTAL = 10;


/**
 * Возвращает точный перечень
 * обязательных идентификаторов.
 */
function dilitant_catalog_expected_ids(): array
{
    $ids = [];

    for (
        $index = 1;
        $index <= DILITANT_CATALOG_EXPECTED_TOTAL;
        $index++
    ) {
        $ids[] = sprintf(
            'AM-CQ-%02d',
            $index
        );
    }

    return $ids;
}


/**
 * Проверяет наличие обязательных
 * интерфейсов испытуемой модели.
 */
function dilitant_catalog_verify_interfaces(): void
{
    $required_functions = [
        'dilitant_land_build_blog_query_args',
        'dilitant_land_build_empty_blog_query_args',
        'dilitant_land_get_blog_articles',
        'dilitant_land_get_blog_category_id',
        'dilitant_land_get_blog_category_ids',
        'dilitant_land_is_blog_catalog_query',
        'dilitant_land_get_blog_catalog_input_args',
        'dilitant_land_get_blog_catalog_term',
        'dilitant_land_apply_blog_catalog_query_args',
        'dilitant_land_apply_blog_catalog_query',
        'dilitant_query_test_assert_invariants',
        'dilitant_query_test_assert_ids',
        'dilitant_query_test_assert_closed_failure',
        'dilitant_query_test_ordered_ids',
        'dilitant_catalog_execute_query',
        'dilitant_catalog_observe_query',
        'dilitant_catalog_with_page_size',
    ];

    foreach ($required_functions as $function) {
        dilitant_test_assert(
            function_exists($function),
            "Required catalog function is missing: {$function}"
        );
    }


    /*
     * Проверяем регистрацию производственного
     * обработчика главного запроса.
     */
    dilitant_test_assert_same(
        100,
        has_action(
            'pre_get_posts',
            'dilitant_land_apply_blog_catalog_query'
        ),
        'Catalog query hook is not registered.'
    );


    /*
     * Проверяем интерфейс Fixtures.
     */
    $required_methods = [
        'create',
        'create_query_fixtures',
        'verify_query_fixtures',
        'expected_category_ids',
        'expected_published_post_ids',
        'excluded_post_ids',
        'all_post_ids',
        'remove_root_category',
        'archive_post_type',
        'fixture_password',
        'run_id',
        'cleanup',
    ];

    foreach ($required_methods as $method) {
        dilitant_test_assert(
            method_exists(
                DilitantArticleFixtures::class,
                $method
            ),
            "Required fixture method is missing: {$method}"
        );
    }

    dilitant_test_assert_same(
        false,
        is_admin(),
        'Unexpected administrative test environment.'
    );

    echo "PASS: required catalog interfaces\n";
}


/**
 * Регистрирует обязательные сценарии.
 *
 * AM-CQ-10 должен выполняться последним,
 * поскольку изменяет нормативное дерево
 * тестовых категорий.
 */
function dilitant_catalog_register_tests(
    DilitantArticleFixtures $fixtures
): array {
    $tests = [];

    foreach (
        dilitant_catalog_expected_ids()
        as $id
    ) {
        $function =
            'dilitant_catalog_test_cq'
            . substr($id, -2);

        dilitant_test_assert(
            function_exists($function),
            "Required scenario is missing: {$id}"
        );

        dilitant_test_assert(
            is_callable($function),
            "Scenario is not callable: {$id}"
        );

        dilitant_test_assert(
            !array_key_exists($id, $tests),
            "Duplicate scenario identifier: {$id}"
        );

        $tests[$id] =
            static function () use (
                $function,
                $fixtures
            ): void {
                $function($fixtures);
            };
    }

    dilitant_test_assert_same(
        DILITANT_CATALOG_EXPECTED_TOTAL,
        count($tests),
        'Unexpected catalog scenario count.'
    );

    dilitant_test_assert_same(
        dilitant_catalog_expected_ids(),
        array_keys($tests),
        'Unexpected catalog scenario manifest.'
    );

    dilitant_test_assert_same(
        'AM-CQ-10',
        array_key_last($tests),
        'Root removal scenario must be executed last.'
    );

    return $tests;
}


/**
 * Исполняет зарегистрированные сценарии.
 *
 * Каждый сценарий получает временное
 * окружение с размером страницы 100.
 *
 * AM-CQ-09 использует отдельный фильтр
 * с более высоким приоритетом.
 */
function dilitant_catalog_execute_tests(
    array $tests
): array {
    $results = [];

    foreach ($tests as $id => $test) {
        $state =
            dilitant_catalog_capture_globals();

        $previous_user_id =
            get_current_user_id();

        $error_message = null;

        try {
            dilitant_catalog_with_page_size(
                100,
                $test,
                1000
            );

            dilitant_catalog_verify_globals(
                $state
            );

            dilitant_test_assert_same(
                $previous_user_id,
                get_current_user_id(),
                'Scenario leaked user context.'
            );

        } catch (Throwable $error) {
            $error_message =
                $error->getMessage();

        } finally {
            try {
                /*
                 * Восстанавливаем контекст
                 * даже после ошибки сценария.
                 */
                if (
                    get_current_user_id()
                    !== $previous_user_id
                ) {
                    wp_set_current_user(
                        $previous_user_id
                    );
                }

                dilitant_catalog_restore_globals(
                    $state
                );

                dilitant_catalog_verify_globals(
                    $state
                );

                dilitant_test_assert_same(
                    $previous_user_id,
                    get_current_user_id(),
                    'Unable to restore user context.'
                );

            } catch (Throwable $restore_error) {
                $error_message =
                    ($error_message ?? '')
                    . PHP_EOL
                    . 'Environment restoration failed: '
                    . $restore_error->getMessage();
            }
        }

        /*
         * PASS допускается только после
         * выполнения сценария и восстановления
         * исходного окружения.
         */
        if ($error_message === null) {
            $results[$id] = [
                'status' => 'PASS',
                'error' => null,
            ];

            echo "PASS: {$id}\n";

        } else {
            $results[$id] = [
                'status' => 'FAIL',
                'error' => $error_message,
            ];

            fwrite(
                STDERR,
                "FAIL: {$id}\n"
                . $error_message
                . PHP_EOL
            );
        }
    }

    return $results;
}


/**
 * Формирует итоговую статистику.
 */
function dilitant_catalog_statistics(
    array $tests,
    array $results
): array {
    $passed = 0;
    $failed = 0;

    foreach ($results as $id => $result) {
        dilitant_test_assert(
            array_key_exists($id, $tests),
            "Result belongs to unknown scenario: {$id}"
        );

        $status = $result['status'] ?? null;

        if ($status === 'PASS') {
            $passed++;

        } elseif ($status === 'FAIL') {
            $failed++;

        } else {
            throw new RuntimeException(
                "Invalid scenario result: {$id}"
            );
        }
    }

    $missing_ids = array_values(
        array_diff(
            dilitant_catalog_expected_ids(),
            array_keys($results)
        )
    );

    return [
        'total' =>
            DILITANT_CATALOG_EXPECTED_TOTAL,

        'registered' =>
            count($tests),

        'executed' =>
            count($results),

        'passed' => $passed,

        'failed' => $failed,

        'not_executed' =>
            count($missing_ids),

        'missing_ids' =>
            $missing_ids,
    ];
}


/**
 * Выводит итоговый отчёт.
 */
function dilitant_catalog_report(
    array $statistics,
    string $initialization_status,
    string $cleanup_status
): void {
    echo PHP_EOL;
    echo "=== RESULT ===\n";

    echo "Total: "
        . $statistics['total']
        . PHP_EOL;

    echo "Registered: "
        . $statistics['registered']
        . PHP_EOL;

    echo "Executed: "
        . $statistics['executed']
        . PHP_EOL;

    echo "Passed: "
        . $statistics['passed']
        . PHP_EOL;

    echo "Failed: "
        . $statistics['failed']
        . PHP_EOL;

    echo "Not executed: "
        . $statistics['not_executed']
        . PHP_EOL;

    echo "Fixture initialization: "
        . $initialization_status
        . PHP_EOL;

    echo "Fixture cleanup: "
        . $cleanup_status
        . PHP_EOL;

    if ($statistics['missing_ids'] !== []) {
        echo "Missing scenarios:\n";

        foreach (
            $statistics['missing_ids']
            as $id
        ) {
            echo "  {$id}\n";
        }
    }
}


/**
 * Главный исполнитель AM-CQ.
 *
 * Возвращает:
 *     0 — все обязательные условия выполнены;
 *     1 — обнаружена ошибка.
 */
function dilitant_catalog_run(): int
{
    $fixtures = null;

    $tests = [];
    $results = [];

    $initialization_status =
        'NOT EXECUTED';

    $cleanup_status =
        'NOT EXECUTED';

    $execution_error = false;

    echo PHP_EOL;
    echo "=== EDC-01 ARTICLE CATALOG ===\n";
    echo PHP_EOL;

    try {
        /*
         * 1. Проверяем интерфейсы.
         */
        dilitant_catalog_verify_interfaces();


        /*
         * 2. Создаём тестовые данные.
         */
        $fixtures =
            new DilitantArticleFixtures();

        $fixtures->create();

        echo "PASS: base fixture creation\n";

        $fixtures->create_query_fixtures();

        echo "PASS: query fixture creation\n";

        $fixtures->verify_query_fixtures();

        echo "PASS: query fixture verification\n";

        $initialization_status = 'PASS';


        /*
         * 3. Регистрируем полный набор.
         */
        $tests =
            dilitant_catalog_register_tests(
                $fixtures
            );

        echo "PASS: catalog test registration\n";

        echo "Registered scenarios: "
            . count($tests)
            . PHP_EOL;

        echo PHP_EOL;


        /*
         * 4. Исполняем испытания.
         */
        $results =
            dilitant_catalog_execute_tests(
                $tests
            );

    } catch (Throwable $error) {
        $execution_error = true;

        if ($initialization_status !== 'PASS') {
            $initialization_status = 'FAIL';
        }

        fwrite(
            STDERR,
            "FAIL: catalog initialization or execution\n"
            . $error->getMessage()
            . PHP_EOL
        );

    } finally {
        /*
         * 5. Очищаем тестовые данные.
         */
        if (
            $fixtures instanceof
                DilitantArticleFixtures
        ) {
            try {
                $fixtures->cleanup();

                $cleanup_status = 'PASS';

            } catch (Throwable $error) {
                $cleanup_status = 'FAIL';

                fwrite(
                    STDERR,
                    "FAIL: catalog fixture cleanup\n"
                    . $error->getMessage()
                    . PHP_EOL
                );
            }
        }
    }


    /*
     * 6. Формируем итоговый отчёт.
     */
    $statistics =
        dilitant_catalog_statistics(
            $tests,
            $results
        );

    dilitant_catalog_report(
        $statistics,
        $initialization_status,
        $cleanup_status
    );


    /*
     * 7. Проверяем критерии приёмки.
     */
    $expected =
        DILITANT_CATALOG_EXPECTED_TOTAL;

    if (
        $execution_error
        || $initialization_status !== 'PASS'
        || $cleanup_status !== 'PASS'
        || $statistics['registered'] !== $expected
        || $statistics['executed'] !== $expected
        || $statistics['passed'] !== $expected
        || $statistics['failed'] !== 0
        || $statistics['not_executed'] !== 0
        || $statistics['missing_ids'] !== []
    ) {
        echo PHP_EOL;
        echo "FAIL: EDC-01 AM-CQ-01-AM-CQ-10\n";

        return 1;
    }

    echo PHP_EOL;
    echo "PASS: EDC-01 AM-CQ-01-AM-CQ-10\n";

    return 0;
}


/*
 * Непосредственный запуск исполнителя.
 */
try {
    $exit_code =
        dilitant_catalog_run();

    dilitant_test_assert(
        in_array(
            $exit_code,
            [0, 1],
            true
        ),
        'Invalid catalog runner exit code.'
    );

    $catalog_run_completed = true;

    exit($exit_code);

} catch (Throwable $error) {
    fwrite(
        STDERR,
        "FAIL: Article Catalog execution\n"
        . $error->getMessage()
        . PHP_EOL
    );

    $catalog_run_completed = true;

    exit(1);
}