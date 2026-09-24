<?php
/**
 * Маршрутизация блога «Дилетанты в Армении».
 *
 * Публичные адреса:
 *
 * /diletant-armenia/articles/
 * /diletant-armenia/articles/<slug>/
 *
 * Используются обычные записи WordPress (post)
 * из категории diletant-armenia.
 *
 * Остальные записи WordPress сохраняют свои обычные URL.
 *
 * @package Dilitant_Land
 */

/**
 * Регистрирует маршруты блога.
 */
function dilitant_land_register_blog_rewrite_rules() {

    /*
     * Список статей.
     *
     * /diletant-armenia/articles/
     *
     * Внутри WordPress запрос превращается в архив
     * категории diletant-armenia.
     */
    add_rewrite_rule(
        '^diletant-armenia/articles/?$',
        'index.php?category_name=diletant-armenia',
        'top'
    );

    /*
     * Отдельная статья.
     *
     * /diletant-armenia/articles/<slug>/
     *
     * Ограничение category_name не позволяет этому правилу
     * превращать произвольные post в статьи данного раздела.
     */
    add_rewrite_rule(
        '^diletant-armenia/articles/([^/]+)/?$',
        'index.php?category_name=diletant-armenia&name=$matches[1]',
        'top'
    );
}
add_action( 'init', 'dilitant_land_register_blog_rewrite_rules' );


/**
 * Возвращает специальный публичный URL для обычной записи,
 * если она относится к категории diletant-armenia.
 *
 * URL остальных post не изменяется.
 *
 * @param string  $permalink Исходный URL записи.
 * @param WP_Post $post      Объект записи.
 *
 * @return string
 */
function dilitant_land_filter_blog_post_permalink( $permalink, $post ) {

    if ( ! $post instanceof WP_Post ) {
        return $permalink;
    }

    if ( 'post' !== $post->post_type ) {
        return $permalink;
    }

    if ( ! has_category( 'diletant-armenia', $post ) ) {
        return $permalink;
    }

    return home_url(
        user_trailingslashit(
            'diletant-armenia/articles/' . $post->post_name
        )
    );
}
add_filter( 'post_link', 'dilitant_land_filter_blog_post_permalink', 10, 2 );