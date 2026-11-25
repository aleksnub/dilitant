<?php
/**
 * Нормативное дерево категорий Article Model.
 *
 * Ответственность:
 *     - разрешение корневой категории;
 *     - рекурсивное получение дочерних категорий;
 *     - определение полного дерева блога;
 *     - проверка допустимости фильтра category_id;
 *     - ограничение выборки подмножеством дерева.
 *
 * Нормативная основа:
 *     EDC-01
 *     AM-D01
 *     AM-D02
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/**
 * Возвращает ID корневой категории блога.
 *
 * @return int ID категории или 0 при отсутствии.
 */
function dilitant_land_get_blog_category_id() {
    $category = get_category_by_slug(
        DILITANT_LAND_BLOG_CATEGORY_SLUG
    );

    if ( ! $category instanceof WP_Term ) {
        return 0;
    }

    if ( 'category' !== $category->taxonomy ) {
        return 0;
    }

    return (int) $category->term_id;
}


/**
 * Возвращает идентификаторы корневой категории
 * и всех её дочерних категорий.
 *
 * Глубина вложенности не ограничивается.
 *
 * При отсутствии корневой категории или
 * невозможности получить дерево категорий
 * возвращается пустой массив.
 *
 * @return int[]
 */
function dilitant_land_get_blog_category_ids() {
    $root_id =
        dilitant_land_get_blog_category_id();

    if ( $root_id <= 0 ) {
        return array();
    }

    $children = get_term_children(
        $root_id,
        'category'
    );

    if (
        is_wp_error( $children )
        || ! is_array( $children )
    ) {
        return array();
    }

    $category_ids = array_merge(
        array( $root_id ),
        $children
    );

    $category_ids = array_map(
        'absint',
        $category_ids
    );

    $category_ids = array_filter(
        $category_ids
    );

    $category_ids = array_unique(
        $category_ids
    );

    return array_values(
        $category_ids
    );
}


/**
 * Определяет допустимое множество категорий
 * для конкретной выборки статей.
 *
 * Если category_id отсутствует, используется
 * полное нормативное дерево категорий.
 *
 * Если category_id передан:
 *     - проверяется корректность идентификатора;
 *     - проверяется принадлежность дереву блога;
 *     - раскрываются дочерние категории;
 *     - результат пересекается с полным деревом.
 *
 * При нарушении любого условия возвращается
 * пустое множество категорий.
 *
 * @internal
 *
 * @param int[] $blog_category_ids Полное дерево блога.
 * @param array $args              Аргументы выборки.
 *
 * @return int[]
 */
function dilitant_land_resolve_blog_query_category_ids(
    $blog_category_ids,
    $args
) {
    if ( empty( $blog_category_ids ) ) {
        return array();
    }

    /*
     * Отсутствие фильтра означает выборку
     * по всему дереву категорий блога.
     */
    if ( ! array_key_exists( 'category_id', $args ) ) {
        return $blog_category_ids;
    }

    /*
     * Проверяем идентификатор категории.
     */
    $category_id =
        dilitant_land_normalize_blog_positive_integer(
            $args['category_id']
        );

    if ( null === $category_id ) {
        return array();
    }

    /*
     * Посторонняя категория не может быть
     * использована в качестве фильтра блога.
     */
    if (
        ! in_array(
            $category_id,
            $blog_category_ids,
            true
        )
    ) {
        return array();
    }

    /*
     * Получаем дочерние категории
     * выбранной ветви дерева.
     */
    $children = get_term_children(
        $category_id,
        'category'
    );

    if (
        is_wp_error( $children )
        || ! is_array( $children )
    ) {
        return array();
    }

    /*
     * Нормализуем выбранное множество.
     */
    $selected_ids =
        dilitant_land_normalize_blog_id_list(
            array_merge(
                array( $category_id ),
                $children
            )
        );

    if ( null === $selected_ids ) {
        return array();
    }

    /*
     * Дополнительное пересечение исключает
     * появление категорий за пределами
     * нормативного дерева блога.
     */
    return array_values(
        array_intersect(
            $blog_category_ids,
            $selected_ids
        )
    );
}