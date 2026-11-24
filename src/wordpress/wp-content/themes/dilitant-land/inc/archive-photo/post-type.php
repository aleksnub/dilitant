<?php
/**
 * Регистрация типа записи archive_photo.
 *
 * @package Dilitant_Land
 */


/**
 * Регистрация типа записи «Архивная фотография».
 */
function dilitant_land_register_archive_photo() {
    $labels = array(
        'name'               => 'Архивные фотографии',
        'singular_name'      => 'Архивная фотография',
        'menu_name'          => 'Фотоархив',
        'add_new'            => 'Добавить фотографию',
        'add_new_item'       => 'Добавить архивную фотографию',
        'edit_item'          => 'Редактировать фотографию',
        'new_item'           => 'Новая фотография',
        'view_item'          => 'Посмотреть фотографию',
        'search_items'       => 'Искать фотографии',
        'not_found'          => 'Фотографии не найдены',
        'not_found_in_trash' => 'В корзине фотографии не найдены',
    );

    $args = array(
        'labels'       => $labels,
        'public'       => true,
        'show_ui'      => true,
        'show_in_menu' => true,
        'show_in_rest' => true,

        'has_archive' => 'diletant-armenia/archive',

        'rewrite' => array(
            'slug'       => 'diletant-armenia/archive',
            'with_front' => false,
        ),

        'supports' => array(
            'title',
            'editor',
            'thumbnail',
        ),

        'menu_icon' => 'dashicons-format-image',
    );

    register_post_type( 'archive_photo', $args );
}

add_action( 'init', 'dilitant_land_register_archive_photo' );