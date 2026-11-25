<?php
/**
 * AM-CQ-07 — изоляция запросов
 * отдельных статей.
 *
 * EDC-01 / AM-D02.
 *
 * Политика публичного каталога не должна
 * преобразовывать запрос отдельной статьи
 * в архивную выборку.
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
 * AM-CQ-07.
 */
function dilitant_catalog_test_cq07(
    DilitantArticleFixtures $fixtures
): void {
    $post = get_post(
        $fixtures->root_post
    );

    dilitant_test_assert(
        $post instanceof WP_Post,
        'Singular article fixture is missing.'
    );


    /*
     * Аргументы специального маршрута
     * отдельной статьи.
     */
    $query = dilitant_catalog_observe_query(
        [
            'category_name' =>
                DILITANT_LAND_BLOG_CATEGORY_SLUG,

            'name' =>
                $post->post_name,
        ],
        true,
        false
    );

    dilitant_test_assert_same(
        true,
        $query->is_single(),
        'Article query lost singular identity.'
    );

    dilitant_test_assert_same(
        true,
        $query->is_singular(),
        'Article is no longer singular.'
    );

    dilitant_query_test_assert_ids(
        $query,
        [
            $fixtures->root_post,
        ],
        'Singular article result was modified.'
    );


    /*
     * Получение записи по её ID.
     */
    $query = dilitant_catalog_observe_query(
        [
            'p' =>
                $fixtures->root_post,

            'post_type' => 'post',
        ],
        true,
        false
    );

    dilitant_query_test_assert_ids(
        $query,
        [
            $fixtures->root_post,
        ],
        'Direct singular article query was modified.'
    );


    /*
     * Отдельная статья дочерней категории.
     */
    $child = get_post(
        $fixtures->child_post
    );

    dilitant_test_assert(
        $child instanceof WP_Post,
        'Child article fixture is missing.'
    );

    $query = dilitant_catalog_observe_query(
        [
            'category_name' =>
                DILITANT_LAND_BLOG_CATEGORY_SLUG,

            'name' =>
                $child->post_name,
        ],
        true,
        false
    );

    dilitant_test_assert_same(
        true,
        $query->is_single(),
        'Child article lost singular identity.'
    );

    dilitant_query_test_assert_ids(
        $query,
        [
            $fixtures->child_post,
        ],
        'Child article query was modified.'
    );
}