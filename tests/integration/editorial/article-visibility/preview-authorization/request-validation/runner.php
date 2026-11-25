<?php
/**
 * Исполнитель расширенной проверки
 * параметров предварительного просмотра.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *     AM-T11.8 — Preview nonce
 *
 * Ответственность:
 *     - получение нормативных сценариев;
 *     - проверка полноты регистрации;
 *     - исполнение всех проверок;
 *     - фиксация отдельных результатов;
 *     - формирование итоговой статистики;
 *     - передача отказа в AM-T11.8.
 *
 * Ошибка отдельного сценария
 * не отменяет исполнение остальных.
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
 * Исполняет все обязательные
 * проверки параметров Preview.
 *
 * В основном исполнителе
 * данный набор учитывается
 * как один сценарий AM-T11.8.
 */
function dilitant_visibility_run_preview_request_validation(
    DilitantArticleFixtures $fixtures,
    DilitantVisibilityUsers $users
): void {
    /*
     * Формируем нормативный набор.
     */
    $cases =
        dilitant_visibility_build_preview_validation_cases(
            $fixtures,
            $users
        );


    /*
     * Проверяем количество сценариев.
     */
    dilitant_test_assert_same(
        DILITANT_PREVIEW_VALIDATION_TOTAL,

        count($cases),

        'Unexpected preview validation case count.'
    );


    /*
     * Проверяем точное соответствие
     * нормативному перечню.
     */
    dilitant_test_assert_same(
        dilitant_visibility_expected_preview_validation_ids(),

        array_keys($cases),

        'Unexpected preview validation case manifest.'
    );


    /*
     * Исходная статистика.
     */
    $passed = 0;

    $failed = 0;

    $errors = [];


    /*
     * Исполняем весь нормативный набор.
     */
    foreach (
        $cases as $scenario_id => $case
    ) {
        try {
            dilitant_visibility_execute_preview_validation(
                $scenario_id,

                $case['post'],

                $case['user_id'],

                $case['request_args'],

                $case['expected_edit_permission'],

                $case['expected_preview'],

                $case['expected_public']
            );


            /*
             * Положительный результат.
             */
            $passed++;

            echo 'PASS: AM-T11.8/'
                . $scenario_id
                . ' '
                . $case['description']
                . PHP_EOL;

        } catch (Throwable $error) {
            /*
             * Отрицательный результат.
             *
             * Продолжаем исполнение
             * остальных сценариев.
             */
            $failed++;

            $errors[] =
                $error->getMessage();

            fwrite(
                STDERR,

                'FAIL: AM-T11.8/'
                . $scenario_id
                . ' '
                . $case['description']
                . PHP_EOL
                . $error->getMessage()
                . PHP_EOL
            );
        }
    }


    /*
     * Внутренняя итоговая статистика.
     */
    echo 'Preview request validation: '
        . $passed
        . '/'
        . DILITANT_PREVIEW_VALIDATION_TOTAL
        . ' PASS'
        . PHP_EOL;


    /*
     * Любой отказ исключает
     * положительный результат AM-T11.8.
     */
    if (
        $passed !== DILITANT_PREVIEW_VALIDATION_TOTAL
        || $failed !== 0
        || $errors !== []
    ) {
        throw new RuntimeException(
            'Preview request validation failed.'
            . PHP_EOL
            . implode(PHP_EOL, $errors)
        );
    }
}