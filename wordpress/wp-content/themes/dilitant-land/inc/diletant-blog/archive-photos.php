<?php
/**
 * Связь статей блога с фотографиями фотоархива.
 *
 * Архивная фотография связывается со статьёй через:
 *
 * _archive_photo_related_article = ID статьи
 *
 * @package Dilitant_Land
 */


/**
 * Возвращает опубликованные архивные фотографии,
 * связанные с указанной статьёй.
 *
 * @param int|WP_Post|null $post ID статьи, объект записи
 *                               или текущая запись.
 * @param array            $args Дополнительные параметры WP_Query.
 *
 * @return WP_Query
 */
function dilitant_land_get_article_archive_photos( $post = null, $args = array() ) {
    $post = get_post( $post );

    /*
     * Связанные фотографии запрашиваются только
     * для статей блога «Дилетанты в Армении».
     */
    if (
        ! $post instanceof WP_Post
        || ! function_exists( 'dilitant_land_is_blog_article' )
        || ! dilitant_land_is_blog_article( $post )
    ) {
        return new WP_Query(
            array(
                'post_type'      => 'archive_photo',
                'post__in'       => array( 0 ),
                'posts_per_page' => 1,
            )
        );
    }

    $defaults = array(
        'post_type'      => 'archive_photo',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_key'       => '_archive_photo_related_article',
        'meta_value'     => (string) $post->ID,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    $args = wp_parse_args( $args, $defaults );

    /*
     * Эти параметры являются инвариантами связи.
     * Вызывающий код не может случайно получить
     * другой тип записи или фотографии другой статьи.
     */
    $args['post_type']   = 'archive_photo';
    $args['post_status'] = 'publish';
    $args['meta_key']    = '_archive_photo_related_article';
    $args['meta_value']  = (string) $post->ID;

    return new WP_Query( $args );
}