<?php
/**
 * AM-CQ-10 — закрытый отказ при отсутствии
 * корневой категории Article Model.
 *
 * EDC-01 / AM-D02.
 *
 * Проверяет согласованность закрытого
 * отказа главного запроса каталога
 * и непосредственного интерфейса модели.
 *
 * Сценарий изменяет тестовое дерево
 * категорий и выполняется последним.
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
 * AM-CQ-10.
 */
function dilitant_catalog_test_cq10(
    DilitantArticleFixtures $fixtures
): void {
    $all_post_ids =
        $fixtures->all_post_ids();


    /*
     * Удаляется только корневая категория,
     * принадлежащая текущим Fixtures.
     */
    $fixtures->remove_root_category();

    dilitant_test_assert_same(
        0,
        dilitant_land_get_blog_category_id(),
        'Deleted catalog root remains resolvable.'
    );

    dilitant_test_assert_same(
        [],
        dilitant_land_get_blog_category_ids(),
        'Catalog tree survived root removal.'
    );


    /*
     * Удаление категории не должно
     * удалять связанные публикации.
     */
    foreach ($all_post_ids as $id) {
        dilitant_test_assert(
            get_post($id) instanceof WP_Post,
            "Post disappeared after root removal: {$id}"
        );
    }


    /*
     * Закрытый отказ главного запроса.
     */
    $query = dilitant_catalog_observe_query(
        [
            'category_name' =>
                DILITANT_LAND_BLOG_CATEGORY_SLUG,
        ],
        true,
        true
    );

    dilitant_query_test_assert_closed_failure(
        $query
    );


    /*
     * Явное включение существующих записей
     * не должно обходить закрытый отказ.
     */
    $query = dilitant_catalog_execute([
        'post__in' =>
            $all_post_ids,

        'post_status' => 'any',
        'has_password' => true,
    ]);

    dilitant_query_test_assert_closed_failure(
        $query
    );


    /*
     * Согласованность с непосредственным
     * публичным интерфейсом Article Model.
     */
    $direct =
        dilitant_land_get_blog_articles([
            'post__in' =>
                $all_post_ids,
        ]);

    dilitant_query_test_assert_closed_failure(
        $direct
    );
}