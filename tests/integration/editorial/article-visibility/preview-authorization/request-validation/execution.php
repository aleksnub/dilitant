<?php
/**
 * Исполнение отдельной проверки
 * параметров Preview.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *     AM-T11.8 — Preview nonce
 *
 * Ответственность:
 *     - установка пользователя;
 *     - проверка объектных полномочий;
 *     - установка параметров запроса;
 *     - формирование контекста Preview;
 *     - проверка решения Article Model;
 *     - проверка решения маршрутизации;
 *     - диагностическая привязка ошибки
 *       к идентификатору сценария.
 *
 * Штатный _show_post_preview()
 * не вызывается.
 *
 * Производственная авторизация
 * проверяется самостоятельно.
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
 * Исполняет одну внутреннюю проверку
 * производственной авторизации Preview.
 *
 * Ожидаемая публичная доступность
 * задаётся независимо от ожидаемого
 * разрешения Preview.
 *
 * Публичная статья не должна
 * автоматически разрешать Preview.
 */
function dilitant_visibility_execute_preview_validation(
    string $scenario_id,
    WP_Post $post,
    int $user_id,
    array $request_args,
    bool $expected_edit_permission,
    bool $expected_preview,
    bool $expected_public
): void {
    try {
        /*
         * Устанавливаем пользовательский
         * контекст конкретного сценария.
         */
        dilitant_visibility_with_user(
            $user_id,

            static function () use (
                $post,
                $request_args,
                $expected_edit_permission,
                $expected_preview,
                $expected_public
            ): void {
                /*
                 * Проверяем фактические
                 * объектные полномочия.
                 */
                dilitant_test_assert_same(
                    $expected_edit_permission,

                    current_user_can(
                        'edit_post',
                        $post->ID
                    ),

                    'Unexpected preview fixture capability.'
                );


                /*
                 * Формируем входные
                 * параметры Preview.
                 *
                 * Не выполняем предварительную
                 * проверку средствами ядра.
                 */
                dilitant_visibility_with_request_args(
                    array_merge(
                        [
                            'preview' => 'true',
                        ],

                        $request_args
                    ),

                    static function () use (
                        $post,
                        $expected_preview,
                        $expected_public
                    ): void {
                        /*
                         * Формируем действительный
                         * объект запроса независимо
                         * от штатного SQL-отбора.
                         */
                        dilitant_visibility_with_post_context(
                            $post,
                            true,

                            static function (
                                WP_Query $query
                            ) use (
                                $post,
                                $expected_preview,
                                $expected_public
                            ): void {
                                /*
                                 * Проверяем независимые
                                 * решения Article Model.
                                 *
                                 * В режиме Preview
                                 * разрешение отображения
                                 * соответствует разрешению
                                 * предварительного просмотра.
                                 */
                                dilitant_visibility_assert_preview(
                                    $post,

                                    $expected_preview,

                                    $expected_preview,

                                    $expected_public
                                );


                                /*
                                 * Проверяем применение
                                 * решения на границе
                                 * маршрутизации.
                                 *
                                 * При отказе общая
                                 * инфраструктура проверяет
                                 * очистку WP_Query.
                                 */
                                dilitant_visibility_assert_handler_decision(
                                    $query,

                                    !$expected_preview
                                );
                            }
                        );
                    }
                );
            }
        );

    } catch (Throwable $error) {
        /*
         * Дополняем диагностическое
         * сообщение идентификатором
         * внутреннего сценария.
         */
        throw new RuntimeException(
            $scenario_id
            . ': '
            . $error->getMessage(),

            0,

            $error
        );
    }
}