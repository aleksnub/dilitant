<?php
/**
 * Единый построитель нормативных аргументов
 * WP_Query для Article Model.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial, редакция 1.1
 *     EDC-01 — Article Model
 *     AM-D01 — Единая семантика принадлежности
 *     AM-D02 — Защита инвариантов WP_Query
 *
 * Ответственность:
 *     - разрешение нормативного дерева категорий;
 *     - применение разрешённого category_id;
 *     - нормализация входных аргументов;
 *     - применение обязательной политики;
 *     - формирование нормативного tax_query;
 *     - формирование аргументов закрытого отказа.
 *
 * Компонент не создаёт WP_Query
 * и не изменяет глобальный запрос WordPress.
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/**
 * Формирует аргументы заведомо пустой выборки.
 *
 * Используется при невозможности подтвердить
 * корректность входных данных или нормативного
 * дерева категорий.
 *
 * Пустой post__in в WordPress может означать
 * отсутствие ограничения, поэтому применяется
 * заведомо несуществующий идентификатор 0.
 *
 * Обязательные ограничения сохраняются.
 *
 * @internal
 *
 * @return array
 */
function dilitant_land_build_empty_blog_query_args(): array {
    return array_merge(
        dilitant_land_get_blog_mandatory_query_args(),
        array(
            'post__in'       => array( 0 ),
            'posts_per_page' => 1,
            'paged'          => 1,
            'no_found_rows'  => true,
        )
    );
}


/**
 * Формирует нормативные аргументы WP_Query.
 *
 * Разрешённые входные параметры:
 *
 *     posts_per_page;
 *     paged;
 *     orderby;
 *     order;
 *     s;
 *     post__in;
 *     post__not_in;
 *     category_id.
 *
 * Неизвестные параметры игнорируются.
 *
 * Некорректные значения разрешённых
 * параметров приводят к закрытому отказу.
 *
 * Обязательная политика имеет приоритет
 * над переданными аргументами.
 *
 * @param mixed $args Исходные аргументы.
 *
 * @return array
 */
function dilitant_land_build_blog_query_args(
    $args = array()
): array {
    /*
     * 1. Проверяем тип входного значения.
     */
    if ( ! is_array( $args ) ) {
        return
            dilitant_land_build_empty_blog_query_args();
    }


    /*
     * 2. Получаем нормативное дерево
     * категорий блога.
     */
    $blog_category_ids =
        dilitant_land_get_blog_category_ids();

    if ( empty( $blog_category_ids ) ) {
        return
            dilitant_land_build_empty_blog_query_args();
    }


    /*
     * 3. Разрешаем допустимое подмножество
     * нормативного дерева.
     *
     * При отсутствии category_id
     * используется всё дерево.
     *
     * Некорректный category_id
     * не расширяет выборку.
     */
    $category_ids =
        dilitant_land_resolve_blog_query_category_ids(
            $blog_category_ids,
            $args
        );

    if ( empty( $category_ids ) ) {
        return
            dilitant_land_build_empty_blog_query_args();
    }


    /*
     * 4. Нормализуем входные аргументы.
     *
     * Исходный массив не копируется
     * в результирующую структуру.
     */
    $query_args =
        dilitant_land_normalize_blog_query_args(
            $args
        );

    if ( null === $query_args ) {
        return
            dilitant_land_build_empty_blog_query_args();
    }


    /*
     * 5. Применяем обязательную политику.
     *
     * Политика объединяется последней,
     * поэтому обязательные значения
     * имеют приоритет.
     */
    $query_args = array_merge(
        $query_args,

        dilitant_land_get_blog_mandatory_query_args()
    );


    /*
     * 6. Формируем нормативное ограничение
     * по дереву категорий.
     */
    $query_args['tax_query'] =
        dilitant_land_build_blog_tax_query(
            $category_ids
        );


    /*
     * 7. Возвращаем проверенный массив.
     *
     * Создание WP_Query выполняется
     * внешним потребителем.
     */
    return $query_args;
}