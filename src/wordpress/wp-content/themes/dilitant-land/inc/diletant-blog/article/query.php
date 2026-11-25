<?php
/**
 * Исполнитель выборки Article Model.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial, редакция 1.1
 *     EDC-01 — Article Model
 *     AM-D01 — Единая семантика принадлежности
 *     AM-D02 — Защита инвариантов WP_Query
 *
 * Ответственность:
 *     - получение нормативных аргументов;
 *     - создание экземпляров WP_Query;
 *     - предоставление публичного интерфейса
 *       выборки статей;
 *     - сохранение совместимости существующих
 *       потребителей Article Model.
 *
 * Формирование аргументов выполняется
 * компонентом query-builder.php.
 *
 * Непосредственная нормализация аргументов,
 * разрешение категорий и определение политики
 * в данном файле не выполняются.
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/**
 * Возвращает заведомо пустую выборку.
 *
 * Сохраняется существующий интерфейс
 * для обратной совместимости.
 *
 * Аргументы закрытого отказа формируются
 * единым нормативным построителем.
 *
 * @internal
 *
 * @return WP_Query
 */
function dilitant_land_get_empty_blog_articles_query() {
    return new WP_Query(
        dilitant_land_build_empty_blog_query_args()
    );
}


/**
 * Возвращает публичные статьи блога.
 *
 * Поддерживаемые параметры:
 *
 *     posts_per_page
 *     paged
 *     orderby
 *     order
 *     s
 *     post__in
 *     post__not_in
 *     category_id
 *
 * Обязательные инварианты:
 *
 *     post_type           = post
 *     post_status         = publish
 *     has_password        = false
 *     ignore_sticky_posts = true
 *
 * Принадлежность нормативному дереву
 * категорий устанавливается построителем.
 *
 * Неизвестные аргументы не передаются
 * в WP_Query.
 *
 * При некорректных входных данных
 * выполняется заведомо пустой запрос.
 *
 * Подсчёт общего количества публикаций
 * сохраняется для корректной пагинации.
 *
 * @param mixed $args Поддерживаемые параметры.
 *
 * @return WP_Query
 */
function dilitant_land_get_blog_articles(
    $args = array()
) {
    $query_args =
        dilitant_land_build_blog_query_args(
            $args
        );

    return new WP_Query(
        $query_args
    );
}