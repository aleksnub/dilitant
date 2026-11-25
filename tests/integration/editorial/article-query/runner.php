<?php
/**
 * Исполнитель интеграционных испытаний AM-D02.
 *
 * Ответственность:
 *     - проверка испытуемых интерфейсов;
 *     - создание тестовых данных;
 *     - регистрация сценариев;
 *     - проверка уникальности идентификаторов;
 *     - проверка нормативной полноты;
 *     - исполнение всех сценариев;
 *     - очистка тестовой среды;
 *     - формирование итогового отчёта;
 *     - определение кода завершения.
 */

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    || !defined('DILITANT_TEST_DB_NAME')
    || !defined('DB_NAME')
    || DB_NAME !== DILITANT_TEST_DB_NAME
) {
    exit(1);
}


/**
 * Нормативное количество сценариев.
 */
const DILITANT_QUERY_TEST_EXPECTED_TOTAL = 52;


/**
 * Возвращает обязательные идентификаторы
 * всех сценариев текущей редакции.
 *
 * Описание после идентификатора
 * не является частью идентичности сценария.
 */
function dilitant_query_test_expected_ids(): array
{
    $ids = [];


    /*
     * AM-T06: 3 сценария.
     */
    for ($i = 1; $i <= 3; $i++) {
        $ids[] = "AM-T06.{$i}";
    }


    /*
     * AM-T07: 6 сценариев.
     */
    for ($i = 1; $i <= 6; $i++) {
        $ids[] = "AM-T07.{$i}";
    }


    /*
     * AM-T08: 10 сценариев.
     */
    for ($i = 1; $i <= 10; $i++) {
        $ids[] = "AM-T08.{$i}";
    }


    /*
     * AM-QN: 20 сценариев.
     */
    for ($i = 1; $i <= 20; $i++) {
        $ids[] = sprintf(
            'AM-QN-%02d',
            $i
        );
    }


    /*
     * AM-QI: 13 сценариев.
     */
    for ($i = 1; $i <= 13; $i++) {
        $ids[] = sprintf(
            'AM-QI-%02d',
            $i
        );
    }

    return $ids;
}


/**
 * Извлекает нормативный идентификатор
 * из имени зарегистрированного сценария.
 */
function dilitant_query_test_extract_id(
    string $name
): string {
    $pattern =
        '/\A'
        . '(AM-T(?:06|07|08)\.[1-9][0-9]*'
        . '|AM-QN-[0-9]{2}'
        . '|AM-QI-[0-9]{2})'
        . '(?:\s+.*)?'
        . '\z/';

    if (!preg_match(
        $pattern,
        $name,
        $matches
    )) {
        throw new RuntimeException(
            "Invalid test identifier: {$name}"
        );
    }

    return $matches[1];
}


/**
 * Проверяет наличие обязательных
 * испытуемых интерфейсов и компонентов.
 */
function dilitant_query_test_verify_interfaces(): void
{
    $required_functions = [

        /*
         * Интерфейсы модели.
         */
        'dilitant_land_get_blog_category_id',

        'dilitant_land_get_blog_category_ids',

        'dilitant_land_get_blog_articles',

        'dilitant_land_is_blog_article',

        'dilitant_land_normalize_blog_query_args',

        /*
         * Компоненты исполнения.
         */
        'dilitant_query_test_execute',

        'dilitant_query_test_assert_invariants',

        'dilitant_query_test_assert_closed_failure',

        /*
         * Фабрики сценариев.
         */
        'dilitant_query_test_cases_t06',

        'dilitant_query_test_cases_t07',

        'dilitant_query_test_cases_t08',

        'dilitant_query_test_cases_normalization',

        'dilitant_query_test_cases_integration',
    ];

    foreach ($required_functions as $function) {
        if (!function_exists($function)) {
            throw new RuntimeException(
                "Required function is missing: {$function}"
            );
        }
    }


    /*
     * Проверяем публичный интерфейс
     * тестового набора.
     */
    if (!class_exists(
        DilitantArticleFixtures::class
    )) {
        throw new RuntimeException(
            'Article fixture class is missing.'
        );
    }

    $required_methods = [
        'create',
        'create_query_fixtures',
        'verify_query_fixtures',
        'expected_category_ids',
        'expected_published_post_ids',
        'expected_child_post_ids',
        'excluded_post_ids',
        'archive_post_type',
        'fixture_password',
        'run_id',
        'cleanup',
    ];

    foreach ($required_methods as $method) {
        if (!method_exists(
            DilitantArticleFixtures::class,
            $method
        )) {
            throw new RuntimeException(
                "Required fixture method is missing: {$method}"
            );
        }
    }

    echo "PASS: required test interfaces\n";
}


/**
 * Регистрирует сценарии отдельной группы.
 *
 * Объединение групп через array_merge()
 * намеренно не используется:
 * оно могло бы незаметно перезаписать
 * одинаковые строковые ключи.
 */
function dilitant_query_test_register_group(
    array &$registered,
    array $group,
    string $group_name,
    int $expected_count
): void {
    dilitant_test_assert_same(
        $expected_count,
        count($group),
        "Unexpected scenario count in {$group_name}."
    );

    foreach ($group as $name => $test) {
        dilitant_test_assert(
            is_string($name),
            "Invalid test name in {$group_name}."
        );

        dilitant_test_assert(
            is_callable($test),
            "Test is not callable: {$name}"
        );

        $id = dilitant_query_test_extract_id(
            $name
        );


        /*
         * Проверяем принадлежность группе.
         */
        dilitant_test_assert(
            str_starts_with($id, $group_name),
            "Unexpected test group for {$id}."
        );


        /*
         * Запрещаем дублирование
         * нормативного идентификатора.
         */
        if (array_key_exists(
            $id,
            $registered
        )) {
            throw new RuntimeException(
                "Duplicate test identifier: {$id}"
            );
        }

        $registered[$id] = [
            'id' => $id,
            'name' => $name,
            'test' => $test,
        ];
    }
}


/**
 * Проверяет точную нормативную полноту
 * зарегистрированного набора.
 */
function dilitant_query_test_verify_registration(
    array $registered
): void {
    $expected_ids =
        dilitant_query_test_expected_ids();

    dilitant_test_assert_same(
        DILITANT_QUERY_TEST_EXPECTED_TOTAL,
        count($expected_ids),
        'Invalid normative test manifest.'
    );

    dilitant_test_assert_same(
        DILITANT_QUERY_TEST_EXPECTED_TOTAL,
        count($registered),
        'Unexpected number of registered tests.'
    );

    $actual_ids = array_keys(
        $registered
    );

    sort(
        $expected_ids,
        SORT_STRING
    );

    sort(
        $actual_ids,
        SORT_STRING
    );

    dilitant_test_assert_same(
        $expected_ids,
        $actual_ids,
        'Registered test set differs from normative manifest.'
    );
}


/**
 * Формирует полный набор испытаний.
 */
function dilitant_query_test_register_all(
    DilitantArticleFixtures $fixtures
): array {
    $registered = [];


    dilitant_query_test_register_group(
        $registered,
        dilitant_query_test_cases_t06(
            $fixtures
        ),
        'AM-T06.',
        3
    );


    dilitant_query_test_register_group(
        $registered,
        dilitant_query_test_cases_t07(
            $fixtures
        ),
        'AM-T07.',
        6
    );


    dilitant_query_test_register_group(
        $registered,
        dilitant_query_test_cases_t08(
            $fixtures
        ),
        'AM-T08.',
        10
    );


    dilitant_query_test_register_group(
        $registered,
        dilitant_query_test_cases_normalization(),
        'AM-QN-',
        20
    );


    dilitant_query_test_register_group(
        $registered,
        dilitant_query_test_cases_integration(
            $fixtures
        ),
        'AM-QI-',
        13
    );


    /*
     * Проверяем точное множество сценариев.
     */
    dilitant_query_test_verify_registration(
        $registered
    );

    return $registered;
}


/**
 * Последовательно выполняет
 * зарегистрированные испытания.
 *
 * Результаты сохраняются по нормативным ID.
 *
 * Ошибка одного сценария не препятствует
 * выполнению остальных.
 */
function dilitant_query_test_execute_all(
    array $registered
): array {
    $results = [];

    foreach ($registered as $id => $entry) {
        $name = $entry['name'];
        $test = $entry['test'];

        try {
            /*
             * Непосредственный вызов сценария.
             */
            $test();


            /*
             * PASS регистрируется только
             * после штатного возврата сценария.
             */
            $results[$id] = [
                'status' => 'PASS',
                'error' => null,
            ];

            echo "PASS: {$name}\n";

        } catch (Throwable $error) {
            $results[$id] = [
                'status' => 'FAIL',
                'error' => $error->getMessage(),
            ];

            fwrite(
                STDERR,
                "FAIL: {$name}\n"
                . $error->getMessage()
                . PHP_EOL
            );
        }
    }

    return $results;
}


/**
 * Формирует итоговую статистику.
 */
function dilitant_query_test_statistics(
    array $registered,
    array $results
): array {
    $expected_ids =
        dilitant_query_test_expected_ids();

    $passed = 0;
    $failed = 0;

    foreach ($results as $id => $result) {
        dilitant_test_assert(
            array_key_exists(
                $id,
                $registered
            ),
            "Result belongs to unregistered test: {$id}"
        );

        $status = $result['status'] ?? null;

        if ($status === 'PASS') {
            $passed++;

        } elseif ($status === 'FAIL') {
            $failed++;

        } else {
            throw new RuntimeException(
                "Unexpected test result status: {$id}"
            );
        }
    }

    $missing_ids = array_values(
        array_diff(
            $expected_ids,
            array_keys($results)
        )
    );

    return [
        'total' =>
            DILITANT_QUERY_TEST_EXPECTED_TOTAL,

        'registered' =>
            count($registered),

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
function dilitant_query_test_report(
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


    /*
     * Явно перечисляем пропущенные сценарии.
     */
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
 * Главный исполнитель AM-D02.
 *
 * Возвращает:
 *     0 — все обязательные условия выполнены;
 *     1 — обнаружена ошибка.
 *
 * Не завершает PHP-процесс непосредственно.
 * Код завершения передаётся точке входа.
 */
function dilitant_query_test_run(): int
{
    $fixtures = null;

    $registered = [];
    $results = [];

    $initialization_status = 'NOT EXECUTED';

    $cleanup_status = 'NOT EXECUTED';

    $execution_error = false;


    echo PHP_EOL;
    echo "=== EDC-01 ARTICLE QUERY POLICY ===\n";
    echo PHP_EOL;


    try {
        /*
         * 1. Проверяем необходимые интерфейсы.
         */
        dilitant_query_test_verify_interfaces();


        /*
         * 2. Создаём тестовый экземпляр.
         */
        $fixtures =
            new DilitantArticleFixtures();


        /*
         * 3. Создаём базовые данные.
         */
        $fixtures->create();

        echo "PASS: base fixture creation\n";


        /*
         * 4. Создаём расширенный набор.
         */
        $fixtures->create_query_fixtures();

        echo "PASS: query fixture creation\n";


        /*
         * 5. Проверяем фактическое состояние.
         */
        $fixtures->verify_query_fixtures();

        echo "PASS: query fixture verification\n";

        $initialization_status = 'PASS';


        /*
         * 6. Регистрируем обязательные сценарии.
         */
        $registered =
            dilitant_query_test_register_all(
                $fixtures
            );

        echo "PASS: test registration\n";

        echo "Registered scenarios: "
            . count($registered)
            . PHP_EOL;

        echo PHP_EOL;


        /*
         * 7. Выполняем испытания.
         */
        $results =
            dilitant_query_test_execute_all(
                $registered
            );


    } catch (Throwable $error) {
        $execution_error = true;

        if ($initialization_status !== 'PASS') {
            $initialization_status = 'FAIL';
        }

        fwrite(
            STDERR,
            "FAIL: test initialization or execution\n"
            . $error->getMessage()
            . PHP_EOL
        );


    } finally {
        /*
         * 8. Очистка выполняется независимо
         * от результата инициализации,
         * регистрации и испытаний.
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
                    "FAIL: fixture cleanup\n"
                    . $error->getMessage()
                    . PHP_EOL
                );
            }
        }
    }


    /*
     * 9. Формируем итоговую статистику.
     */
    $statistics =
        dilitant_query_test_statistics(
            $registered,
            $results
        );

    dilitant_query_test_report(
        $statistics,
        $initialization_status,
        $cleanup_status
    );


    /*
     * 10. Проверяем обязательные
     * критерии успешного завершения.
     */
    $expected =
        DILITANT_QUERY_TEST_EXPECTED_TOTAL;

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
        echo "FAIL: EDC-01 AM-D02 QUERY POLICY\n";

        return 1;
    }


    /*
     * Единственная штатная точка выдачи
     * положительного итогового результата.
     */
    echo PHP_EOL;
    echo "PASS: EDC-01 AM-D02 QUERY POLICY\n";

    return 0;
}