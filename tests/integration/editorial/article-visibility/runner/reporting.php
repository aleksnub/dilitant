<?php
/**
 * Отчётность интеграционных
 * испытаний AM-D03.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *
 * Ответственность:
 *     - проверка идентификаторов результатов;
 *     - формирование статистики;
 *     - определение отсутствующих сценариев;
 *     - формирование итогового отчёта;
 *     - проверка критериев приёмки.
 *
 * Компонент не создаёт Fixtures,
 * не исполняет сценарии
 * и не изменяет состояние WordPress.
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
 * Формирует статистику исполнения
 * нормативного набора AM-D03.
 *
 * Отсутствующий результат не считается
 * успешно выполненным сценарием.
 */
function dilitant_visibility_statistics(
    array $tests,
    array $results
): array {
    $expected =
        dilitant_visibility_expected_ids();

    $passed = 0;

    $failed = 0;


    /*
     * Проверяем каждый полученный
     * результат испытания.
     */
    foreach ($results as $id => $result) {
        /*
         * Результат должен принадлежать
         * зарегистрированному сценарию.
         */
        dilitant_test_assert(
            array_key_exists(
                $id,
                $tests
            ),
            "Result belongs to unknown scenario: {$id}"
        );


        /*
         * Зарегистрированный сценарий
         * должен входить в нормативный набор.
         */
        dilitant_test_assert(
            in_array(
                $id,
                $expected,
                true
            ),
            "Result has unexpected scenario ID: {$id}"
        );


        /*
         * Допустимы только два результата:
         *
         *     PASS;
         *     FAIL.
         */
        $status =
            $result['status'] ?? null;

        if ($status === 'PASS') {
            $passed++;

        } elseif ($status === 'FAIL') {
            $failed++;

        } else {
            throw new RuntimeException(
                "Invalid scenario status: {$id}"
            );
        }
    }


    /*
     * Определяем нормативные сценарии,
     * для которых результат отсутствует.
     */
    $missing = array_values(
        array_diff(
            $expected,
            array_keys($results)
        )
    );


    /*
     * Формируем машинную статистику.
     */
    return [
        'total' =>
            DILITANT_VISIBILITY_EXPECTED_TOTAL,

        'registered' =>
            count($tests),

        'executed' =>
            count($results),

        'passed' =>
            $passed,

        'failed' =>
            $failed,

        'not_executed' =>
            count($missing),

        'missing_ids' =>
            $missing,
    ];
}


/**
 * Выводит итоговую отчётность.
 *
 * Формат отчёта сохраняется совместимым
 * с существующим исполнителем
 * регрессионных испытаний.
 */
function dilitant_visibility_report(
    array $statistics,
    string $initialization_status,
    string $fixture_cleanup_status,
    string $user_cleanup_status
): void {
    echo PHP_EOL;

    echo "=== RESULT ===\n";


    /*
     * Статистика нормативного набора.
     */
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


    /*
     * Результаты жизненного цикла.
     */
    echo "Fixture initialization: "
        . $initialization_status
        . PHP_EOL;

    echo "Fixture cleanup: "
        . $fixture_cleanup_status
        . PHP_EOL;

    echo "User cleanup: "
        . $user_cleanup_status
        . PHP_EOL;


    /*
     * При неполном исполнении
     * явно перечисляем отсутствующие
     * нормативные идентификаторы.
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
 * Определяет выполнение всех
 * нормативных критериев приёмки AM-D03.
 *
 * Успешная приёмка требует:
 *
 *     - отсутствия ошибки исполнителя;
 *     - успешной инициализации;
 *     - успешной очистки Fixtures;
 *     - успешной очистки пользователей;
 *     - регистрации всех 20 сценариев;
 *     - исполнения всех 20 сценариев;
 *     - положительного результата
 *       каждого сценария;
 *     - отсутствия пропущенных испытаний.
 *
 * @internal
 */
function dilitant_visibility_is_accepted(
    array $statistics,
    string $initialization_status,
    string $fixture_cleanup_status,
    string $user_cleanup_status,
    bool $execution_error
): bool {
    $expected =
        DILITANT_VISIBILITY_EXPECTED_TOTAL;


    /*
     * Любая ошибка жизненного цикла
     * исключает успешную приёмку.
     */
    if (
        $execution_error
        || $initialization_status !== 'PASS'
        || $fixture_cleanup_status !== 'PASS'
        || $user_cleanup_status !== 'PASS'
    ) {
        return false;
    }


    /*
     * Все нормативные сценарии
     * должны быть зарегистрированы.
     */
    if (
        $statistics['registered']
        !== $expected
    ) {
        return false;
    }


    /*
     * Все зарегистрированные сценарии
     * должны быть исполнены.
     */
    if (
        $statistics['executed']
        !== $expected
    ) {
        return false;
    }


    /*
     * Все нормативные сценарии
     * должны завершиться успешно.
     */
    if (
        $statistics['passed'] !== $expected
        || $statistics['failed'] !== 0
    ) {
        return false;
    }


    /*
     * Отсутствующие или неисполненные
     * сценарии исключают приёмку.
     */
    if (
        $statistics['not_executed'] !== 0
        || $statistics['missing_ids'] !== []
    ) {
        return false;
    }

    return true;
}