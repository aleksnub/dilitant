<?php
/**
 * Регистрация внутреннего типа записи photo_submission.
 *
 * Заявка создаётся системой после отправки публичной формы.
 * Редактор может просматривать и модерировать заявку,
 * но не может создавать её вручную.
 *
 * @package Dilitant_Land
 */


/**
 * Регистрация типа записи «Заявка на фото».
 */
function dilitant_land_register_photo_submission() {
    $labels = array(
        'name'               => 'Заявки на фото',
        'singular_name'      => 'Заявка на фото',
        'menu_name'          => 'Заявки на фото',
        'edit_item'          => 'Рассмотреть заявку',
        'view_item'          => 'Просмотреть заявку',
        'search_items'       => 'Искать заявки',
        'not_found'          => 'Заявки не найдены',
        'not_found_in_trash' => 'В корзине заявки не найдены',
    );

    $args = array(
        'labels' => $labels,

        /*
         * Заявка — внутренний объект редакции,
         * а не публичный материал сайта.
         */
        'public'              => false,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,

        /*
         * Заявки доступны редакции в wp-admin.
         *
         * Создавать их вручную через административный
         * интерфейс запрещено: новые заявки должна
         * создавать только система при приёме формы.
         */
        'show_ui'      => true,
        'show_in_menu' => true,

        'capabilities' => array(
            'create_posts' => 'do_not_allow',
        ),

        'map_meta_cap' => true,

        /*
         * Заявки не выдаются через REST API.
         */
        'show_in_rest' => false,

        /*
         * Публичного архива и публичных URL заявок нет.
         */
        'has_archive' => false,
        'rewrite'     => false,

        /*
         * Заголовок используется для служебного
         * обозначения заявки.
         *
         * editor пока оставляем для внутренней
         * редакционной заметки.
         */
        /*
        * Заявка не редактируется стандартными полями WordPress.
        *
        * Заголовок и остальные исходные данные создаёт система.
        * Редактор работает только через специализированный
        * административный интерфейс заявки.
        */
        'supports' => false,

        'menu_icon' => 'dashicons-email-alt',
    );

    register_post_type( 'photo_submission', $args );
}

add_action(
    'init',
    'dilitant_land_register_photo_submission'
);