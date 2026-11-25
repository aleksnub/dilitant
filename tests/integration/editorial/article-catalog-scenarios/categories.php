<?php
/**
 * AM-CQ-04 — полнота нормативного
 * дерева категорий публичного каталога.
 *
 * EDC-01 / AM-D02.
 *
 * Проверяет включение корневой категории
 * и всех её потомков, а также исключение
 * посторонних категорий и типов записей.
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
 * AM-CQ-04.
 */
function dilitant_catalog_test_cq04(
    DilitantArticleFixtures $fixtures
): void {
    $query = dilitant_catalog_execute();

    dilitant_catalog_assert_full_set(
        $query,
        $fixtures
    );

    $actual_ids =
        dilitant_test_query_ids($query);

    $expected = [
        $fixtures->root_post,
        $fixtures->child_post,
        $fixtures->grandchild_post,
    ];

    foreach ($expected as $id) {
        dilitant_test_assert(
            in_array(
                $id,
                $actual_ids,
                true
            ),
            "Catalog lost normative article: {$id}"
        );
    }

    dilitant_test_assert(
        !in_array(
            $fixtures->outside_post,
            $actual_ids,
            true
        ),
        'Outside article entered catalog.'
    );


    /*
     * Попытка исключить нормативное
     * дерево и включить постороннюю
     * категорию через tax_query.
     */
    $query = dilitant_catalog_execute([
        'category__not_in' =>
            $fixtures->expected_category_ids(),

        'tax_query' => [
            [
                'taxonomy' => 'category',
                'field' => 'term_id',

                'terms' => [
                    $fixtures->outside_category,
                ],

                'operator' => 'IN',
            ],
        ],
    ]);

    dilitant_catalog_assert_full_set(
        $query,
        $fixtures
    );


    /*
     * Попытка расширить выборку
     * через альтернативные ограничения.
     */
    $query = dilitant_catalog_execute([
        'category__in' => [
            $fixtures->outside_category,
        ],

        'post__in' => [
            $fixtures->outside_post,
            $fixtures->archive_post,
            $fixtures->page_post,
        ],
    ]);

    dilitant_catalog_assert_full_set(
        $query,
        $fixtures
    );
}