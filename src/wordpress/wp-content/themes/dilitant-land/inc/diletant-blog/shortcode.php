<?php
/**
 * Shortcode архивной фотографии внутри статьи.
 *
 * Использование:
 *
 * [archive_photo id="46"]
 *
 * Shortcode хранит ссылку на archive_photo,
 * а не URL JPEG и не ID attachment.
 *
 * @package Dilitant_Land
 */


/**
 * Выводит опубликованную архивную фотографию.
 *
 * @param array $atts Атрибуты shortcode.
 *
 * @return string HTML или пустая строка.
 */
function dilitant_land_archive_photo_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'id' => 0,
        ),
        $atts,
        'archive_photo'
    );

    $archive_photo_id = absint( $atts['id'] );

    /*
     * ID обязателен.
     */
    if ( $archive_photo_id <= 0 ) {
        return '';
    }

    $archive_photo = get_post( $archive_photo_id );

    /*
     * Разрешён только существующий archive_photo.
     */
    if (
        ! $archive_photo instanceof WP_Post
        || 'archive_photo' !== $archive_photo->post_type
    ) {
        return '';
    }

    /*
     * Черновики, приватные и удалённые фотографии
     * внутри публичной статьи не выводятся.
     */
    if ( 'publish' !== $archive_photo->post_status ) {
        return '';
    }

    /*
     * Используем только уже существующее публичное
     * изображение archive_photo.
     */
    $attachment_id = (int) get_post_thumbnail_id( $archive_photo_id );

    if ( $attachment_id <= 0 ) {
        return '';
    }

    /*
     * Attachment должен действительно быть изображением.
     */
    if ( ! wp_attachment_is_image( $attachment_id ) ) {
        return '';
    }

    $image = wp_get_attachment_image(
        $attachment_id,
        'large',
        false,
        array(
            'class' => 'diletant-embedded-archive-photo__image',
        )
    );

    if ( ! $image ) {
        return '';
    }

    $photo_url = get_permalink( $archive_photo_id );

    if ( ! $photo_url ) {
        return '';
    }

    $photographer = get_post_meta(
        $archive_photo_id,
        '_archive_photo_photographer',
        true
    );

    $caption = '';

    if ( $photographer ) {
        $caption = sprintf(
            '<figcaption class="diletant-embedded-archive-photo__caption">Фотограф: %s</figcaption>',
            esc_html( $photographer )
        );
    }

    return sprintf(
        '<figure class="diletant-embedded-archive-photo"><a class="diletant-embedded-archive-photo__link" href="%1$s">%2$s</a>%3$s</figure>',
        esc_url( $photo_url ),
        $image,
        $caption
    );
}


/**
 * Регистрирует shortcode [archive_photo].
 */
function dilitant_land_register_archive_photo_shortcode() {
    add_shortcode(
        'archive_photo',
        'dilitant_land_archive_photo_shortcode'
    );
}
add_action(
    'init',
    'dilitant_land_register_archive_photo_shortcode'
);