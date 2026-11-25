<?php
/**
 * Координатор нормализации аргументов
 * публичной выборки статей.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D02 — Защита инвариантов WP_Query
 *
 * Ответственность:
 *     - подключение компонентов нормализации;
 *     - проверка входного массива;
 *     - последовательная нормализация аргументов;
 *     - объединение проверенных результатов;
 *     - закрытый отказ при некорректных данных.
 *
 * Поддерживаемые параметры:
 *     - posts_per_page;
 *     - paged;
 *     - orderby;
 *     - order;
 *     - s;
 *     - post__in;
 *     - post__not_in.
 *
 * Параметр category_id обрабатывается
 * компонентом categories.php.
 *
 * Неизвестные параметры игнорируются.
 *
 * Обязательные инварианты выборки
 * устанавливаются компонентами
 * query-policy.php и query.php.
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/*
 * Нормализация пагинации.
 */
require_once __DIR__
    . '/query-arguments/pagination.php';


/*
 * Нормализация поискового запроса.
 */
require_once __DIR__
    . '/query-arguments/search.php';


/*
 * Нормализация ограничений по ID записей.
 */
require_once __DIR__
    . '/query-arguments/post-ids.php';


/*
 * Нормализация сортировки.
 */
require_once __DIR__
    . '/query-arguments/ordering.php';


/**
 * Формирует закрытый набор аргументов WP_Query.
 *
 * Исходный массив никогда не передаётся
 * непосредственно в WP_Query.
 *
 * Каждый функциональный блок аргументов
 * проходит самостоятельную нормализацию.
 *
 * Неизвестные параметры игнорируются.
 *
 * Некорректное значение поддерживаемого
 * параметра приводит к закрытому отказу.
 *
 * Порядок обработки:
 *
 *     1. Пагинация.
 *     2. Поисковый запрос.
 *     3. Ограничения по ID.
 *     4. Сортировка.
 *
 * Сортировка обрабатывается последней,
 * поскольку отдельные критерии зависят
 * от поискового запроса и списка post__in.
 *
 * @internal
 *
 * @param mixed $args Исходные аргументы.
 *
 * @return array|null Нормализованные аргументы
 *                    или null при отказе.
 */
function dilitant_land_normalize_blog_query_args(
    $args
) {
    /*
     * 1. Проверяем тип входных данных.
     */
    if ( ! is_array( $args ) ) {
        return null;
    }


    /*
     * 2. Нормализуем пагинацию.
     */
    $pagination =
        dilitant_land_normalize_blog_pagination_args(
            $args
        );

    if ( null === $pagination ) {
        return null;
    }


    /*
     * 3. Нормализуем поисковый запрос.
     */
    $search =
        dilitant_land_normalize_blog_search_args(
            $args
        );

    if ( null === $search ) {
        return null;
    }


    /*
     * 4. Нормализуем включение
     * и исключение публикаций.
     */
    $post_ids =
        dilitant_land_normalize_blog_post_id_args(
            $args
        );

    if ( null === $post_ids ) {
        return null;
    }


    /*
     * 5. Объединяем проверенные блоки.
     *
     * Исходный массив args не копируется
     * в результирующую структуру.
     */
    $normalized = array_merge(
        $pagination,
        $search,
        $post_ids
    );


    /*
     * 6. Нормализуем сортировку.
     *
     * Используются уже проверенные
     * поисковый запрос и списки ID.
     */
    $ordering =
        dilitant_land_normalize_blog_order_args(
            $args,
            $normalized
        );

    if ( null === $ordering ) {
        return null;
    }


    /*
     * 7. Возвращаем итоговый закрытый
     * набор аргументов.
     */
    return array_merge(
        $normalized,
        $ordering
    );
}