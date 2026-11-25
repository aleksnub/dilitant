<?php
/**
 * AM-CQ-05 — административная изоляция.
 * AM-CQ-06 — изоляция посторонних запросов.
 *
 * EDC-01 / AM-D02.
 *
 * Нормативная политика каталога не должна
 * изменять запросы вне своей ответственности.
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
 * AM-CQ-05.
 *
 * Проверяет невмешательство
 * в административные запросы.
 */
function dilitant_catalog_test_cq05(
    DilitantArticleFixtures $fixtures
): void {
    $state =
        dilitant_catalog_capture_globals();

    try {
        /*
         * Моделирование административного
         * экрана в CLI без изменения WP_ADMIN.
         */
        $GLOBALS['current_screen'] =
            new class {
                public $id = 'edit-post';

                public $base = 'edit';

                public $post_type = 'post';

                public function in_admin(
                    $admin = null
                ): bool {
                    return $admin === null
                        || $admin === 'site';
                }
            };

        dilitant_test_assert_same(
            true,
            is_admin(),
            'Administrative context was not established.'
        );

        $query = dilitant_catalog_observe_query(
            [
                'category_name' =>
                    DILITANT_LAND_BLOG_CATEGORY_SLUG,

                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => 2,
                'orderby' => 'title',
                'order' => 'ASC',
            ],
            true,
            false
        );

        dilitant_test_assert_same(
            2,
            (int) $query->get('posts_per_page'),
            'Administrative page size was modified.'
        );

        dilitant_test_assert_same(
            'title',
            $query->get('orderby'),
            'Administrative ordering was modified.'
        );

        dilitant_test_assert_same(
            'ASC',
            $query->get('order'),
            'Administrative order direction was modified.'
        );

    } finally {
        dilitant_catalog_restore_globals(
            $state
        );

        dilitant_catalog_verify_globals(
            $state
        );
    }

    dilitant_test_assert_same(
        false,
        is_admin(),
        'Administrative context was not restored.'
    );
}


/**
 * AM-CQ-06.
 *
 * Проверяет невмешательство
 * во вторичные запросы, архивы
 * посторонних категорий, поиск и RSS.
 */
function dilitant_catalog_test_cq06(
    DilitantArticleFixtures $fixtures
): void {
    /*
     * Вторичный запрос корневой категории.
     */
    $query = dilitant_catalog_observe_query(
        [
            'category_name' =>
                DILITANT_LAND_BLOG_CATEGORY_SLUG,

            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'title',
            'order' => 'ASC',
        ],
        false,
        false
    );

    dilitant_test_assert_same(
        1,
        (int) $query->get('posts_per_page'),
        'Secondary query page size was modified.'
    );

    dilitant_test_assert_same(
        'title',
        $query->get('orderby'),
        'Secondary query ordering was modified.'
    );


    /*
     * Главный архив посторонней категории.
     */
    $query = dilitant_catalog_observe_query(
        [
            'cat' =>
                $fixtures->outside_category,

            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 100,
        ],
        true,
        false
    );

    dilitant_query_test_assert_ids(
        $query,
        [
            $fixtures->outside_post,
        ],
        'Outside category archive was modified.'
    );

    $outside_term =
        $query->get_queried_object();

    dilitant_test_assert(
        $outside_term instanceof WP_Term,
        'Outside category identity is missing.'
    );

    dilitant_test_assert_same(
        $fixtures->outside_category,
        (int) $outside_term->term_id,
        'Outside category identity was replaced.'
    );


    /*
     * Глобальный поиск сайта.
     */
    $query = dilitant_catalog_observe_query(
        [
            's' => $fixtures->run_id(),
            'post_type' => 'post',
            'posts_per_page' => 100,
        ],
        true,
        false
    );

    dilitant_test_assert_same(
        true,
        $query->is_search(),
        'Global search lost its identity.'
    );


    /*
     * Поиск внутри категории блога.
     */
    $query = dilitant_catalog_observe_query(
        [
            'category_name' =>
                DILITANT_LAND_BLOG_CATEGORY_SLUG,

            's' => $fixtures->run_id(),
            'post_type' => 'post',
        ],
        true,
        false
    );

    dilitant_test_assert_same(
        true,
        $query->is_search(),
        'Category search lost its identity.'
    );


    /*
     * RSS корневой категории.
     */
    $query = dilitant_catalog_observe_query(
        [
            'category_name' =>
                DILITANT_LAND_BLOG_CATEGORY_SLUG,

            'feed' => 'rss2',
        ],
        true,
        false
    );

    dilitant_test_assert_same(
        true,
        $query->is_feed(),
        'Category feed lost its identity.'
    );


    /*
     * Штатный главный запрос записей.
     */
    $query = dilitant_catalog_observe_query(
        [
            'post_type' => 'post',
            'posts_per_page' => 2,
        ],
        true,
        false
    );

    dilitant_test_assert_same(
        2,
        (int) $query->get('posts_per_page'),
        'Unrelated main query was modified.'
    );
}