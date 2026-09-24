<?php
/**
 * Метаданные архивной фотографии.
 *
 * Регистрация дополнительных данных archive_photo
 * в WordPress и REST API.
 *
 * @package Dilitant_Land
 */


/**
 * Регистрация метаданных archive_photo.
 */
function dilitant_land_register_archive_photo_meta() {

    /*
     * Однострочные текстовые поля.
     */
    $text_meta = array(
        '_archive_photo_approx_date',
        '_archive_photo_place',
        '_archive_photo_photographer',
        '_archive_photo_provided_by',
    );

    foreach ( $text_meta as $meta_key ) {
        register_post_meta(
            'archive_photo',
            $meta_key,
            array(
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
    }

    /*
     * Многострочные текстовые поля.
     */
    $textarea_meta = array(
        '_archive_photo_source',
        '_archive_photo_rights',
    );

    foreach ( $textarea_meta as $meta_key ) {
        register_post_meta(
            'archive_photo',
            $meta_key,
            array(
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_textarea_field',
                'auth_callback'     => function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
    }

    /*
     * Внутренние связи записи фотоархива.
     *
     * related_article — связанная публикация.
     *
     * submission_id — заявка, из которой была создана
     * эта запись фотоархива. Используется системой
     * для защиты от дублей и восстановления после сбоя.
     *
     * Связанная статья доступна через REST API.
     * Внутренняя ссылка на заявку через REST API
     * не публикуется.
     */
    $integer_meta = array(
        '_archive_photo_related_article' => true,
        '_archive_photo_submission_id'   => false,
    );

    foreach ( $integer_meta as $meta_key => $show_in_rest ) {
        register_post_meta(
            'archive_photo',
            $meta_key,
            array(
                'type'              => 'integer',
                'single'            => true,
                'show_in_rest'      => $show_in_rest,
                'sanitize_callback' => 'absint',
                'auth_callback'     => function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
    }
}

add_action(
    'init',
    'dilitant_land_register_archive_photo_meta'
);