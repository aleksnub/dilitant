<?php
/**
 * Испытания контекста предварительного
 * просмотра статей.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *
 * Сценарии:
 *     AM-T11.5 — отсутствие Preview;
 *     AM-T11.6 — запись в корзине;
 *     AM-T11.7 — несовпадение записи.
 *
 * Наличие полномочий пользователя
 * не является достаточным основанием
 * для предварительного просмотра.
 *
 * Дополнительно должны подтверждаться:
 *     - контекст запроса;
 *     - допустимый статус записи;
 *     - идентичность запрошенного объекта.
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
 * AM-T11.5 — отсутствие Preview.
 */
function dilitant_visibility_test_t11_5(
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
             * Наличие полномочий
             * без контекста Preview
             * не является основанием доступа.
             */
            dilitant_visibility_with_post_context(
                $post,
                false,

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
             * Опубликованная статья,
             * напротив, доступна без Preview.
             */
            $public =
                dilitant_visibility_get_post(
                    $fixtures->root_post
                );

            dilitant_visibility_with_post_context(
                $public,
                false,

                static function (
                    WP_Query $query
                ) use ($public): void {
                    dilitant_visibility_assert_preview(
                        $public,
                        false,
                        true,
                        true
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
 * AM-T11.6 — запись в корзине.
 */
function dilitant_visibility_test_t11_6(
    DilitantArticleFixtures $fixtures,
    DilitantVisibilityUsers $users
): void {
    dilitant_visibility_with_user(
        $users->administrator,

        static function () use (
            $fixtures
        ): void {
            dilitant_visibility_with_trashed_post(
                $fixtures,
                $fixtures->root_post,

                static function (
                    WP_Post $post
                ): void {
                    dilitant_visibility_assert_identity(
                        $post,
                        true,
                        false
                    );

                    /*
                     * Административные полномочия
                     * не отменяют ограничение
                     * по статусу trash.
                     */
                    dilitant_visibility_assert_edit_permission(
                        (int) $post->ID,
                        true
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


            /*
             * Подтверждаем восстановление
             * исходного состояния публикации.
             */
            dilitant_visibility_assert_post(
                $fixtures->root_post,
                true,
                true,
                'publish'
            );
        }
    );
}


/**
 * AM-T11.7 — несовпадение записи.
 *
 * Контекст Preview одной записи
 * не является основанием для Preview
 * другой записи.
 */
function dilitant_visibility_test_t11_7(
    DilitantArticleFixtures $fixtures,
    DilitantVisibilityUsers $users
): void {
    dilitant_visibility_with_user(
        $users->administrator,

        static function () use (
            $fixtures
        ): void {
            $requested =
                dilitant_visibility_get_post(
                    $fixtures->draft_post
                );

            $other =
                dilitant_visibility_get_post(
                    $fixtures->private_post
                );

            dilitant_visibility_assert_edit_permission(
                (int) $requested->ID,
                true
            );

            dilitant_visibility_assert_edit_permission(
                (int) $other->ID,
                true
            );

            dilitant_visibility_with_post_context(
                $requested,
                true,

                static function (
                    WP_Query $query
                ) use (
                    $requested,
                    $other
                ): void {
                    /*
                     * Положительный контроль:
                     * запрошенный объект допускается.
                     */
                    dilitant_visibility_assert_preview(
                        $requested,
                        true,
                        true,
                        false
                    );


                    /*
                     * Отрицательный контроль:
                     * другой объект отклоняется,
                     * несмотря на edit_post.
                     */
                    dilitant_visibility_assert_preview(
                        $other,
                        false,
                        false,
                        false
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