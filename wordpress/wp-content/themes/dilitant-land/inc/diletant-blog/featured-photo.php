<?php
/**
 * Архивная фотография как обложка статьи
 * блога «Дилетанты в Армении».
 *
 * В метаданных статьи хранится ID archive_photo,
 * а не ID attachment и не URL файла.
 *
 * Новый attachment или JPEG этот модуль не создаёт.
 *
 * @package Dilitant_Land
 */


/**
 * Meta key статьи, содержащий ID архивной фотографии,
 * выбранной в качестве обложки.
 */
const DILITANT_LAND_FEATURED_ARCHIVE_PHOTO_META_KEY = '_dilitant_featured_archive_photo';


/**
 * Возвращает archive_photo, выбранный как обложка статьи.
 *
 * Возвращается только опубликованный archive_photo.
 *
 * @param int|WP_Post|null $post ID статьи, объект записи
 *                               или текущая запись.
 *
 * @return WP_Post|null
 */
function dilitant_land_get_featured_archive_photo( $post = null ) {
    $post = get_post( $post );

    if (
        ! $post instanceof WP_Post
        || ! function_exists( 'dilitant_land_is_blog_article' )
        || ! dilitant_land_is_blog_article( $post )
    ) {
        return null;
    }

    $archive_photo_id = (int) get_post_meta(
        $post->ID,
        DILITANT_LAND_FEATURED_ARCHIVE_PHOTO_META_KEY,
        true
    );

    if ( $archive_photo_id <= 0 ) {
        return null;
    }

    $archive_photo = get_post( $archive_photo_id );

    if (
        ! $archive_photo instanceof WP_Post
        || 'archive_photo' !== $archive_photo->post_type
        || 'publish' !== $archive_photo->post_status
    ) {
        return null;
    }

    return $archive_photo;
}


/**
 * Возвращает ID публичного attachment,
 * используемого архивной фотографией.
 *
 * Никакой новый attachment или JPEG здесь не создаётся.
 *
 * @param int|WP_Post|null $post ID статьи, объект записи
 *                               или текущая запись.
 *
 * @return int ID attachment или 0.
 */
function dilitant_land_get_featured_archive_photo_attachment_id( $post = null ) {
    $archive_photo = dilitant_land_get_featured_archive_photo( $post );

    if ( ! $archive_photo instanceof WP_Post ) {
        return 0;
    }

    $attachment_id = (int) get_post_thumbnail_id( $archive_photo->ID );

    if ( $attachment_id <= 0 ) {
        return 0;
    }

    if ( ! wp_attachment_is_image( $attachment_id ) ) {
        return 0;
    }

    return $attachment_id;
}