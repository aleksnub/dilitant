<?php
/**
 * Проверки инвариантов Article Model.
 *
 * Ответственность:
 *     - проверка обязательных аргументов;
 *     - проверка нормативного tax_query;
 *     - проверка фактического состава записей;
 *     - проверка отсутствия запрещённых объектов;
 *     - проверка закрытого отказа.
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
 * Проверяет обязательные параметры запроса.
 *
 * Используется для обычных и пустых выборок.
 */
function dilitant_query_test_assert_mandatory_args(
    WP_Query $query
): void {
    $expected = [
        'post_type' => 'post',
        'post_status' => 'publish',
        'has_password' => false,
        'ignore_sticky_posts' => true,
    ];

    foreach ($expected as $key => $value) {
        dilitant_test_assert_same(
            $value,
            $query->get($key),
            "Mandatory query argument violated: {$key}"
        );
    }
}


/**
 * Проверяет нормативное ограничение категорий.
 */
function dilitant_query_test_assert_taxonomy(
    WP_Query $query,
    array $expected_category_ids
): void {
    dilitant_test_assert(
        $expected_category_ids !== [],
        'Expected category set must not be empty.'
    );

    $tax_query = $query->get('tax_query');

    dilitant_test_assert(
        is_array($tax_query),
        'Query taxonomy constraint is missing.'
    );

    dilitant_test_assert_same(
        1,
        count($tax_query),
        'Unexpected number of taxonomy clauses.'
    );

    $clause = $tax_query[0] ?? null;

    dilitant_test_assert(
        is_array($clause),
        'Invalid taxonomy clause.'
    );

    $expected = [
        'taxonomy' => 'category',
        'field' => 'term_id',
        'include_children' => false,
        'operator' => 'IN',
    ];

    foreach ($expected as $key => $value) {
        dilitant_test_assert_same(
            $value,
            $clause[$key] ?? null,
            "Taxonomy query argument violated: {$key}"
        );
    }

    $actual_ids = $clause['terms'] ?? null;

    dilitant_test_assert(
        is_array($actual_ids),
        'Query taxonomy terms are missing.'
    );

    dilitant_test_assert_same(
        dilitant_test_normalize_ids(
            $expected_category_ids
        ),
        dilitant_test_normalize_ids(
            $actual_ids
        ),
        'Unexpected query category set.'
    );
}


/**
 * Проверяет фактическую запись,
 * возвращённую публичной выборкой.
 */
function dilitant_query_test_assert_public_post(
    WP_Post $post,
    array $expected_category_ids
): void {
    dilitant_test_assert_same(
        'post',
        $post->post_type,
        'Query returned forbidden post type.'
    );

    dilitant_test_assert_same(
        'publish',
        $post->post_status,
        'Query returned unpublished post.'
    );

    dilitant_test_assert_same(
        '',
        $post->post_password,
        'Query returned password-protected post.'
    );

    dilitant_test_assert_same(
        true,
        dilitant_land_is_blog_article($post),
        'Query returned non-blog post.'
    );


    /*
     * Независимая проверка категорий.
     */
    $post_categories = wp_get_post_categories(
        $post->ID,
        [
            'fields' => 'ids',
        ]
    );

    if (is_wp_error($post_categories)) {
        throw new RuntimeException(
            'Unable to resolve returned post categories.'
        );
    }

    $intersection = array_intersect(
        $expected_category_ids,
        dilitant_test_normalize_ids(
            $post_categories
        )
    );

    dilitant_test_assert(
        !empty($intersection),
        'Query returned post outside permitted categories.'
    );
}


/**
 * Проверяет полный набор инвариантов
 * публичной выборки.
 */
function dilitant_query_test_assert_invariants(
    WP_Query $query,
    DilitantArticleFixtures $fixtures,
    array $expected_category_ids
): void {
    dilitant_query_test_assert_mandatory_args(
        $query
    );

    dilitant_query_test_assert_taxonomy(
        $query,
        $expected_category_ids
    );


    /*
     * Проверяем отсутствие запрещённых ID.
     */
    $actual_ids = dilitant_test_query_ids(
        $query
    );

    $forbidden_ids =
        $fixtures->excluded_post_ids();

    dilitant_test_assert_same(
        [],
        array_values(
            array_intersect(
                $actual_ids,
                $forbidden_ids
            )
        ),
        'Forbidden records entered public article query.'
    );


    /*
     * Проверяем каждый фактический объект.
     */
    foreach ($query->posts as $post) {
        dilitant_test_assert(
            $post instanceof WP_Post,
            'Query returned invalid object.'
        );

        dilitant_query_test_assert_public_post(
            $post,
            $expected_category_ids
        );
    }
}


/**
 * Проверяет точное множество результатов.
 *
 * Дубликаты не устраняются нормализатором
 * тестовых результатов и приводят к ошибке.
 */
function dilitant_query_test_assert_ids(
    WP_Query $query,
    array $expected_ids,
    string $message
): void {
    dilitant_test_assert_same(
        dilitant_test_normalize_ids(
            $expected_ids
        ),
        dilitant_test_query_ids($query),
        $message
    );

    dilitant_test_assert_same(
        count($expected_ids),
        (int) $query->post_count,
        'Unexpected query post_count.'
    );
}


/**
 * Проверяет стандартную публичную выборку
 * после попытки изменить её условия.
 */
function dilitant_query_test_assert_public_set(
    DilitantArticleFixtures $fixtures,
    array $args
): void {
    $query = dilitant_query_test_execute(
        $args
    );

    dilitant_query_test_assert_invariants(
        $query,
        $fixtures,
        $fixtures->expected_category_ids()
    );

    dilitant_query_test_assert_ids(
        $query,
        $fixtures->expected_published_post_ids(),
        'Unexpected public article set.'
    );
}


/**
 * Проверяет выборку конкретного
 * допустимого подмножества записей.
 *
 * Если множество категорий не указано,
 * используется полное дерево блога.
 */
function dilitant_query_test_assert_selection(
    DilitantArticleFixtures $fixtures,
    array $args,
    array $expected_ids,
    ?array $expected_category_ids = null
): WP_Query {
    $query = dilitant_query_test_execute(
        $args
    );

    $category_ids =
        $expected_category_ids
        ?? $fixtures->expected_category_ids();

    dilitant_query_test_assert_invariants(
        $query,
        $fixtures,
        $category_ids
    );

    dilitant_query_test_assert_ids(
        $query,
        $expected_ids,
        'Unexpected article selection.'
    );

    return $query;
}


/**
 * Проверяет пустую выборку.
 *
 * Используется для закрытого отказа
 * и корректных запросов без результатов.
 *
 * При выходе за пределы существующих страниц
 * found_posts может иметь другую семантику,
 * поэтому такой случай проверяется отдельно.
 */
function dilitant_query_test_assert_empty(
    WP_Query $query
): void {
    dilitant_query_test_assert_mandatory_args(
        $query
    );

    dilitant_test_assert_same(
        [],
        dilitant_test_query_ids($query),
        'Expected an empty query.'
    );

    dilitant_test_assert_same(
        0,
        (int) $query->post_count,
        'Empty query has nonzero post_count.'
    );

    dilitant_test_assert_same(
        0,
        (int) $query->found_posts,
        'Empty query has nonzero found_posts.'
    );

    dilitant_test_assert_same(
        0,
        (int) $query->max_num_pages,
        'Empty query has nonzero max_num_pages.'
    );
}


/**
 * Проверяет механизм закрытого отказа
 * при некорректном входном значении.
 *
 * В отличие от обычного запроса без результатов
 * ожидается специальный запрос с post__in = [0].
 */
function dilitant_query_test_assert_closed_failure(
    WP_Query $query
): void {
    dilitant_query_test_assert_empty(
        $query
    );

    dilitant_test_assert_same(
        [0],
        $query->get('post__in'),
        'Closed failure did not use impossible post ID.'
    );

    dilitant_test_assert_same(
        true,
        $query->get('no_found_rows'),
        'Closed failure did not disable result counting.'
    );
}