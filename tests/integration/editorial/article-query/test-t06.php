<?php
/**
 * AM-T06 — Защита обязательного типа записи.
 *
 * Сценарии:
 *     AM-T06.1 — post_type = page;
 *     AM-T06.2 — post_type = any;
 *     AM-T06.3 — массив типов и явные ID.
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
 * Формирует набор испытаний AM-T06.
 */
function dilitant_query_test_cases_t06(
    DilitantArticleFixtures $fixtures
): array {
    return [

        /*
         * AM-T06.1.
         *
         * Попытка заменить обязательный
         * тип post типом page.
         */
        'AM-T06.1' => static function () use (
            $fixtures
        ): void {
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'post_type' => 'page',
                ]
            );

            dilitant_test_assert_same(
                false,
                dilitant_land_is_blog_article(
                    $fixtures->page_post
                ),
                'Page was recognized as blog article.'
            );
        },


        /*
         * AM-T06.2.
         *
         * Попытка выборки всех типов записей.
         */
        'AM-T06.2' => static function () use (
            $fixtures
        ): void {
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'post_type' => 'any',
                ]
            );

            dilitant_test_assert_same(
                false,
                dilitant_land_is_blog_article(
                    $fixtures->archive_post
                ),
                'Archive CPT was recognized as blog article.'
            );
        },


        /*
         * AM-T06.3.
         *
         * Попытка передачи массива типов
         * и непосредственного включения
         * запрещённых объектов по ID.
         */
        'AM-T06.3' => static function () use (
            $fixtures
        ): void {
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'post_type' => [
                        'post',
                        'page',
                        $fixtures->archive_post_type(),
                    ],
                ]
            );


            /*
             * Явное включение ID не должно
             * обходить обязательный тип.
             */
            dilitant_query_test_assert_selection(
                $fixtures,
                [
                    'post_type' => 'any',

                    'post__in' => [
                        $fixtures->root_post,
                        $fixtures->page_post,
                        $fixtures->archive_post,
                    ],
                ],
                [
                    $fixtures->root_post,
                ]
            );


            /*
             * Выборка исключительно объектов
             * запрещённых типов.
             */
            $query = dilitant_query_test_execute([
                'post_type' => 'any',

                'post__in' => [
                    $fixtures->page_post,
                    $fixtures->archive_post,
                ],
            ]);

            dilitant_query_test_assert_empty(
                $query
            );
        },
    ];
}