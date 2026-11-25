<?php
/**
 * Нормализация параметров сортировки
 * публичной выборки статей.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D02 — Защита инвариантов WP_Query
 *     AM-D05 — Пагинация каталога
 *
 * Ответственность:
 *     - проверка направления сортировки;
 *     - проверка допустимости orderby;
 *     - проверка зависимости от post__in;
 *     - проверка зависимости от поискового запроса;
 *     - формирование дополнительного критерия ID;
 *     - предотвращение передачи произвольных
 *       критериев сортировки в WP_Query.
 *
 * Зависимости:
 *     article/query-policy.php
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/**
 * Нормализует параметры сортировки.
 *
 * Допустимые значения orderby определяются
 * компонентом query-policy.php.
 *
 * Для неуникальных основных критериев
 * применяется дополнительная сортировка по ID.
 *
 * Сортировка post__in использует порядок
 * ранее нормализованного списка идентификаторов.
 *
 * Сортировка relevance допускается только
 * при наличии непустого поискового запроса.
 *
 * @internal
 *
 * @param array $args       Исходные аргументы.
 * @param array $normalized Ранее проверенные параметры.
 *
 * @return array|null
 */
function dilitant_land_normalize_blog_order_args(
    $args,
    $normalized
) {
    /*
     * 1. Направление сортировки.
     */
    $order = 'DESC';

    if ( array_key_exists( 'order', $args ) ) {
        if ( ! is_string( $args['order'] ) ) {
            return null;
        }

        $order = strtoupper(
            $args['order']
        );

        if (
            ! in_array(
                $order,
                array( 'ASC', 'DESC' ),
                true
            )
        ) {
            return null;
        }
    }


    /*
     * 2. Основной критерий сортировки.
     */
    $orderby = 'date';

    if ( array_key_exists( 'orderby', $args ) ) {
        if ( ! is_string( $args['orderby'] ) ) {
            return null;
        }

        $orderby = $args['orderby'];

        if (
            ! in_array(
                $orderby,
                dilitant_land_get_blog_allowed_orderby(),
                true
            )
        ) {
            return null;
        }
    }


    /*
     * 3. Сортировка по порядку post__in.
     *
     * WordPress сохраняет порядок переданных
     * идентификаторов независимо от общего
     * параметра order.
     */
    if ( 'post__in' === $orderby ) {
        if ( empty( $normalized['post__in'] ) ) {
            return null;
        }

        return array(
            'orderby' => 'post__in',
            'order'   => $order,
        );
    }


    /*
     * 4. Сортировка по поисковой релевантности.
     *
     * Допускается только при наличии ранее
     * нормализованного непустого запроса.
     *
     * Порядок релевантности определяется
     * механизмом поиска WordPress.
     */
    if ( 'relevance' === $orderby ) {
        if (
            ! isset( $normalized['s'] )
            || '' === $normalized['s']
        ) {
            return null;
        }

        return array(
            'orderby' => 'relevance',
            'order'   => $order,
        );
    }


    /*
     * 5. Сортировка по уникальному ID.
     *
     * Дополнительный критерий не требуется.
     */
    if ( 'ID' === $orderby ) {
        return array(
            'orderby' => array(
                'ID' => $order,
            ),
            'order' => $order,
        );
    }


    /*
     * 6. Сортировка по обычным критериям:
     *
     *     date
     *     modified
     *     title
     *
     * Добавляем уникальный вторичный
     * критерий ID.
     *
     * Это обеспечивает детерминированный
     * порядок публикаций при совпадении
     * значений основного критерия.
     */
    return array(
        'orderby' => array(
            $orderby => $order,
            'ID'     => $order,
        ),
        'order' => $order,
    );
}