<?php
/**
 * Точка входа модели статей блога
 * «Дилетанты в Армении».
 *
 * Нормативная основа:
 *     ТП-03 — Editorial, редакция 1.1
 *     EDC-01 — Article Model
 *     AM-D01 — Единая семантика принадлежности
 *     AM-D02 — Защита инвариантов WP_Query
 *     AM-D03 — Публичная доступность
 *
 * Ответственность:
 *     - подключение компонентов Article Model;
 *     - обеспечение порядка зависимостей;
 *     - сохранение действующих интерфейсов.
 *
 * Article Model определяет:
 *
 *     - нормативное дерево категорий;
 *     - идентификацию статьи;
 *     - публичную доступность;
 *     - авторизацию Preview;
 *     - допустимость отображения;
 *     - политику публичной выборки;
 *     - нормализацию аргументов;
 *     - построение WP_Query.
 *
 * Бизнес-логика в данном файле отсутствует.
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/*
 * Константы Article Model.
 */
require_once __DIR__
    . '/article/constants.php';


/*
 * Общие механизмы нормализации.
 */
require_once __DIR__
    . '/article/normalization.php';


/*
 * Нормативное дерево категорий.
 */
require_once __DIR__
    . '/article/categories.php';


/*
 * Идентификация принадлежности.
 *
 * Не зависит от статуса публикации,
 * парольной защиты и полномочий
 * текущего пользователя.
 */
require_once __DIR__
    . '/article/identity.php';


/*
 * Нормативная публичная доступность.
 *
 * Зависит от идентификации статьи.
 */
require_once __DIR__
    . '/article/visibility.php';


/*
 * Авторизация предварительного просмотра.
 *
 * Зависит от:
 *
 *     - Article Identity;
 *     - Article Public Visibility;
 *     - механизмов авторизации WordPress.
 *
 * Также определяет допустимость
 * отображения статьи.
 */
require_once __DIR__
    . '/article/preview.php';


/*
 * Публичный адрес каталога.
 */
require_once __DIR__
    . '/article/routing.php';


/*
 * Нормативная политика публичной выборки.
 */
require_once __DIR__
    . '/article/query-policy.php';


/*
 * Проверка и нормализация
 * аргументов публичной выборки.
 */
require_once __DIR__
    . '/article/query-arguments.php';


/*
 * Единый построитель нормативных
 * аргументов WP_Query.
 */
require_once __DIR__
    . '/article/query-builder.php';


/*
 * Публичный интерфейс
 * получения статей.
 */
require_once __DIR__
    . '/article/query.php';