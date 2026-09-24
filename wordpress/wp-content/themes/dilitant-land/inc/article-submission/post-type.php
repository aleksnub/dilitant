<?php
/**
 * Внутренний тип записи для заявок на статьи.
 *
 * Заявка не является опубликованной статьёй.
 * Тип записи используется только для хранения
 * и административной обработки присланных рукописей.
 *
 * Заявки создаются публичным обработчиком формы.
 * Редактор может просматривать и рассматривать
 * существующие заявки, но не создаёт их вручную.
 *
 * @package Dilitant_Land
 */


/**
 * Регистрирует внутренний тип записи article_submission.
 */
function dilitant_land_register_article_submission_post_type() {

    $labels = array(
        'name'               => 'Заявки на статьи',
        'singular_name'      => 'Заявка на статью',
        'menu_name'          => 'Заявки на статьи',
        'edit_item'          => 'Просмотреть заявку',
        'view_item'          => 'Просмотреть заявку',
        'search_items'       => 'Найти заявку',
        'not_found'          => 'Заявки не найдены',
        'not_found_in_trash' => 'В корзине заявок нет',
    );

    register_post_type(
        'article_submission',
        array(
            'labels'             => $labels,

            /*
             * Заявка полностью закрыта
             * от публичной части WordPress.
             */
            'public'             => false,
            'publicly_queryable' => false,
            'show_in_rest'       => false,
            'has_archive'        => false,
            'rewrite'            => false,
            'query_var'          => false,

            /*
             * Административный список и карточка
             * заявки редактору доступны.
             */
            'show_ui'            => true,
            'show_in_menu'       => true,

            /*
             * Стандартные редактируемые поля WordPress
             * заявке не нужны.
             *
             * Заголовок, текстовый редактор и прочие
             * стандартные поля отключены.
             *
             * Все сведения о заявке выводятся
             * нашими административными блоками.
             */
            'supports'           => false,

            /*
             * Для просмотра и рассмотрения
             * существующих заявок используются
             * обычные редакторские права WordPress.
             */
            'capability_type'    => 'post',
            'map_meta_cap'       => true,

            /*
             * Создавать заявки вручную через wp-admin
             * запрещено.
             *
             * Новая заявка появляется только после
             * успешной обработки публичной формы.
             */
            'capabilities'       => array(
                'create_posts' => 'do_not_allow',
            ),

            'menu_icon'          => 'dashicons-media-document',
        )
    );
}


add_action(
    'init',
    'dilitant_land_register_article_submission_post_type'
);