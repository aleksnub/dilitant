<?php
/**
 * Формирование публичных адресов
 * статей блога «Дилетанты в Армении».
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D01 — Идентификация статьи
 *     AM-D03 — Публичная доступность
 *
 * Ответственность:
 *     - формирование специального permalink;
 *     - сохранение штатных адресов
 *       остальных записей WordPress;
 *     - использование нормативного
 *       предиката публичной доступности.
 *
 * Формирование URL не является
 * самостоятельным разрешением доступа.
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/**
 * Возвращает специальный публичный URL
 * для публично доступной статьи блога.
 *
 * Если запись не принадлежит блогу
 * или не является публично доступной,
 * сохраняется исходный адрес WordPress.
 *
 * Это необходимо в том числе для
 * штатных механизмов редактирования
 * и предварительного просмотра.
 *
 * @param string  $permalink Исходный URL.
 * @param WP_Post $post      Объект записи.
 *
 * @return string
 */
function dilitant_land_filter_blog_post_permalink(
    $permalink,
    $post
) {
    /*
     * Проверяем действительность
     * переданного объекта.
     */
    if ( ! $post instanceof WP_Post ) {
        return $permalink;
    }


    /*
     * Используем единый нормативный
     * предикат Article Model.
     *
     * Самостоятельная проверка категорий
     * внутри маршрутизации запрещена.
     */
    if (
        ! dilitant_land_is_public_blog_article(
            $post
        )
    ) {
        return $permalink;
    }


    /*
     * Специальный маршрут требует
     * непустого идентификатора slug.
     */
    if ( '' === $post->post_name ) {
        return $permalink;
    }


    /*
     * Формируем публичный URL,
     * сохраняя действующую схему адресов.
     */
    return home_url(
        user_trailingslashit(
            'diletant-armenia/articles/'
            . $post->post_name
        )
    );
}


/*
 * Сохраняем действующий приоритет
 * регистрации фильтра.
 */
add_filter(
    'post_link',
    'dilitant_land_filter_blog_post_permalink',
    10,
    2
);