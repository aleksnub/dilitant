<?php
/**
 * Регистрация маршрутов блога
 * «Дилетанты в Армении».
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *
 * Публичные маршруты:
 *
 *     /diletant-armenia/articles/
 *
 *     /diletant-armenia/articles/<slug>/
 *
 * Ответственность:
 *     - регистрация маршрута каталога;
 *     - регистрация маршрута отдельной статьи;
 *     - преобразование публичных адресов
 *       в параметры запроса WordPress.
 *
 * Регистрация маршрута сама по себе
 * не является разрешением доступа.
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/**
 * Регистрирует маршруты блога.
 *
 * Существующие правила сохраняются
 * без изменения семантики.
 *
 * @return void
 */
function dilitant_land_register_blog_rewrite_rules() {

    /*
     * Публичный каталог статей.
     *
     * /diletant-armenia/articles/
     *
     * WordPress разрешает запрос
     * как архив корневой категории.
     *
     * Нормативные параметры выборки
     * применяются механизмом AM-D02.
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
     * Проверка публичной доступности
     * выполняется после разрешения
     * конкретного объекта WordPress.
     */
    add_rewrite_rule(
        '^diletant-armenia/articles/([^/]+)/?$',

        'index.php?category_name=diletant-armenia&name=$matches[1]',

        'top'
    );
}


/*
 * Регистрируем правила на штатной
 * стадии инициализации WordPress.
 */
add_action(
    'init',
    'dilitant_land_register_blog_rewrite_rules'
);