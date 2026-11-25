<?php
/**
 * Публичные адреса Article Model.
 *
 * Ответственность:
 *     - предоставление URL каталога статей.
 *
 * Регистрация rewrite rules и обработка
 * входящих HTTP-запросов выполняются
 * внешним модулем маршрутизации.
 *
 * Нормативная основа:
 *     EDC-01
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/**
 * Возвращает URL списка статей блога.
 *
 * Публичный адрес определяется собственной
 * маршрутизацией, а не стандартным URL
 * категории WordPress.
 *
 * Существующий URL сохраняется:
 *
 *     /diletant-armenia/articles/
 *
 * @return string
 */
function dilitant_land_get_blog_url() {
    return home_url(
        user_trailingslashit(
            'diletant-armenia/articles'
        )
    );
}