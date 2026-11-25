<?php
/**
 * Испытательная инфраструктура
 * штатного WordPress Preview.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *     AM-T11.8 — Preview nonce
 *
 * Ответственность:
 *     - формирование параметров запроса;
 *     - восстановление GET и REQUEST;
 *     - типизированный перехват wp_die();
 *     - исполнение _show_post_preview();
 *     - проверка штатного отказа;
 *     - проверка кода ответа;
 *     - проверка autosave filter;
 *     - восстановление исходных Hooks.
 *
 * Штатная реализация wp_verify_nonce()
 * не подменяется.
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
 * Типизированный перехват wp_die().
 *
 * Позволяет испытать штатный отказ
 * WordPress без завершения всего
 * CLI-исполнителя.
 */
final class DilitantVisibilityWpDie
    extends RuntimeException
{
    private int $response;


    public function __construct(
        string $message,
        int $response
    ) {
        parent::__construct(
            $message
        );

        $this->response =
            $response;
    }


    public function response(): int
    {
        return $this->response;
    }
}


/**
 * Исполняет операцию с перехватом
 * штатного завершения wp_die().
 *
 * Не заменяет саму проверку nonce.
 *
 * Перехватывается только завершение
 * PHP-процесса и сохраняется
 * нормативный код ответа.
 */
function dilitant_visibility_capture_wp_die(
    callable $callback
): ?DilitantVisibilityWpDie {
    /*
     * Обработчик штатного wp_die().
     *
     * Вместо завершения процесса
     * создаётся типизированное
     * тестовое исключение.
     */
    $handler =
        static function (
            $message,
            $title = '',
            $args = []
        ): void {
            /*
             * Нормализуем WP_Error.
             */
            if ($message instanceof WP_Error) {
                $message =
                    $message->get_error_message();
            }


            /*
             * Определяем код ответа.
             */
            $response = 500;

            if (is_int($args)) {
                $response = $args;

            } elseif (
                is_array($args)
                && isset($args['response'])
            ) {
                $response =
                    (int) $args['response'];

            } elseif (is_int($title)) {
                $response = $title;
            }


            /*
             * Передаём штатный отказ
             * испытательному исполнителю.
             */
            throw new DilitantVisibilityWpDie(
                is_scalar($message)

                    ? (string) $message

                    : 'WordPress terminated request.',

                $response
            );
        };


    /*
     * Подключаем перехватчик
     * через штатный фильтр WordPress.
     */
    $filter =
        static function () use (
            $handler
        ): callable {
            return $handler;
        };

    add_filter(
        'wp_die_handler',

        $filter,

        PHP_INT_MAX,

        0
    );


    try {
        /*
         * Исполняем проверяемую операцию.
         */
        $callback();


        /*
         * Операция завершилась
         * без вызова wp_die().
         */
        return null;

    } catch (DilitantVisibilityWpDie $error) {
        /*
         * Возвращаем зафиксированный
         * штатный отказ.
         */
        return $error;

    } finally {
        /*
         * Удаляем испытательный фильтр.
         */
        remove_filter(
            'wp_die_handler',

            $filter,

            PHP_INT_MAX
        );
    }
}


/**
 * Временно формирует параметры
 * GET и REQUEST.
 *
 * Исходное состояние обязательно
 * восстанавливается после callback.
 */
function dilitant_visibility_with_request_args(
    array $args,
    callable $callback
) {
    /*
     * Сохраняем исходное состояние.
     */
    $previous_get = $_GET;

    $previous_request = $_REQUEST;

    try {
        /*
         * Устанавливаем параметры
         * конкретного испытания.
         */
        $_GET = $args;

        $_REQUEST = $args;

        return $callback();

    } finally {
        /*
         * Восстанавливаем исходные
         * параметры запроса.
         */
        $_GET = $previous_get;

        $_REQUEST = $previous_request;


        /*
         * Подтверждаем восстановление.
         */
        dilitant_test_assert_same(
            $previous_get,

            $_GET,

            'GET request state was not restored.'
        );

        dilitant_test_assert_same(
            $previous_request,

            $_REQUEST,

            'REQUEST state was not restored.'
        );
    }
}


/**
 * Проверяет штатный механизм
 * WordPress Preview nonce.
 *
 * Использует действительную
 * реализацию _show_post_preview().
 *
 * Проверяет:
 *
 *     - наличие штатного обработчика;
 *     - решение обработчика;
 *     - код отказа;
 *     - состояние autosave filter;
 *     - восстановление исходных Hooks.
 */
function dilitant_visibility_check_native_nonce(
    array $args,
    bool $expect_denial,
    bool $expect_preview_filter
): void {
    /*
     * Проверяем наличие
     * штатного интерфейса WordPress.
     */
    dilitant_test_assert(
        function_exists(
            '_show_post_preview'
        ),

        'Native WordPress preview mechanism is missing.'
    );


    /*
     * Проверяем регистрацию
     * обработчика на стадии init.
     */
    dilitant_test_assert(
        has_action(
            'init',

            '_show_post_preview'
        ) !== false,

        'Native preview nonce handler is not registered.'
    );


    /*
     * Сохраняем исходное состояние
     * фильтра отображения autosave.
     */
    $previous_priority =
        has_filter(
            'the_preview',

            '_set_preview'
        );


    /*
     * Удаляем исходную регистрацию,
     * чтобы результат испытания
     * не зависел от ранее установленного
     * autosave filter.
     */
    if ($previous_priority !== false) {
        remove_filter(
            'the_preview',

            '_set_preview',

            $previous_priority
        );
    }


    try {
        /*
         * Формируем параметры
         * конкретного испытания.
         */
        dilitant_visibility_with_request_args(
            $args,

            static function () use (
                $expect_denial,
                $expect_preview_filter
            ): void {
                /*
                 * Исполняем действительный
                 * штатный механизм проверки.
                 */
                $error =
                    dilitant_visibility_capture_wp_die(
                        static function (): void {
                            _show_post_preview();
                        }
                    );


                /*
                 * Проверяем ожидаемый отказ.
                 */
                if ($expect_denial) {
                    dilitant_test_assert(
                        $error instanceof
                            DilitantVisibilityWpDie,

                        'Invalid preview nonce was accepted.'
                    );

                    dilitant_test_assert_same(
                        403,

                        $error->response(),

                        'Unexpected native nonce denial response.'
                    );

                } else {
                    /*
                     * Положительный сценарий
                     * не должен приводить
                     * к штатному отказу.
                     */
                    dilitant_test_assert_same(
                        null,

                        $error,

                        'Native preview unexpectedly rejected.'
                    );
                }


                /*
                 * Проверяем регистрацию
                 * механизма отображения
                 * сохранённой autosave-редакции.
                 */
                $filter_active =
                    has_filter(
                        'the_preview',

                        '_set_preview'
                    ) !== false;

                dilitant_test_assert_same(
                    $expect_preview_filter,

                    $filter_active,

                    'Unexpected native autosave filter state.'
                );
            }
        );

    } finally {
        /*
         * Определяем состояние фильтра
         * после исполнения испытания.
         */
        $current_priority =
            has_filter(
                'the_preview',

                '_set_preview'
            );


        /*
         * Удаляем регистрацию,
         * установленную испытанием.
         */
        if ($current_priority !== false) {
            remove_filter(
                'the_preview',

                '_set_preview',

                $current_priority
            );
        }


        /*
         * Восстанавливаем исходную
         * регистрацию.
         */
        if ($previous_priority !== false) {
            add_filter(
                'the_preview',

                '_set_preview',

                $previous_priority,

                1
            );
        }


        /*
         * Подтверждаем восстановление.
         */
        dilitant_test_assert_same(
            $previous_priority,

            has_filter(
                'the_preview',

                '_set_preview'
            ),

            'Native preview filter was not restored.'
        );
    }
}