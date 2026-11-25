<?php
/**
 * AM-CQ-08 — изоляция запросов фотоархива.
 *
 * EDC-01 / AM-D02.
 *
 * Проверяет невмешательство в главные,
 * вторичные и сингулярные запросы
 * архивных фотографий.
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
 * AM-CQ-08.
 */
function dilitant_catalog_test_cq08(
    DilitantArticleFixtures $fixtures
): void {
    $archive_type =
        $fixtures->archive_post_type();


    /*
     * Главная выборка фотоархива.
     */
    $query = dilitant_catalog_observe_query(
        [
            'post_type' =>
                $archive_type,

            'post_status' => 'publish',
            'posts_per_page' => 100,
        ],
        true,
        false
    );

    dilitant_query_test_assert_ids(
        $query,
        [
            $fixtures->archive_post,
        ],
        'Archive photo selection was modified.'
    );

    dilitant_test_assert_same(
        $archive_type,
        $query->get('post_type'),
        'Archive photo post type was replaced.'
    );


    /*
     * Вторичная выборка фотоархива.
     */
    $query = dilitant_catalog_observe_query(
        [
            'post_type' =>
                $archive_type,

            'post_status' => 'publish',
            'posts_per_page' => 100,

            'post__in' => [
                $fixtures->archive_post,
            ],
        ],
        false,
        false
    );

    dilitant_query_test_assert_ids(
        $query,
        [
            $fixtures->archive_post,
        ],
        'Secondary archive photo query was modified.'
    );


    /*
     * Отдельная архивная фотография.
     */
    $query = dilitant_catalog_observe_query(
        [
            'post_type' =>
                $archive_type,

            'p' =>
                $fixtures->archive_post,
        ],
        true,
        false
    );

    dilitant_query_test_assert_ids(
        $query,
        [
            $fixtures->archive_post,
        ],
        'Singular archive photo query was modified.'
    );
}