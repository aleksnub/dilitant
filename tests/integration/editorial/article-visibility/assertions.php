<?php
/**
 * Общие проверки интеграционных
 * испытаний AM-D03.
 *
 * Ответственность:
 *     - проверка идентификации статьи;
 *     - проверка публичной доступности;
 *     - проверка согласованности
 *       интерфейсов ID и WP_Post;
 *     - проверка авторизации Preview;
 *     - проверка полномочий;
 *     - проверка состава публичной выборки.
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
 * Получает существующую тестовую запись.
 */
function dilitant_visibility_get_post(
    int $post_id
): WP_Post {
    $post = get_post($post_id);

    dilitant_test_assert(
        $post instanceof WP_Post,
        "Required fixture post is missing: {$post_id}"
    );

    dilitant_test_assert_same(
        $post_id,
        (int) $post->ID,
        'Unexpected fixture post identity.'
    );

    return $post;
}


/**
 * Проверяет принадлежность записи
 * и её публичную доступность.
 *
 * Ожидаемые результаты передаются
 * непосредственно нормативным сценарием.
 */
function dilitant_visibility_assert_identity(
    $post,
    bool $expected_identity,
    bool $expected_public
): void {
    dilitant_test_assert_same(
        $expected_identity,
        dilitant_land_is_blog_article(
            $post
        ),
        'Unexpected Article Identity result.'
    );

    dilitant_test_assert_same(
        $expected_public,
        dilitant_land_is_public_blog_article(
            $post
        ),
        'Unexpected Article Public Visibility result.'
    );
}


/**
 * Проверяет семантику существующей записи
 * при передаче ID и объекта WP_Post.
 */
function dilitant_visibility_assert_post(
    int $post_id,
    bool $expected_identity,
    bool $expected_public,
    string $expected_status,
    string $expected_type = 'post'
): void {
    $post =
        dilitant_visibility_get_post(
            $post_id
        );

    dilitant_test_assert_same(
        $expected_status,
        $post->post_status,
        'Unexpected fixture post status.'
    );

    dilitant_test_assert_same(
        $expected_type,
        $post->post_type,
        'Unexpected fixture post type.'
    );


    /*
     * Проверка интерфейса через ID.
     */
    dilitant_visibility_assert_identity(
        $post_id,
        $expected_identity,
        $expected_public
    );


    /*
     * Проверка интерфейса через WP_Post.
     */
    dilitant_visibility_assert_identity(
        $post,
        $expected_identity,
        $expected_public
    );
}


/**
 * Проверяет полномочия текущего
 * пользователя относительно записи.
 */
function dilitant_visibility_assert_edit_permission(
    int $post_id,
    bool $expected
): void {
    dilitant_test_assert_same(
        $expected,
        current_user_can(
            'edit_post',
            $post_id
        ),
        'Unexpected fixture edit capability.'
    );
}


/**
 * Проверяет оба основания отображения:
 *
 *     - Preview Authorization;
 *     - Render Authorization.
 *
 * Публичная доступность проверяется
 * независимо от полномочий пользователя.
 */
function dilitant_visibility_assert_preview(
    WP_Post $post,
    bool $expected_preview,
    bool $expected_render,
    bool $expected_public
): void {
    dilitant_test_assert_same(
        $expected_public,
        dilitant_land_is_public_blog_article(
            $post
        ),
        'Preview context changed public visibility.'
    );

    dilitant_test_assert_same(
        $expected_preview,
        dilitant_land_can_preview_blog_article(
            $post
        ),
        'Unexpected Preview Authorization result.'
    );

    dilitant_test_assert_same(
        $expected_render,
        dilitant_land_can_render_blog_article(
            $post
        ),
        'Unexpected Render Authorization result.'
    );
}


/**
 * Проверяет согласованность предиката
 * публичной доступности с Article Query.
 *
 * Ожидаемое множество предоставляют
 * Fixtures, а не производственный код.
 */
function dilitant_visibility_assert_public_query(
    DilitantArticleFixtures $fixtures
): void {
    $query =
        dilitant_land_get_blog_articles([
            'posts_per_page' => 100,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

    dilitant_test_assert(
        $query instanceof WP_Query,
        'Article Model did not return WP_Query.'
    );

    dilitant_test_assert_same(
        $fixtures->expected_published_post_ids(),
        dilitant_test_query_ids($query),
        'Unexpected public article query result.'
    );

    foreach ($query->posts as $post) {
        dilitant_test_assert(
            $post instanceof WP_Post,
            'Public query contains an invalid object.'
        );

        dilitant_visibility_assert_identity(
            $post,
            true,
            true
        );
    }

    dilitant_test_assert_same(
        [],
        array_values(
            array_intersect(
                $fixtures->excluded_post_ids(),
                dilitant_test_query_ids($query)
            )
        ),
        'Public query contains excluded posts.'
    );
}


/**
 * Проверяет отсутствие записи
 * в публичной выборке при явном
 * запросе её идентификатора.
 */
function dilitant_visibility_assert_excluded(
    int $post_id
): void {
    $query =
        dilitant_land_get_blog_articles([
            'posts_per_page' => 100,

            'post__in' => [
                $post_id,
            ],
        ]);

    dilitant_test_assert(
        $query instanceof WP_Query,
        'Article Model did not return WP_Query.'
    );

    dilitant_test_assert_same(
        [],
        dilitant_test_query_ids($query),
        'Non-public record entered public selection.'
    );

    dilitant_test_assert_same(
        0,
        (int) $query->post_count,
        'Excluded post query is not empty.'
    );
}