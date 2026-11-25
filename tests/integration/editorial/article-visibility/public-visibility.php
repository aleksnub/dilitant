<?php
/**
 * AM-T09.1–AM-T09.9.
 *
 * Проверка нормативной публичной
 * доступности Article Model.
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
 * AM-T09.1 — опубликованная статья.
 *
 * Проверяются все уровни
 * нормативного дерева категорий.
 */
function dilitant_visibility_test_t09_1(
    DilitantArticleFixtures $fixtures
): void {
    dilitant_visibility_with_user(
        0,

        static function () use (
            $fixtures
        ): void {
            foreach (
                [
                    $fixtures->root_post,
                    $fixtures->child_post,
                    $fixtures->grandchild_post,
                ]
                as $id
            ) {
                dilitant_visibility_assert_post(
                    $id,
                    true,
                    true,
                    'publish'
                );

                $post =
                    dilitant_visibility_get_post($id);

                dilitant_test_assert_same(
                    '',
                    $post->post_password,
                    'Public fixture has a password.'
                );

                dilitant_test_assert_same(
                    true,
                    dilitant_land_can_render_blog_article(
                        $post
                    ),
                    'Public article cannot be rendered.'
                );
            }

            dilitant_visibility_assert_public_query(
                $fixtures
            );
        }
    );
}


/**
 * AM-T09.2 — черновик.
 */
function dilitant_visibility_test_t09_2(
    DilitantArticleFixtures $fixtures
): void {
    dilitant_visibility_with_user(
        0,

        static function () use (
            $fixtures
        ): void {
            dilitant_visibility_assert_post(
                $fixtures->draft_post,
                true,
                false,
                'draft'
            );

            dilitant_visibility_assert_excluded(
                $fixtures->draft_post
            );
        }
    );
}


/**
 * AM-T09.3 — приватная статья.
 */
function dilitant_visibility_test_t09_3(
    DilitantArticleFixtures $fixtures
): void {
    dilitant_visibility_with_user(
        0,

        static function () use (
            $fixtures
        ): void {
            dilitant_visibility_assert_post(
                $fixtures->private_post,
                true,
                false,
                'private'
            );

            dilitant_visibility_assert_excluded(
                $fixtures->private_post
            );
        }
    );
}


/**
 * AM-T09.4 — будущая публикация.
 */
function dilitant_visibility_test_t09_4(
    DilitantArticleFixtures $fixtures
): void {
    dilitant_visibility_with_user(
        0,

        static function () use (
            $fixtures
        ): void {
            dilitant_visibility_assert_post(
                $fixtures->future_post,
                true,
                false,
                'future'
            );

            $post =
                dilitant_visibility_get_post(
                    $fixtures->future_post
                );

            $timestamp = strtotime(
                $post->post_date_gmt . ' UTC'
            );

            dilitant_test_assert(
                $timestamp !== false
                && $timestamp > time(),
                'Future fixture is not scheduled.'
            );

            dilitant_visibility_assert_excluded(
                $fixtures->future_post
            );
        }
    );
}


/**
 * AM-T09.5 — статья в корзине.
 *
 * Принадлежность категории сохраняется.
 * Публичная доступность запрещена.
 */
function dilitant_visibility_test_t09_5(
    DilitantArticleFixtures $fixtures
): void {
    dilitant_visibility_with_user(
        0,

        static function () use (
            $fixtures
        ): void {
            dilitant_visibility_with_trashed_post(
                $fixtures,
                $fixtures->root_post,

                static function (
                    WP_Post $post
                ) use ($fixtures): void {
                    dilitant_visibility_assert_post(
                        (int) $post->ID,
                        true,
                        false,
                        'trash'
                    );

                    dilitant_visibility_assert_excluded(
                        (int) $post->ID
                    );
                }
            );

            /*
             * После восстановления статуса
             * статья снова публично доступна.
             */
            dilitant_visibility_assert_post(
                $fixtures->root_post,
                true,
                true,
                'publish'
            );

            dilitant_visibility_assert_public_query(
                $fixtures
            );
        }
    );
}


/**
 * AM-T09.6 — запись другого раздела.
 *
 * Дополнительно проверяется страница,
 * которой назначена категория блога.
 */
function dilitant_visibility_test_t09_6(
    DilitantArticleFixtures $fixtures
): void {
    dilitant_visibility_assert_post(
        $fixtures->outside_post,
        false,
        false,
        'publish'
    );

    dilitant_visibility_assert_excluded(
        $fixtures->outside_post
    );

    dilitant_visibility_assert_post(
        $fixtures->page_post,
        false,
        false,
        'publish',
        'page'
    );

    dilitant_visibility_assert_excluded(
        $fixtures->page_post
    );


    /*
     * Обработчик Article Model не должен
     * блокировать обычную запись сайта.
     */
    dilitant_visibility_with_user(
        0,

        static function () use (
            $fixtures
        ): void {
            dilitant_visibility_with_query(
                [
                    'p' =>
                        $fixtures->outside_post,

                    'post_type' => 'post',
                ],

                static function (
                    WP_Query $query
                ) use ($fixtures): void {
                    dilitant_test_assert_same(
                        $fixtures->outside_post,
                        (int) $query->get_queried_object_id(),
                        'Outside post query identity is incorrect.'
                    );

                    dilitant_visibility_assert_handler_decision(
                        $query,
                        false
                    );
                }
            );
        }
    );
}


/**
 * AM-T09.7 — архивная фотография.
 *
 * Наличие категории блога не превращает
 * пользовательский тип записи в статью.
 */
function dilitant_visibility_test_t09_7(
    DilitantArticleFixtures $fixtures
): void {
    dilitant_visibility_assert_post(
        $fixtures->archive_post,
        false,
        false,
        'publish',
        $fixtures->archive_post_type()
    );

    dilitant_visibility_assert_excluded(
        $fixtures->archive_post
    );

    dilitant_visibility_with_user(
        0,

        static function () use (
            $fixtures
        ): void {
            dilitant_visibility_with_query(
                [
                    'p' =>
                        $fixtures->archive_post,

                    'post_type' =>
                        $fixtures->archive_post_type(),
                ],

                static function (
                    WP_Query $query
                ) use ($fixtures): void {
                    dilitant_test_assert_same(
                        $fixtures->archive_post,
                        (int) $query->get_queried_object_id(),
                        'Archive photo query identity is incorrect.'
                    );

                    dilitant_visibility_assert_handler_decision(
                        $query,
                        false
                    );
                }
            );
        }
    );
}


/**
 * AM-T09.8 — несуществующая запись.
 */
function dilitant_visibility_test_t09_8(
    DilitantArticleFixtures $fixtures
): void {
    /*
     * Выбираем положительный ID,
     * отсутствующий в тестовой БД.
     *
     * Не используем 0 или null,
     * поскольку get_post() может
     * разрешить их как текущую запись.
     */
    $missing_id = PHP_INT_MAX;

    dilitant_test_assert_same(
        null,
        get_post($missing_id),
        'Missing fixture ID unexpectedly exists.'
    );

    dilitant_visibility_assert_identity(
        $missing_id,
        false,
        false
    );

    dilitant_visibility_assert_excluded(
        $missing_id
    );
}


/**
 * AM-T09.9 — отсутствие корневой категории.
 *
 * Выполняется последним во всём наборе.
 */
function dilitant_visibility_test_t09_9(
    DilitantArticleFixtures $fixtures
): void {
    $all_post_ids =
        $fixtures->all_post_ids();

    $fixtures->remove_root_category();

    dilitant_test_assert_same(
        0,
        dilitant_land_get_blog_category_id(),
        'Removed root category remains resolvable.'
    );

    dilitant_test_assert_same(
        [],
        dilitant_land_get_blog_category_ids(),
        'Normative category tree survived root removal.'
    );


    /*
     * Физические записи сохраняются,
     * но принадлежность блогу больше
     * не может быть подтверждена.
     */
    foreach ($all_post_ids as $id) {
        $post =
            dilitant_visibility_get_post($id);

        dilitant_visibility_assert_identity(
            $post,
            false,
            false
        );
    }

    $query =
        dilitant_land_get_blog_articles([
            'post__in' => $all_post_ids,
        ]);

    dilitant_test_assert(
        $query instanceof WP_Query,
        'Article Model did not return WP_Query.'
    );

    dilitant_test_assert_same(
        [],
        dilitant_test_query_ids($query),
        'Public selection survived root removal.'
    );

    dilitant_test_assert_same(
        [0],
        $query->get('post__in'),
        'Missing root did not produce closed failure.'
    );

    dilitant_test_assert_same(
        0,
        (int) $query->post_count,
        'Closed failure returned posts.'
    );
}