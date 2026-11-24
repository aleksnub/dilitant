<?php
/**
 * Общая логика статей блога «Дилетанты в Армении».
 *
 * Статьями блога считаются обычные записи WordPress (post),
 * принадлежащие корневой категории diletant-armenia
 * или любой её дочерней категории независимо от глубины.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial, редакция 1.1
 *     EDC-01 — Article Model
 *     AM-D01 — Единая семантика принадлежности к блогу
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/**
 * Slug корневой категории блога.
 */
const DILITANT_LAND_BLOG_CATEGORY_SLUG = 'diletant-armenia';


/**
 * Возвращает ID корневой категории блога.
 *
 * @return int ID категории или 0, если категория не найдена.
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
 * Возвращает идентификаторы корневой категории блога
 * и всех её дочерних категорий.
 *
 * Глубина вложенности категорий не ограничивается.
 *
 * При отсутствии корневой категории или невозможности
 * получить дерево категорий возвращается пустой массив.
 *
 * @return int[]
 */
function dilitant_land_get_blog_category_ids() {
    $root_id = dilitant_land_get_blog_category_id();

    if ( $root_id <= 0 ) {
        return array();
    }

    $children = get_term_children(
        $root_id,
        'category'
    );

    if ( is_wp_error( $children ) ) {
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

    return array_values( $category_ids );
}


/**
 * Проверяет принадлежность записи к блогу
 * «Дилетанты в Армении».
 *
 * Принадлежность определяется типом записи
 * и наличием хотя бы одной категории из дерева блога.
 *
 * Статус публикации не учитывается:
 * черновики и приватные записи также могут принадлежать блогу.
 *
 * @param int|WP_Post|null $post ID записи, объект записи
 *                               или текущая запись.
 *
 * @return bool
 */
function dilitant_land_is_blog_article( $post = null ) {
    $post = get_post( $post );

    if ( ! $post instanceof WP_Post ) {
        return false;
    }

    if ( 'post' !== $post->post_type ) {
        return false;
    }

    $category_ids = dilitant_land_get_blog_category_ids();

    if ( empty( $category_ids ) ) {
        return false;
    }

    $post_category_ids = wp_get_post_categories(
        $post->ID,
        array(
            'fields' => 'ids',
        )
    );

    if (
        is_wp_error( $post_category_ids )
        || empty( $post_category_ids )
    ) {
        return false;
    }

    $post_category_ids = array_map(
        'absint',
        $post_category_ids
    );

    return ! empty(
        array_intersect(
            $category_ids,
            $post_category_ids
        )
    );
}


/**
 * Возвращает URL списка статей блога.
 *
 * Публичный адрес определяется собственной маршрутизацией,
 * а не стандартным URL категории WordPress.
 *
 * @return string
 */
function dilitant_land_get_blog_url() {
    return home_url(
        user_trailingslashit( 'diletant-armenia/articles' )
    );
}


/**
 * Возвращает опубликованные статьи блога.
 *
 * Для выборки используется то же множество категорий,
 * что и для проверки принадлежности отдельной записи.
 *
 * При отсутствии корневой категории или невозможности
 * получить дерево категорий возвращается пустая выборка.
 *
 * @param array $args Дополнительные параметры WP_Query.
 *
 * @return WP_Query
 */
function dilitant_land_get_blog_articles( $args = array() ) {
    $category_ids = dilitant_land_get_blog_category_ids();

    /*
     * Закрытый отказ.
     *
     * Пустой post__in не гарантирует пустого результата
     * в WordPress, поэтому используется array( 0 ).
     */
    if ( empty( $category_ids ) ) {
        return new WP_Query(
            array(
                'post_type'           => 'post',
                'post_status'         => 'publish',
                'post__in'            => array( 0 ),
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
            )
        );
    }

    $defaults = array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'ignore_sticky_posts' => true,
    );

    $args = wp_parse_args( $args, $defaults );

    /*
     * Исключаем альтернативные параметры категорий,
     * способные изменить семантику выборки.
     */
    unset(
        $args['cat'],
        $args['category_name'],
        $args['category__in'],
        $args['category__not_in'],
        $args['category__and']
    );

    /*
     * Обязательные условия выборки.
     */
    $args['post_type']           = 'post';
    $args['post_status']         = 'publish';
    $args['ignore_sticky_posts'] = true;

    /*
     * Дерево категорий уже раскрыто моделью.
     * Повторное включение дочерних категорий
     * средствами WP_Query не требуется.
     */
    $args['tax_query'] = array(
        array(
            'taxonomy'         => 'category',
            'field'            => 'term_id',
            'terms'            => $category_ids,
            'include_children' => false,
            'operator'         => 'IN',
        ),
    );

    return new WP_Query( $args );
}