<?php
/**
 * AM-T07 — Защита статусной
 * и парольной политики.
 *
 * Сценарии:
 *     AM-T07.1 — draft;
 *     AM-T07.2 — private;
 *     AM-T07.3 — future;
 *     AM-T07.4 — any и массив статусов;
 *     AM-T07.5 — has_password;
 *     AM-T07.6 — post_password и явные ID.
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
 * Формирует набор испытаний AM-T07.
 */
function dilitant_query_test_cases_t07(
    DilitantArticleFixtures $fixtures
): array {
    return [

        /*
         * AM-T07.1.
         *
         * Попытка выборки черновиков.
         */
        'AM-T07.1' => static function () use (
            $fixtures
        ): void {
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'post_status' => 'draft',
                ]
            );
        },


        /*
         * AM-T07.2.
         *
         * Попытка выборки приватных статей.
         */
        'AM-T07.2' => static function () use (
            $fixtures
        ): void {
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'post_status' => 'private',
                ]
            );
        },


        /*
         * AM-T07.3.
         *
         * Попытка выборки запланированных
         * публикаций.
         */
        'AM-T07.3' => static function () use (
            $fixtures
        ): void {
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'post_status' => 'future',
                ]
            );
        },


        /*
         * AM-T07.4.
         *
         * Попытка выборки произвольных статусов.
         */
        'AM-T07.4' => static function () use (
            $fixtures
        ): void {
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'post_status' => 'any',
                ]
            );

            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'post_status' => [
                        'publish',
                        'draft',
                        'private',
                        'future',
                    ],
                ]
            );
        },


        /*
         * AM-T07.5.
         *
         * Попытка изменить обязательное
         * ограничение парольной защиты.
         */
        'AM-T07.5' => static function () use (
            $fixtures
        ): void {
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'has_password' => true,
                ]
            );

            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'has_password' => null,
                ]
            );
        },


        /*
         * AM-T07.6.
         *
         * Передача пароля и явное включение
         * недоступных публичному читателю записей.
         */
        'AM-T07.6' => static function () use (
            $fixtures
        ): void {
            /*
             * Даже правильный пароль
             * не изменяет публичную политику.
             */
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'has_password' => true,

                    'post_password' =>
                        $fixtures->fixture_password(),
                ]
            );


            /*
             * Проверяем пароль отдельно,
             * без попытки изменить has_password.
             */
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'post_password' =>
                        $fixtures->fixture_password(),
                ]
            );


            /*
             * Принадлежность к блогу
             * не зависит от публичной доступности.
             */
            $nonpublic_ids = [
                $fixtures->draft_post,
                $fixtures->private_post,
                $fixtures->future_post,
                $fixtures->password_post,
            ];

            foreach ($nonpublic_ids as $id) {
                dilitant_test_assert_same(
                    true,
                    dilitant_land_is_blog_article(
                        $id
                    ),
                    'Nonpublic article lost blog identity.'
                );
            }


            /*
             * Попытка включить публичную
             * и недоступные статьи одновременно.
             */
            dilitant_query_test_assert_selection(
                $fixtures,
                [
                    'post_status' => 'any',
                    'has_password' => true,

                    'post__in' => array_merge(
                        [$fixtures->root_post],
                        $nonpublic_ids
                    ),
                ],
                [
                    $fixtures->root_post,
                ]
            );


            /*
             * Выборка только недоступных статей
             * должна быть пустой.
             */
            $query = dilitant_query_test_execute([
                'post_status' => 'any',
                'has_password' => true,

                'post__in' => $nonpublic_ids,
            ]);

            dilitant_query_test_assert_empty(
                $query
            );
        },
    ];
}