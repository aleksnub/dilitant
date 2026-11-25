<?php
/**
 * Нормализация параметров пагинации
 * публичной выборки статей.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D02 — Защита инвариантов WP_Query
 *     AM-D05 — Пагинация каталога
 *
 * Ответственность:
 *     - нормализация posts_per_page;
 *     - нормализация paged;
 *     - применение ограничения размера страницы;
 *     - предотвращение целочисленного переполнения.
 *
 * Зависимости:
 *     article/constants.php
 *     article/normalization.php
 *     article/query-policy.php
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/**
 * Нормализует параметры пагинации.
 *
 * Поддерживаемые параметры:
 *
 *     posts_per_page:
 *         положительное целое число
 *         не больше установленного максимума.
 *
 *     paged:
 *         положительное целое число.
 *
 * Некорректные значения приводят
 * к закрытому отказу.
 *
 * @internal
 *
 * @param array $args Исходные аргументы.
 *
 * @return array|null
 */
function dilitant_land_normalize_blog_pagination_args(
    $args
) {
    /*
     * Формируем параметры по умолчанию.
     */
    $normalized = array(
        'posts_per_page' =>
            dilitant_land_get_blog_default_posts_per_page(),

        'paged' => 1,
    );


    /*
     * Количество публикаций на странице.
     */
    if ( array_key_exists( 'posts_per_page', $args ) ) {
        $posts_per_page =
            dilitant_land_normalize_blog_positive_integer(
                $args['posts_per_page']
            );

        if (
            null === $posts_per_page
            || $posts_per_page >
                DILITANT_LAND_BLOG_MAX_POSTS_PER_PAGE
        ) {
            return null;
        }

        $normalized['posts_per_page'] =
            $posts_per_page;
    }


    /*
     * Номер страницы.
     */
    if ( array_key_exists( 'paged', $args ) ) {
        $paged =
            dilitant_land_normalize_blog_positive_integer(
                $args['paged']
            );

        if ( null === $paged ) {
            return null;
        }

        $normalized['paged'] = $paged;
    }


    /*
     * Предотвращаем целочисленное переполнение
     * при последующем вычислении SQL OFFSET:
     *
     *     (paged - 1) * posts_per_page
     *
     * Проверка выполняется до умножения.
     */
    if (
        $normalized['paged'] - 1 >
        intdiv(
            PHP_INT_MAX,
            $normalized['posts_per_page']
        )
    ) {
        return null;
    }


    /*
     * Возвращаем только проверенные
     * параметры пагинации.
     */
    return $normalized;
}