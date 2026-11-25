<?php
/**
 * Политика выборки Article Model.
 *
 * Ответственность:
 *     - определение обязательных условий;
 *     - определение допустимых способов сортировки;
 *     - определение размера страницы по умолчанию;
 *     - формирование нормативного tax_query.
 *
 * Нормативная основа:
 *     EDC-01
 *     AM-D02
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/**
 * Возвращает обязательные условия
 * публичной выборки статей.
 *
 * Эти параметры управляются исключительно
 * моделью и не могут переопределяться
 * вызывающим кодом.
 *
 * @internal
 *
 * @return array
 */
function dilitant_land_get_blog_mandatory_query_args() {
    return array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'has_password'        => false,
        'ignore_sticky_posts' => true,
    );
}


/**
 * Возвращает закрытый перечень допустимых
 * значений параметра orderby.
 *
 * @internal
 *
 * @return string[]
 */
function dilitant_land_get_blog_allowed_orderby() {
    return array(
        'date',
        'modified',
        'title',
        'ID',
        'post__in',
        'relevance',
    );
}


/**
 * Возвращает размер страницы по умолчанию.
 *
 * Использует общую настройку WordPress
 * posts_per_page с установленным ограничением.
 *
 * @internal
 *
 * @return int
 */
function dilitant_land_get_blog_default_posts_per_page() {
    $value = get_option(
        'posts_per_page',
        10
    );

    $value =
        dilitant_land_normalize_blog_positive_integer(
            $value
        );

    if ( null === $value ) {
        return 10;
    }

    return min(
        $value,
        DILITANT_LAND_BLOG_MAX_POSTS_PER_PAGE
    );
}


/**
 * Формирует нормативное ограничение категорий.
 *
 * Вызывающий код обязан предварительно проверить,
 * что множество категорий не пусто и принадлежит
 * нормативному дереву блога.
 *
 * Повторное раскрытие дочерних категорий
 * средствами WP_Query запрещено:
 * множество уже раскрыто моделью.
 *
 * @internal
 *
 * @param int[] $category_ids Проверенное множество.
 *
 * @return array
 */
function dilitant_land_build_blog_tax_query(
    $category_ids
) {
    return array(
        array(
            'taxonomy'         => 'category',
            'field'            => 'term_id',
            'terms'            => $category_ids,
            'include_children' => false,
            'operator'         => 'IN',
        ),
    );
}