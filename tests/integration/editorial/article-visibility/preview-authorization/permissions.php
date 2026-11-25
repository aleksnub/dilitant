<?php
/**
 * Испытания аутентификации и полномочий
 * предварительного просмотра статей.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *
 * Сценарии:
 *     AM-T11.1 — анонимный Preview;
 *     AM-T11.2 — недостаточные полномочия;
 *     AM-T11.3 — авторизованный Preview;
 *     AM-T11.4 — объектные полномочия.
 *
 * Авторизация предварительного просмотра
 * не изменяет публичную доступность записи.
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
 * AM-T11.1 — анонимный Preview.
 */
function dilitant_visibility_test_t11_1(
    DilitantArticleFixtures $fixtures
): void {
    dilitant_visibility_with_user(
        0,

        static function () use (
            $fixtures
        ): void {
            dilitant_test_assert_same(
                false,
                is_user_logged_in(),
                'Anonymous context was not established.'
            );

            $post =
                dilitant_visibility_get_post(
                    $fixtures->draft_post
                );

            dilitant_visibility_assert_edit_permission(
                (int) $post->ID,
                false
            );

            /*
             * Проверяем производственный
             * предикат независимо от SQL.
             */
            dilitant_visibility_with_post_context(
                $post,
                true,

                static function (
                    WP_Query $query
                ) use ($post): void {
                    dilitant_visibility_assert_preview(
                        $post,
                        false,
                        false,
                        false
                    );

                    dilitant_visibility_assert_handler_decision(
                        $query,
                        true
                    );
                }
            );

            /*
             * Дополнительно проверяем
             * настоящий WP_Query.
             */
            dilitant_visibility_with_query(
                [
                    'p' => (int) $post->ID,
                    'post_type' => 'post',
                    'preview' => true,
                ],

                static function (
                    WP_Query $query
                ) use ($post): void {
                    /*
                     * WordPress может самостоятельно
                     * исключить закрытую запись.
                     */
                    if ($query->posts === []) {
                        return;
                    }

                    /*
                     * Если запись разрешена запросом,
                     * Article Model обязан отказать.
                     */
                    dilitant_test_assert_same(
                        (int) $post->ID,
                        (int) $query->get_queried_object_id(),
                        'Unexpected anonymous preview object.'
                    );

                    dilitant_visibility_assert_handler_decision(
                        $query,
                        true
                    );
                }
            );
        }
    );
}


/**
 * AM-T11.2 — пользователь
 * без полномочия edit_post.
 */
function dilitant_visibility_test_t11_2(
    DilitantArticleFixtures $fixtures,
    DilitantVisibilityUsers $users
): void {
    dilitant_visibility_with_user(
        $users->subscriber,

        static function () use (
            $fixtures
        ): void {
            dilitant_test_assert_same(
                true,
                is_user_logged_in(),
                'Subscriber is not logged in.'
            );

            $post =
                dilitant_visibility_get_post(
                    $fixtures->draft_post
                );

            dilitant_visibility_assert_edit_permission(
                (int) $post->ID,
                false
            );

            dilitant_visibility_with_post_context(
                $post,
                true,

                static function (
                    WP_Query $query
                ) use ($post): void {
                    dilitant_visibility_assert_preview(
                        $post,
                        false,
                        false,
                        false
                    );

                    dilitant_visibility_assert_handler_decision(
                        $query,
                        true
                    );
                }
            );
        }
    );
}


/**
 * AM-T11.3 — авторизованный Preview.
 *
 * Проверяем положительный сценарий
 * для пользователя, имеющего право
 * редактирования записи.
 */
function dilitant_visibility_test_t11_3(
    DilitantArticleFixtures $fixtures,
    DilitantVisibilityUsers $users
): void {
    dilitant_visibility_with_user(
        $users->administrator,

        static function () use (
            $fixtures
        ): void {
            $post =
                dilitant_visibility_get_post(
                    $fixtures->draft_post
                );

            dilitant_visibility_assert_edit_permission(
                (int) $post->ID,
                true
            );


            /*
             * Контролируемый контекст.
             */
            dilitant_visibility_with_post_context(
                $post,
                true,

                static function (
                    WP_Query $query
                ) use ($post): void {
                    dilitant_visibility_assert_preview(
                        $post,
                        true,
                        true,
                        false
                    );

                    dilitant_visibility_assert_handler_decision(
                        $query,
                        false
                    );
                }
            );


            /*
             * Настоящий WP_Query.
             *
             * Проверяем, что сохранённый черновик
             * разрешается WordPress для редактора.
             */
            dilitant_visibility_with_query(
                [
                    'p' => (int) $post->ID,
                    'post_type' => 'post',
                    'preview' => true,
                ],

                static function (
                    WP_Query $query
                ) use ($post): void {
                    dilitant_test_assert_same(
                        true,
                        $query->is_preview(),
                        'Authorized query is not a preview.'
                    );

                    dilitant_test_assert_same(
                        true,
                        $query->is_singular('post'),
                        'Authorized preview is not singular.'
                    );

                    dilitant_test_assert_same(
                        (int) $post->ID,
                        (int) $query->get_queried_object_id(),
                        'Authorized preview identity is incorrect.'
                    );

                    dilitant_test_assert_same(
                        [(int) $post->ID],
                        dilitant_test_query_ids(
                            $query
                        ),
                        'Authorized preview returned unexpected posts.'
                    );

                    dilitant_visibility_assert_preview(
                        $post,
                        true,
                        true,
                        false
                    );

                    dilitant_visibility_assert_handler_decision(
                        $query,
                        false
                    );
                }
            );


            /*
             * Preview не делает статью
             * доступной через публичную выборку.
             */
            dilitant_visibility_assert_excluded(
                (int) $post->ID
            );
        }
    );
}


/**
 * AM-T11.4 — объектные полномочия.
 *
 * Проверяем, что автор может редактировать
 * собственный черновик, но не получает
 * право Preview чужих публикаций.
 */
function dilitant_visibility_test_t11_4(
    DilitantArticleFixtures $fixtures,
    DilitantVisibilityUsers $users
): void {
    dilitant_visibility_verify_environment();

    $draft =
        dilitant_visibility_get_post(
            $fixtures->draft_post
        );

    $other =
        dilitant_visibility_get_post(
            $fixtures->private_post
        );

    $previous_author =
        (int) $draft->post_author;

    dilitant_test_assert_same(
        true,
        in_array(
            (int) $draft->ID,
            $fixtures->all_post_ids(),
            true
        ),
        'Draft does not belong to current fixtures.'
    );

    try {
        /*
         * Временно назначаем тестового
         * пользователя автором черновика.
         */
        $updated = wp_update_post(
            [
                'ID' =>
                    (int) $draft->ID,

                'post_author' =>
                    $users->author,
            ],
            true
        );

        if (is_wp_error($updated)) {
            throw new RuntimeException(
                $updated->get_error_message()
            );
        }

        dilitant_test_assert_same(
            (int) $draft->ID,
            (int) $updated,
            'Unable to change fixture author.'
        );

        $owned =
            dilitant_visibility_get_post(
                (int) $draft->ID
            );

        dilitant_test_assert_same(
            $users->author,
            (int) $owned->post_author,
            'Fixture ownership was not applied.'
        );

        dilitant_visibility_with_user(
            $users->author,

            static function () use (
                $owned,
                $other
            ): void {
                /*
                 * Положительное полномочие
                 * на собственную запись.
                 */
                dilitant_visibility_assert_edit_permission(
                    (int) $owned->ID,
                    true
                );

                dilitant_visibility_with_post_context(
                    $owned,
                    true,

                    static function (
                        WP_Query $query
                    ) use ($owned): void {
                        dilitant_visibility_assert_preview(
                            $owned,
                            true,
                            true,
                            false
                        );

                        dilitant_visibility_assert_handler_decision(
                            $query,
                            false
                        );
                    }
                );


                /*
                 * Отрицательное полномочие
                 * на чужую запись.
                 */
                dilitant_visibility_assert_edit_permission(
                    (int) $other->ID,
                    false
                );

                dilitant_visibility_with_post_context(
                    $other,
                    true,

                    static function (
                        WP_Query $query
                    ) use ($other): void {
                        dilitant_visibility_assert_preview(
                            $other,
                            false,
                            false,
                            false
                        );

                        dilitant_visibility_assert_handler_decision(
                            $query,
                            true
                        );
                    }
                );
            }
        );

    } finally {
        /*
         * Восстанавливаем исходного автора,
         * чтобы сохранить инварианты Fixtures
         * и гарантировать очистку пользователя.
         */
        $restored = wp_update_post(
            [
                'ID' =>
                    (int) $draft->ID,

                'post_author' =>
                    $previous_author,
            ],
            true
        );

        if (is_wp_error($restored)) {
            throw new RuntimeException(
                $restored->get_error_message()
            );
        }

        dilitant_test_assert_same(
            (int) $draft->ID,
            (int) $restored,
            'Unable to restore fixture author.'
        );

        $post =
            dilitant_visibility_get_post(
                (int) $draft->ID
            );

        dilitant_test_assert_same(
            $previous_author,
            (int) $post->post_author,
            'Original fixture author was not restored.'
        );
    }
}