<?php
/**
 * Идентификация статей блога.
 *
 * Ответственность:
 *     - разрешение записи WordPress;
 *     - проверка типа записи;
 *     - проверка принадлежности дереву категорий.
 *
 * Нормативная основа:
 *     EDC-01
 *     AM-D01
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/**
 * Проверяет принадлежность записи к блогу
 * «Дилетанты в Армении».
 *
 * Принадлежность определяется:
 *
 *     1. Существованием записи WordPress.
 *     2. Типом записи post.
 *     3. Наличием хотя бы одной категории
 *        из нормативного дерева блога.
 *
 * Статус публикации не учитывается.
 *
 * Черновики, приватные записи и записи
 * с парольной защитой могут принадлежать блогу,
 * не будучи доступными публичному читателю.
 *
 * При отсутствии корневой категории
 * принадлежность не подтверждается.
 *
 * @param int|WP_Post|null $post ID записи, объект записи
 *                               или текущая запись.
 *
 * @return bool
 */
function dilitant_land_is_blog_article(
    $post = null
) {
    /*
     * Разрешаем запись средствами WordPress.
     */
    $post = get_post( $post );

    if ( ! $post instanceof WP_Post ) {
        return false;
    }

    /*
     * Статья должна быть обычной записью.
     */
    if ( 'post' !== $post->post_type ) {
        return false;
    }

    /*
     * Получаем нормативное дерево блога.
     */
    $category_ids =
        dilitant_land_get_blog_category_ids();

    if ( empty( $category_ids ) ) {
        return false;
    }

    /*
     * Получаем фактические категории записи.
     */
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

    /*
     * Для подтверждения принадлежности
     * достаточно одной общей категории.
     */
    return ! empty(
        array_intersect(
            $category_ids,
            $post_category_ids
        )
    );
}