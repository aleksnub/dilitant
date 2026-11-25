<?php
/**
 * Нормализация ограничений по идентификаторам
 * публикаций Article Model.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D02 — Защита инвариантов WP_Query
 *
 * Ответственность:
 *     - нормализация post__in;
 *     - нормализация post__not_in;
 *     - исключение недопустимых идентификаторов;
 *     - разрешение конфликта включения и исключения;
 *     - сохранение порядка включённых ID;
 *     - закрытый отказ при пустом множестве включения.
 *
 * Зависимости:
 *     article/normalization.php
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/**
 * Нормализует ограничения по ID публикаций.
 *
 * Правила:
 *
 *     1. Все ID должны быть положительными.
 *
 *     2. Некорректный элемент делает
 *        недействительным весь список.
 *
 *     3. Пустой явно переданный post__in
 *        означает пустую выборку.
 *
 *     4. Пустой post__not_in не ограничивает
 *        выборку.
 *
 *     5. При одновременном использовании
 *        post__in и post__not_in исключение
 *        имеет преимущество.
 *
 *     6. Порядок оставшихся post__in
 *        сохраняется.
 *
 *     7. Повторяющиеся идентификаторы
 *        удаляются общим нормализатором.
 *
 * Наличие ID в списке post__in само по себе
 * не подтверждает принадлежность записи блогу.
 *
 * Обязательные ограничения категорий,
 * типа, статуса и парольной защиты
 * устанавливаются компонентом query.php.
 *
 * @internal
 *
 * @param array $args Исходные аргументы.
 *
 * @return array|null
 */
function dilitant_land_normalize_blog_post_id_args(
    $args
) {
    $normalized = array();


    /*
     * Список включённых публикаций.
     */
    if ( array_key_exists( 'post__in', $args ) ) {
        $included_ids =
            dilitant_land_normalize_blog_id_list(
                $args['post__in']
            );

        /*
         * Пустой post__in в WordPress
         * может означать отсутствие ограничения.
         *
         * Поэтому пустой список нельзя
         * передавать непосредственно в WP_Query.
         */
        if (
            null === $included_ids
            || empty( $included_ids )
        ) {
            return null;
        }

        $normalized['post__in'] =
            $included_ids;
    }


    /*
     * Список исключённых публикаций.
     */
    if ( array_key_exists( 'post__not_in', $args ) ) {
        $excluded_ids =
            dilitant_land_normalize_blog_id_list(
                $args['post__not_in']
            );

        if ( null === $excluded_ids ) {
            return null;
        }

        /*
         * Пустой список исключения допустим
         * и не формирует ограничений.
         */
        if ( ! empty( $excluded_ids ) ) {
            $normalized['post__not_in'] =
                $excluded_ids;
        }
    }


    /*
     * Разрешаем конфликт включения и исключения.
     *
     * WordPress не гарантирует одновременное
     * применение post__in и post__not_in.
     *
     * Исключаем запрещённые ID самостоятельно,
     * до формирования WP_Query.
     */
    if (
        isset( $normalized['post__in'] )
        && isset( $normalized['post__not_in'] )
    ) {
        $normalized['post__in'] = array_values(
            array_diff(
                $normalized['post__in'],
                $normalized['post__not_in']
            )
        );


        /*
         * Исключения уже применены.
         *
         * Передавать оба аргумента
         * в WP_Query не требуется.
         */
        unset(
            $normalized['post__not_in']
        );


        /*
         * Если исключены все разрешённые ID,
         * возвращаем закрытый отказ.
         */
        if ( empty( $normalized['post__in'] ) ) {
            return null;
        }
    }


    /*
     * Возвращаем только проверенные
     * ограничения по идентификаторам.
     */
    return $normalized;
}