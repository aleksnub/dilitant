<?php
/**
 * Испытание производственного
 * обработчика доступности статей.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *
 * Ответственность:
 *     - проверка регистрации обработчика;
 *     - сохранение исходного состояния;
 *     - проверка предусловий отказа;
 *     - вызов производственного обработчика;
 *     - проверка результата;
 *     - проверка очистки запроса;
 *     - проверка сохранения записи;
 *     - восстановление redirect Hooks.
 *
 * Испытание проверяет состояние
 * WordPress в CLI, а не фактический
 * сетевой HTTP-ответ.
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
 * Проверяет решение производственного
 * обработчика доступности.
 *
 * При ожидаемом отказе:
 *
 *     - состояние 404;
 *     - очистка WP_Query;
 *     - очистка глобального post;
 *     - невозможность шаблонного цикла;
 *     - сохранение данных записи;
 *     - отключение перенаправлений.
 *
 * При ожидаемом разрешении:
 *
 *     - сохранение состояния запроса;
 *     - сохранение глобального post;
 *     - сохранение redirect Hooks.
 */
function dilitant_visibility_assert_handler_decision(
    WP_Query $query,
    bool $expected_rejection
): void {
    /*
     * Производственный обработчик
     * должен работать с тем запросом,
     * который передан испытанию.
     */
    dilitant_test_assert_same(
        $query,

        $GLOBALS['wp_query'],

        'Visibility handler target is not global query.'
    );


    /*
     * Исходный запрос не должен
     * уже находиться в состоянии 404.
     */
    dilitant_test_assert_same(
        false,

        $query->is_404(),

        'Visibility handler received an existing 404.'
    );


    /*
     * Проверяем регистрацию
     * испытуемого обработчика.
     */
    dilitant_test_assert_same(
        1,

        has_action(
            'template_redirect',

            'dilitant_land_enforce_blog_article_visibility'
        ),

        'Visibility handler is not registered.'
    );


    /*
     * Сохраняем исходное состояние
     * главного запроса.
     */
    $before =
        dilitant_catalog_capture_query_state(
            $query
        );


    /*
     * Сохраняем redirect Hooks.
     */
    $redirect_state =
        dilitant_visibility_capture_redirect_hooks();


    /*
     * Сохраняем глобальную запись
     * отдельно от состояния WP_Query.
     */
    $global_post_exists =
        array_key_exists(
            'post',

            $GLOBALS
        );

    $global_post_before =
        $GLOBALS['post'] ?? null;


    /*
     * Данные отклоняемой записи.
     */
    $rejected_post_id = 0;

    $stored_post_before = null;


    try {
        if ($expected_rejection) {
            /*
             * Подтверждаем наличие записи
             * до принятия решения.
             */
            $target =
                dilitant_visibility_assert_rejection_preconditions(
                    $query
                );

            $rejected_post_id =
                (int) $target->ID;


            /*
             * Сохраняем данные записи
             * для проверки отсутствия
             * побочных изменений.
             */
            $stored_post_before =
                dilitant_visibility_get_post(
                    $rejected_post_id
                )->to_array();


            /*
             * Настоящий WP_Query в CLI
             * не всегда устанавливает
             * глобальный post.
             *
             * Для испытания очистки
             * устанавливаем его явно.
             */
            $GLOBALS['post'] = $target;

            dilitant_test_assert_same(
                $target,

                get_post(),

                'Rejection precondition: global post is not resolvable.'
            );
        }


        /*
         * Вызываем непосредственно
         * производственный обработчик.
         *
         * Весь template_redirect
         * не запускается, поскольку
         * сторонние обработчики могут
         * завершить PHP-процесс.
         */
        dilitant_land_enforce_blog_article_visibility();


        /*
         * Проверяем основное решение.
         */
        dilitant_test_assert_same(
            $expected_rejection,

            $query->is_404(),

            'Unexpected visibility handler decision.'
        );


        if ($expected_rejection) {
            /*
             * Проверяем полную очистку
             * отклонённого запроса.
             */
            dilitant_visibility_assert_rejected_query_cleared(
                $query,

                $rejected_post_id
            );


            /*
             * Подтверждаем отсутствие
             * побочного изменения данных.
             */
            dilitant_test_assert_same(
                $stored_post_before,

                dilitant_visibility_get_post(
                    $rejected_post_id
                )->to_array(),

                'Access denial modified stored post.'
            );


            /*
             * Каноническое перенаправление
             * должно быть отключено.
             */
            dilitant_test_assert_same(
                false,

                has_action(
                    'template_redirect',

                    'redirect_canonical'
                ),

                'Canonical redirect remained after rejection.'
            );


            /*
             * Перенаправление старого slug
             * также должно быть отключено.
             */
            dilitant_test_assert_same(
                false,

                has_action(
                    'template_redirect',

                    'wp_old_slug_redirect'
                ),

                'Old slug redirect remained after rejection.'
            );

        } else {
            /*
             * Разрешённый запрос
             * должен сохранить состояние.
             */
            $after =
                dilitant_catalog_capture_query_state(
                    $query
                );

            dilitant_catalog_assert_unchanged(
                $before,

                $after
            );


            /*
             * Проверяем сохранение
             * глобальной переменной post.
             */
            dilitant_test_assert_same(
                $global_post_exists,

                array_key_exists(
                    'post',

                    $GLOBALS
                ),

                'Allowed request changed global post presence.'
            );

            dilitant_test_assert_same(
                $global_post_before,

                $GLOBALS['post'] ?? null,

                'Allowed request changed global post.'
            );


            /*
             * Разрешённый запрос
             * не должен отключать
             * перенаправления.
             */
            dilitant_test_assert_same(
                $redirect_state,

                dilitant_visibility_capture_redirect_hooks(),

                'Allowed request changed redirect hooks.'
            );
        }

    } finally {
        /*
         * Восстанавливаем исходные
         * обработчики перенаправления
         * независимо от результата.
         */
        dilitant_visibility_restore_redirect_hooks(
            $redirect_state
        );


        /*
         * Отклонённую запись
         * внутри этого контекста
         * не восстанавливаем.
         *
         * Исходный глобальный контекст
         * будет восстановлен внешним
         * with_query() или
         * with_post_context().
         */
    }
}