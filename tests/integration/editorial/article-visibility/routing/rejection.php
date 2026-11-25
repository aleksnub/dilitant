<?php
/**
 * Проверки отказа в доступе
 * к отдельной статье.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *
 * Ответственность:
 *     - проверка исходного состояния;
 *     - подтверждение наличия
 *       отклоняемой публикации;
 *     - проверка состояния 404;
 *     - проверка очистки WP_Query;
 *     - проверка очистки глобального post;
 *     - проверка невозможности
 *       стандартного шаблонного цикла;
 *     - проверка сохранения записи в БД.
 *
 * Установка одного признака is_404()
 * не является достаточным критерием
 * успешного отказа в доступе.
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
 * Проверяет исходное состояние
 * отклоняемого запроса.
 *
 * Запрос должен содержать запись
 * до принятия решения об отказе.
 *
 * Это исключает ложный PASS
 * при проверке очистки запроса,
 * который изначально был пуст.
 *
 * @return WP_Post Отклоняемая запись.
 */
function dilitant_visibility_assert_rejection_preconditions(
    WP_Query $query
): WP_Post {
    /*
     * Проверяем запрошенный объект.
     */
    $post =
        $query->get_queried_object();

    dilitant_test_assert(
        $post instanceof WP_Post,

        'Rejection precondition: queried post is missing.'
    );

    dilitant_test_assert(
        (int) $post->ID > 0,

        'Rejection precondition: invalid post identity.'
    );


    /*
     * Проверяем наличие публикаций
     * в результате WP_Query.
     */
    dilitant_test_assert(
        is_array($query->posts)
        && $query->posts !== [],

        'Rejection precondition: query result is empty.'
    );


    /*
     * Счётчик записей должен быть
     * положительным до отказа.
     */
    dilitant_test_assert(
        (int) $query->post_count > 0,

        'Rejection precondition: post count is zero.'
    );


    /*
     * Отклоняемая запись должна
     * действительно присутствовать
     * в результате выборки.
     */
    dilitant_test_assert(
        in_array(
            (int) $post->ID,

            dilitant_test_query_ids(
                $query
            ),

            true
        ),

        'Rejection precondition: target post is not in query result.'
    );


    /*
     * Проверяем текущую запись WP_Query.
     */
    dilitant_test_assert(
        $query->post instanceof WP_Post,

        'Rejection precondition: current query post is missing.'
    );


    /*
     * Текущая и запрошенная запись
     * должны иметь одинаковый ID.
     */
    dilitant_test_assert_same(
        (int) $post->ID,

        (int) $query->post->ID,

        'Rejection precondition: current query post differs.'
    );

    return $post;
}


/**
 * Проверяет полную очистку
 * отклонённого результата WP_Query.
 *
 * После отказа запись не должна
 * оставаться доступной через:
 *
 *     - массив результатов;
 *     - текущую запись;
 *     - запрошенный объект;
 *     - идентификатор объекта;
 *     - глобальный post;
 *     - стандартный цикл WordPress.
 *
 * При этом физическая запись
 * должна сохраняться в БД.
 */
function dilitant_visibility_assert_rejected_query_cleared(
    WP_Query $query,
    int $rejected_post_id
): void {
    /*
     * 1. Состояние запроса.
     */
    dilitant_test_assert_same(
        true,

        $query->is_404(),

        'Rejected query is not a 404.'
    );

    dilitant_test_assert_same(
        false,

        $query->is_singular(),

        'Rejected query retained singular identity.'
    );


    /*
     * 2. Результаты выборки.
     */
    dilitant_test_assert_same(
        [],

        $query->posts,

        'Rejected query retained post records.'
    );


    /*
     * 3. Счётчики результатов.
     */
    dilitant_test_assert_same(
        0,

        (int) $query->post_count,

        'Rejected query retained post count.'
    );

    dilitant_test_assert_same(
        0,

        (int) $query->found_posts,

        'Rejected query retained found posts count.'
    );

    dilitant_test_assert_same(
        0,

        (int) $query->max_num_pages,

        'Rejected query retained pagination state.'
    );


    /*
     * 4. Состояние цикла.
     */
    dilitant_test_assert_same(
        -1,

        (int) $query->current_post,

        'Rejected query retained loop position.'
    );

    dilitant_test_assert_same(
        false,

        $query->in_the_loop,

        'Rejected query remained inside the loop.'
    );


    /*
     * 5. Текущая запись запроса.
     */
    dilitant_test_assert_same(
        null,

        $query->post,

        'Rejected query retained current post.'
    );


    /*
     * 6. Запрошенный объект.
     */
    dilitant_test_assert_same(
        null,

        $query->queried_object,

        'Rejected query retained queried object.'
    );

    dilitant_test_assert_same(
        0,

        (int) $query->queried_object_id,

        'Rejected query retained queried object ID.'
    );


    /*
     * 7. Глобальная запись WordPress.
     */
    dilitant_test_assert_same(
        null,

        $GLOBALS['post'] ?? null,

        'Rejected post remains globally accessible.'
    );


    /*
     * 8. Проверяем штатные интерфейсы
     * разрешения текущего объекта.
     *
     * Недостаточно проверить только
     * значения полей WP_Query.
     */
    dilitant_test_assert_same(
        null,

        get_queried_object(),

        'Rejected queried object is still resolvable.'
    );

    dilitant_test_assert_same(
        0,

        (int) get_queried_object_id(),

        'Rejected queried object ID is still resolvable.'
    );

    dilitant_test_assert_same(
        null,

        get_post(),

        'Rejected post is still resolvable through get_post().'
    );


    /*
     * 9. Проверяем невозможность
     * стандартного шаблонного цикла.
     */
    dilitant_test_assert_same(
        false,

        have_posts(),

        'Rejected query still exposes posts to template loop.'
    );


    /*
     * 10. Проверяем, что вызов
     * have_posts() не восстановил
     * отклонённый объект.
     */
    dilitant_test_assert_same(
        null,

        $query->post,

        'Template loop restored rejected post.'
    );

    dilitant_test_assert_same(
        null,

        $GLOBALS['post'] ?? null,

        'Template loop restored global rejected post.'
    );

    dilitant_test_assert_same(
        [],

        $query->posts,

        'Template loop restored rejected query records.'
    );


    /*
     * 11. Проверяем сохранение записи.
     *
     * Отказ в отображении
     * не должен приводить
     * к физическому удалению статьи.
     */
    $stored_post = get_post(
        $rejected_post_id
    );

    dilitant_test_assert(
        $stored_post instanceof WP_Post,

        'Access denial removed the stored post.'
    );

    dilitant_test_assert_same(
        $rejected_post_id,

        (int) $stored_post->ID,

        'Access denial changed stored post identity.'
    );


    /*
     * 12. Явное обращение к записи
     * по ID не должно восстанавливать
     * глобальный объект запроса.
     */
    dilitant_test_assert_same(
        null,

        $GLOBALS['post'] ?? null,

        'Explicit post lookup restored rejected global post.'
    );

    dilitant_test_assert_same(
        null,

        get_queried_object(),

        'Explicit post lookup restored queried object.'
    );
}