<?php
/**
 * Формирование контекста
 * испытательного запроса WordPress.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *
 * Ответственность:
 *     - исполнение настоящего WP_Query;
 *     - формирование главного запроса;
 *     - формирование контролируемого
 *       контекста отдельной записи;
 *     - заполнение результатов выборки;
 *     - проверка идентичности объекта;
 *     - восстановление глобального состояния.
 *
 * Контролируемый контекст не является
 * полноценным HTTP-испытанием.
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
 * Исполняет настоящий главный WP_Query.
 *
 * Глобальное состояние сохраняется
 * на всё время исполнения callback.
 */
function dilitant_visibility_with_query(
    array $args,
    callable $callback
) {
    $state =
        dilitant_catalog_capture_globals();

    try {
        /*
         * Создаём экземпляр WP_Query
         * и устанавливаем его
         * главным запросом.
         */
        $query = new WP_Query();

        $GLOBALS['wp_query'] = $query;

        $GLOBALS['wp_the_query'] = $query;


        /*
         * Подтверждаем идентичность
         * главного запроса.
         */
        dilitant_test_assert_same(
            true,

            $query->is_main_query(),

            'Visibility query is not the main query.'
        );


        /*
         * Выполняем действительный
         * запрос средствами WordPress.
         */
        $query->query($args);


        /*
         * После выполнения запроса
         * он должен сохранить статус
         * главного запроса.
         */
        dilitant_test_assert_same(
            true,

            $query->is_main_query(),

            'Visibility query lost main query identity.'
        );


        return $callback($query);

    } finally {
        /*
         * Восстанавливаем исходный
         * глобальный контекст.
         */
        dilitant_catalog_restore_globals(
            $state
        );

        dilitant_catalog_verify_globals(
            $state
        );
    }
}


/**
 * Формирует контролируемый контекст
 * отдельной записи.
 *
 * Не выполняет SQL-выборку.
 *
 * WordPress разбирает признаки запроса,
 * после чего испытательная инфраструктура
 * устанавливает существующую публикацию
 * и непустой результат выборки.
 */
function dilitant_visibility_with_post_context(
    WP_Post $post,
    bool $preview,
    callable $callback
) {
    $state =
        dilitant_catalog_capture_globals();

    try {
        /*
         * Формируем главный запрос.
         */
        $query = new WP_Query();

        $GLOBALS['wp_query'] = $query;

        $GLOBALS['wp_the_query'] = $query;


        /*
         * Параметры отдельной записи.
         */
        $args = [
            'p' =>
                (int) $post->ID,

            'post_type' =>
                $post->post_type,
        ];

        if ($preview) {
            $args['preview'] = true;
        }


        /*
         * Разбираем признаки запроса
         * штатным механизмом WordPress.
         *
         * SQL-выборка не выполняется.
         */
        $query->parse_query($args);


        /*
         * Устанавливаем непустой
         * результат выборки.
         *
         * Это обязательное предусловие
         * достоверного испытания очистки
         * отклонённого запроса.
         */
        $query->posts = [
            $post,
        ];

        $query->post_count = 1;

        $query->found_posts = 1;

        $query->max_num_pages = 1;


        /*
         * Начальное состояние цикла.
         */
        $query->current_post = -1;

        $query->in_the_loop = false;

        $query->post = $post;


        /*
         * Запрошенный объект.
         */
        $query->queried_object = $post;

        $query->queried_object_id =
            (int) $post->ID;


        /*
         * Глобальная ссылка на запись.
         */
        $GLOBALS['post'] = $post;


        /*
         * Проверяем главную роль
         * сформированного запроса.
         */
        dilitant_test_assert_same(
            true,

            $query->is_main_query(),

            'Controlled query is not the main query.'
        );


        /*
         * Проверяем singular-контекст.
         */
        dilitant_test_assert_same(
            true,

            is_singular(
                $post->post_type
            ),

            'Controlled singular context is invalid.'
        );


        /*
         * Проверяем режим Preview.
         */
        dilitant_test_assert_same(
            $preview,

            is_preview(),

            'Controlled preview context is invalid.'
        );


        /*
         * Проверяем идентичность
         * запрошенного объекта.
         */
        dilitant_test_assert_same(
            (int) $post->ID,

            (int) get_queried_object_id(),

            'Controlled queried object identity is invalid.'
        );


        /*
         * Подтверждаем наличие записи
         * в результате запроса.
         */
        dilitant_test_assert_same(
            [(int) $post->ID],

            dilitant_test_query_ids(
                $query
            ),

            'Controlled query has no target post.'
        );

        dilitant_test_assert_same(
            1,

            (int) $query->post_count,

            'Controlled query post count is invalid.'
        );


        /*
         * Проверяем ссылки
         * на текущую запись.
         */
        dilitant_test_assert_same(
            $post,

            $query->post,

            'Controlled query post is invalid.'
        );

        dilitant_test_assert_same(
            $post,

            $GLOBALS['post'],

            'Controlled global post is invalid.'
        );


        /*
         * Исполняем проверку
         * в сформированном контексте.
         */
        return $callback($query);

    } finally {
        /*
         * Восстанавливаем состояние,
         * существовавшее до испытания.
         */
        dilitant_catalog_restore_globals(
            $state
        );

        dilitant_catalog_verify_globals(
            $state
        );
    }
}