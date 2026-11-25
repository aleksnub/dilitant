<?php
/**
 * AM-T10.1–AM-T10.3.
 *
 * Проверка парольной защиты статьи.
 *
 * Парольная защита исключает запись
 * из публичного Article Model независимо
 * от полномочий текущего пользователя
 * и наличия корректного password cookie.
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
 * AM-T10.1 — защищённая статья.
 */
function dilitant_visibility_test_t10_1(
    DilitantArticleFixtures $fixtures
): void {
    dilitant_visibility_with_user(
        0,

        static function () use (
            $fixtures
        ): void {
            $post =
                dilitant_visibility_get_post(
                    $fixtures->password_post
                );

            dilitant_test_assert_same(
                'publish',
                $post->post_status,
                'Password fixture is not published.'
            );

            dilitant_test_assert(
                $post->post_password !== '',
                'Password fixture has no password.'
            );

            dilitant_visibility_assert_post(
                $fixtures->password_post,
                true,
                false,
                'publish'
            );

            dilitant_visibility_assert_excluded(
                $fixtures->password_post
            );


            /*
             * Проверяем действительный
             * singular WP_Query по ID.
             */
            dilitant_visibility_with_query(
                [
                    'p' =>
                        $fixtures->password_post,

                    'post_type' => 'post',
                ],

                static function (
                    WP_Query $query
                ) use ($post): void {
                    dilitant_test_assert_same(
                        true,
                        $query->is_singular('post'),
                        'Password fixture is not a singular query.'
                    );

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
 * AM-T10.2 — наличие правильного пароля.
 *
 * Штатный WordPress password cookie
 * не изменяет нормативную публичность.
 */
function dilitant_visibility_test_t10_2(
    DilitantArticleFixtures $fixtures
): void {
    require_once ABSPATH
        . WPINC
        . '/class-phpass.php';

    dilitant_test_assert(
        class_exists('PasswordHash'),
        'WordPress password hasher is unavailable.'
    );

    dilitant_test_assert(
        defined('COOKIEHASH'),
        'WordPress COOKIEHASH is missing.'
    );

    $post =
        dilitant_visibility_get_post(
            $fixtures->password_post
        );

    dilitant_test_assert_same(
        $fixtures->fixture_password(),
        $post->post_password,
        'Unexpected fixture password.'
    );


    /*
     * Формируем настоящий password cookie
     * в формате WordPress.
     */
    $hasher = new PasswordHash(
        8,
        true
    );

    $cookie_value =
        $hasher->HashPassword(
            $post->post_password
        );

    dilitant_test_assert(
        is_string($cookie_value)
        && str_starts_with(
            $cookie_value,
            '$P$'
        ),
        'Unable to create WordPress password cookie.'
    );

    $cookie_name =
        'wp-postpass_' . COOKIEHASH;


    dilitant_visibility_with_user(
        0,

        static function () use (
            $fixtures,
            $post,
            $cookie_name,
            $cookie_value
        ): void {
            dilitant_visibility_with_cookie(
                $cookie_name,
                $cookie_value,

                static function () use (
                    $fixtures,
                    $post
                ): void {
                    /*
                     * Подтверждаем, что штатный
                     * WordPress принимает пароль.
                     */
                    dilitant_test_assert_same(
                        false,
                        post_password_required(
                            $post
                        ),
                        'WordPress rejected valid password cookie.'
                    );


                    /*
                     * Несмотря на корректный пароль,
                     * статья не становится публичной.
                     */
                    dilitant_visibility_assert_post(
                        $fixtures->password_post,
                        true,
                        false,
                        'publish'
                    );

                    dilitant_visibility_assert_excluded(
                        $fixtures->password_post
                    );


                    /*
                     * Парольный cookie не является
                     * полномочием Preview.
                     */
                    dilitant_visibility_with_query(
                        [
                            'p' =>
                                $fixtures->password_post,

                            'post_type' => 'post',
                        ],

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
    );
}


/**
 * AM-T10.3 — административные полномочия.
 */
function dilitant_visibility_test_t10_3(
    DilitantArticleFixtures $fixtures,
    DilitantVisibilityUsers $users
): void {
    dilitant_visibility_with_user(
        $users->administrator,

        static function () use (
            $fixtures
        ): void {
            dilitant_test_assert(
                current_user_can('manage_options'),
                'Administrator context is missing.'
            );

            dilitant_visibility_assert_edit_permission(
                $fixtures->password_post,
                true
            );


            /*
             * Административные полномочия
             * не изменяют публичный предикат.
             */
            dilitant_visibility_assert_post(
                $fixtures->password_post,
                true,
                false,
                'publish'
            );

            dilitant_visibility_assert_excluded(
                $fixtures->password_post
            );


            /*
             * Проверяем остальные закрытые
             * состояния в том же контексте.
             */
            $closed = [
                $fixtures->draft_post =>
                    'draft',

                $fixtures->private_post =>
                    'private',

                $fixtures->future_post =>
                    'future',
            ];

            foreach (
                $closed as $id => $status
            ) {
                dilitant_visibility_assert_post(
                    $id,
                    true,
                    false,
                    $status
                );

                dilitant_visibility_assert_excluded(
                    $id
                );
            }


            /*
             * Администратор без контекста
             * Preview также не получает
             * право отображения закрытой статьи.
             */
            $post =
                dilitant_visibility_get_post(
                    $fixtures->password_post
                );

            dilitant_visibility_with_query(
                [
                    'p' =>
                        $fixtures->password_post,

                    'post_type' => 'post',
                ],

                static function (
                    WP_Query $query
                ) use ($post): void {
                    dilitant_test_assert_same(
                        false,
                        $query->is_preview(),
                        'Unexpected preview context.'
                    );

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
             * Публичная выборка остаётся
             * одинаковой для администратора
             * и анонимного читателя.
             */
            dilitant_visibility_assert_public_query(
                $fixtures
            );
        }
    );
}