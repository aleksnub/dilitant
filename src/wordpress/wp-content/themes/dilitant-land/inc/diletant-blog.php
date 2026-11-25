<?php
/**
 * Загрузчик функциональности блога
 * «Дилетанты в Армении».
 *
 * Нормативная основа:
 *     ТП-03 — Editorial, редакция 1.1
 *     EDC-01 — Article Model
 *
 * Ответственность:
 *     - подключение маршрутизации;
 *     - подключение Article Model;
 *     - подключение политики глобального
 *       запроса публичного каталога;
 *     - интеграция с фотоархивом;
 *     - подключение административных модулей.
 *
 * Бизнес-логика непосредственно
 * в данном файле не размещается.
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/*
 * Модель статей.
 */
require_once __DIR__
    . '/diletant-blog/article.php';


/*
 * Публичная маршрутизация.
 */
require_once __DIR__
    . '/diletant-blog/routing.php';


/*
 * Применение нормативной политики
 * к глобальному запросу каталога.
 */
require_once __DIR__
    . '/diletant-blog/catalog-query.php';


/*
 * Связанные фотографии.
 */
require_once __DIR__
    . '/diletant-blog/archive-photos.php';


/*
 * Обложка статьи из фотоархива.
 */
require_once __DIR__
    . '/diletant-blog/featured-photo.php';


/*
 * Вставка архивных фотографий.
 */
require_once __DIR__
    . '/diletant-blog/shortcode.php';


/*
 * Административная функциональность.
 */
if ( is_admin() ) {
    require_once __DIR__
        . '/diletant-blog/admin.php';
}