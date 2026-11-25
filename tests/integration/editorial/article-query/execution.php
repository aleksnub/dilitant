<?php
/**
 * Исполнение запросов Article Model.
 *
 * Ответственность:
 *     - вызов публичного интерфейса выборки;
 *     - формирование стандартных параметров;
 *     - проверка типа возвращаемого объекта;
 *     - получение фактического порядка записей.
 */

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    || !defined('DILITANT_TEST_DB_NAME')
    || !defined('DB_NAME')
    || DB_NAME !== DILITANT_TEST_DB_NAME
) {
    exit(1);
}


/**
 * Выполняет запрос через публичную модель.
 *
 * Стандартный размер страницы достаточен
 * для полного тестового набора.
 *
 * Переданные аргументы намеренно могут
 * содержать запрещённые параметры:
 * их обязана блокировать испытуемая модель.
 */
function dilitant_query_test_execute(
    array $args = []
): WP_Query {
    $defaults = [
        'posts_per_page' => 100,
        'orderby' => 'ID',
        'order' => 'ASC',
    ];

    $query = dilitant_land_get_blog_articles(
        array_replace(
            $defaults,
            $args
        )
    );

    dilitant_test_assert(
        $query instanceof WP_Query,
        'Article model did not return WP_Query.'
    );

    return $query;
}


/**
 * Возвращает ID в фактическом порядке
 * получения записей.
 *
 * В отличие от dilitant_test_query_ids(),
 * результат не сортируется.
 */
function dilitant_query_test_ordered_ids(
    WP_Query $query
): array {
    $ids = [];

    foreach ($query->posts as $post) {
        dilitant_test_assert(
            $post instanceof WP_Post,
            'Invalid object in ordered query.'
        );

        $ids[] = (int) $post->ID;
    }

    return $ids;
}