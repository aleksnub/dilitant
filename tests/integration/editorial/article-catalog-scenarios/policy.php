<?php
/**
 * AM-CQ-01 — применение обязательной
 * политики Article Model к главному
 * запросу публичного каталога.
 *
 * EDC-01 / AM-D02.
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
 * Проверяет устранение конфликтующих
 * аргументов, нормативную сортировку
 * и сохранение состава каталога.
 */
function dilitant_catalog_test_cq01(
    DilitantArticleFixtures $fixtures
): void {
    $query = dilitant_catalog_observe_query(
        [
            'category_name' =>
                DILITANT_LAND_BLOG_CATEGORY_SLUG,

            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'ignore_sticky_posts' => false,
            'has_password' => true,
            'post_status' => 'any',

            'post__not_in' => [
                $fixtures->root_post,
            ],
        ],
        true,
        true
    );

    dilitant_catalog_assert_full_set(
        $query,
        $fixtures
    );

    dilitant_test_assert_same(
        [
            'date' => 'DESC',
            'ID' => 'DESC',
        ],
        $query->get('orderby'),
        'Catalog did not apply normative ordering.'
    );

    dilitant_test_assert_same(
        'DESC',
        $query->get('order'),
        'Catalog ordering direction is incorrect.'
    );

    dilitant_test_assert_same(
        dilitant_catalog_expected_order(
            $fixtures
        ),
        dilitant_query_test_ordered_ids(
            $query
        ),
        'Catalog returned incorrect article order.'
    );

    dilitant_test_assert_same(
        100,
        (int) $query->get('posts_per_page'),
        'Caller changed normative catalog page size.'
    );

    dilitant_test_assert(
        empty($query->get('post__not_in')),
        'Forbidden catalog exclusion remained.'
    );


    /*
     * Идентификация корневой категории
     * через стандартный числовой ID.
     */
    $query = dilitant_catalog_observe_query(
        [
            'cat' => $fixtures->root_category,
        ],
        true,
        true
    );

    dilitant_catalog_assert_full_set(
        $query,
        $fixtures
    );


    /*
     * Дополнительные ненормативные
     * аргументы WP_Query.
     */
    $query = dilitant_catalog_execute([
        'offset' => 500,
        'nopaging' => true,
        'fields' => 'ids',
        'no_found_rows' => true,
        'posts_per_archive_page' => -1,
        'showposts' => -1,

        'meta_query' => [
            [
                'key' => '_am_nonexistent_meta',
                'compare' => 'EXISTS',
            ],
        ],
    ]);

    dilitant_catalog_assert_full_set(
        $query,
        $fixtures
    );

    dilitant_test_assert_same(
        100,
        (int) $query->get('posts_per_page'),
        'Forbidden arguments changed catalog page size.'
    );

    dilitant_test_assert(
        !$query->get('no_found_rows'),
        'Caller disabled catalog result counting.'
    );
}