<?php
/**
 * Исполнение интеграционных
 * испытаний AM-D03.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *
 * Ответственность:
 *     - исполнение отдельного сценария;
 *     - сохранение тестового окружения;
 *     - восстановление пользователя;
 *     - восстановление глобального состояния;
 *     - восстановление GET, REQUEST и COOKIE;
 *     - восстановление WordPress Hooks;
 *     - фиксация результата сценария;
 *     - исполнение полного набора.
 *
 * Ошибка одного сценария не является
 * основанием для пропуска остальных.
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
 * Исполняет один сценарий
 * с контролем восстановления окружения.
 *
 * Возвращает:
 *
 *     null   — сценарий успешно завершён;
 *     string — описание обнаруженной ошибки.
 */
function dilitant_visibility_execute_test(
    callable $test
): ?string {
    /*
     * Сохраняем исходное состояние
     * перед исполнением сценария.
     */
    $state =
        dilitant_catalog_capture_globals();

    $previous_user_id =
        get_current_user_id();

    $previous_get = $_GET;

    $previous_request = $_REQUEST;

    $previous_cookie = $_COOKIE;

    $redirect_state =
        dilitant_visibility_capture_redirect_hooks();

    $preview_priority =
        has_filter(
            'the_preview',
            '_set_preview'
        );

    $error_message = null;


    try {
        /*
         * Подтверждаем изоляцию
         * перед началом испытания.
         */
        dilitant_visibility_verify_environment();


        /*
         * Каждый сценарий начинается
         * в контексте анонимного читателя.
         *
         * Дополнительные полномочия
         * устанавливаются самим сценарием.
         */
        dilitant_visibility_with_user(
            0,
            $test
        );


        /*
         * Проверяем, что сценарий
         * самостоятельно восстановил
         * глобальное состояние WordPress.
         */
        dilitant_catalog_verify_globals(
            $state
        );

        dilitant_test_assert_same(
            $previous_user_id,
            get_current_user_id(),
            'Scenario leaked user context.'
        );


        /*
         * Проверяем отсутствие утечки
         * параметров запроса.
         */
        dilitant_test_assert_same(
            $previous_get,
            $_GET,
            'Scenario leaked GET parameters.'
        );

        dilitant_test_assert_same(
            $previous_request,
            $_REQUEST,
            'Scenario leaked REQUEST parameters.'
        );


        /*
         * Проверяем восстановление cookie.
         */
        dilitant_test_assert_same(
            $previous_cookie,
            $_COOKIE,
            'Scenario leaked cookies.'
        );


        /*
         * Проверяем состояние
         * обработчиков перенаправления.
         */
        dilitant_test_assert_same(
            $redirect_state,
            dilitant_visibility_capture_redirect_hooks(),
            'Scenario leaked redirect hook state.'
        );


        /*
         * Проверяем состояние штатного
         * механизма отображения autosave.
         */
        dilitant_test_assert_same(
            $preview_priority,
            has_filter(
                'the_preview',
                '_set_preview'
            ),
            'Scenario leaked native preview filter.'
        );

    } catch (Throwable $error) {
        /*
         * Фиксируем ошибку сценария,
         * не прерывая полный набор.
         */
        $error_message =
            $error->getMessage();

    } finally {
        /*
         * Принудительно восстанавливаем
         * исходное окружение независимо
         * от результата сценария.
         */
        try {
            /*
             * Восстанавливаем пользователя.
             */
            wp_set_current_user(
                $previous_user_id
            );


            /*
             * Восстанавливаем запрос.
             */
            $_GET = $previous_get;

            $_REQUEST = $previous_request;

            $_COOKIE = $previous_cookie;


            /*
             * Восстанавливаем глобальные
             * переменные WordPress.
             */
            dilitant_catalog_restore_globals(
                $state
            );


            /*
             * Восстанавливаем обработчики
             * перенаправления.
             */
            dilitant_visibility_restore_redirect_hooks(
                $redirect_state
            );


            /*
             * Восстанавливаем штатный
             * autosave preview filter.
             */
            $current_priority =
                has_filter(
                    'the_preview',
                    '_set_preview'
                );

            if (
                $current_priority
                !== $preview_priority
            ) {
                if ($current_priority !== false) {
                    remove_filter(
                        'the_preview',
                        '_set_preview',
                        $current_priority
                    );
                }

                if ($preview_priority !== false) {
                    add_filter(
                        'the_preview',
                        '_set_preview',
                        $preview_priority,
                        1
                    );
                }
            }


            /*
             * Повторно подтверждаем
             * восстановление окружения.
             */
            dilitant_catalog_verify_globals(
                $state
            );

            dilitant_test_assert_same(
                $previous_user_id,
                get_current_user_id(),
                'Unable to restore original user.'
            );

            dilitant_test_assert_same(
                $preview_priority,
                has_filter(
                    'the_preview',
                    '_set_preview'
                ),
                'Unable to restore native preview filter.'
            );


            /*
             * Подтверждаем сохранение
             * изоляции тестовой БД.
             */
            dilitant_visibility_verify_environment();

        } catch (Throwable $restore_error) {
            /*
             * Ошибка восстановления
             * является самостоятельным
             * основанием для FAIL.
             */
            $error_message =
                ($error_message ?? '')
                . PHP_EOL
                . 'Environment restoration failed: '
                . $restore_error->getMessage();
        }
    }

    return $error_message;
}


/**
 * Исполняет полный зарегистрированный набор.
 *
 * Результаты индексируются
 * нормативными идентификаторами.
 *
 * Допустимые результаты:
 *
 *     PASS;
 *     FAIL.
 *
 * Повторное исполнение одного
 * идентификатора в рамках набора запрещено.
 */
function dilitant_visibility_execute_tests(
    array $tests
): array {
    $results = [];

    foreach ($tests as $id => $test) {
        /*
         * Исключаем повторную фиксацию
         * результата одного сценария.
         */
        dilitant_test_assert(
            !array_key_exists(
                $id,
                $results
            ),
            "Scenario already executed: {$id}"
        );


        /*
         * Исполняем сценарий
         * в изолированном контексте.
         */
        $error =
            dilitant_visibility_execute_test(
                $test
            );


        /*
         * Фиксируем результат.
         */
        if ($error === null) {
            $results[$id] = [
                'status' => 'PASS',
                'error' => null,
            ];

            echo "PASS: {$id}\n";

        } else {
            $results[$id] = [
                'status' => 'FAIL',
                'error' => $error,
            ];

            fwrite(
                STDERR,
                "FAIL: {$id}\n"
                . $error
                . PHP_EOL
            );
        }
    }

    return $results;
}