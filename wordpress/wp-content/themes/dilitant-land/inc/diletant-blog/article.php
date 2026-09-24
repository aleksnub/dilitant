<?php
/**
 * Общая логика статей блога «Дилетанты в Армении».
 *
 * Статьями блога считаются обычные записи WordPress (post),
 * относящиеся к категории diletant-armenia.
 *
 * @package Dilitant_Land
 */


/**
 * Slug категории блога «Дилетанты в Армении».
 */
const DILITANT_LAND_BLOG_CATEGORY_SLUG = 'diletant-armenia';


/**
 * Проверяет, является ли запись статьёй блога
 * «Дилетанты в Армении».
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

    return has_category( DILITANT_LAND_BLOG_CATEGORY_SLUG, $post );
}


/**
 * Возвращает ID категории блога «Дилетанты в Армении».
 *
 * @return int ID категории или 0, если категория не найдена.
 */
function dilitant_land_get_blog_category_id() {
    $category = get_category_by_slug( DILITANT_LAND_BLOG_CATEGORY_SLUG );

    if ( ! $category instanceof WP_Term ) {
        return 0;
    }

    return (int) $category->term_id;
}


/**
 * Возвращает URL списка статей блога.
 *
 * Публичный адрес намеренно задаётся через нашу
 * маршрутизацию, а не через стандартный URL категории.
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
 * Дополнительные параметры WP_Query можно передать
 * через $args. Категория и тип записи при этом остаются
 * зафиксированными данным модулем.
 *
 * @param array $args Дополнительные параметры запроса.
 *
 * @return WP_Query
 */
function dilitant_land_get_blog_articles( $args = array() ) {
    $defaults = array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'category_name'       => DILITANT_LAND_BLOG_CATEGORY_SLUG,
        'ignore_sticky_posts' => true,
    );

    $args = wp_parse_args( $args, $defaults );

    /*
     * Эти параметры являются инвариантами блога:
     * вызывающий код не должен случайно запросить другой
     * тип записи или другую категорию.
     */
    $args['post_type']     = 'post';
    $args['post_status']   = 'publish';
    $args['category_name'] = DILITANT_LAND_BLOG_CATEGORY_SLUG;

    return new WP_Query( $args );
}
