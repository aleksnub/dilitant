<?php
/**
 * AM-T08 — Защита нормативного
 * дерева категорий Article Model.
 *
 * Сценарии:
 *     AM-T08.1  — tax_query;
 *     AM-T08.2  — cat;
 *     AM-T08.3  — category_name;
 *     AM-T08.4  — category__in;
 *     AM-T08.5  — category__not_in;
 *     AM-T08.6  — category__and;
 *     AM-T08.7  — допустимый category_id;
 *     AM-T08.8  — посторонний category_id;
 *     AM-T08.9  — отсутствующий category_id;
 *     AM-T08.10 — обход через post__in.
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
 * Формирует набор испытаний AM-T08.
 */
function dilitant_query_test_cases_t08(
    DilitantArticleFixtures $fixtures
): array {
    return [

        /*
         * AM-T08.1.
         *
         * Попытка подмены нормативного tax_query.
         */
        'AM-T08.1' => static function () use (
            $fixtures
        ): void {
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'tax_query' => [
                        [
                            'taxonomy' => 'category',
                            'field' => 'term_id',

                            'terms' => [
                                $fixtures->outside_category,
                            ],

                            'include_children' => true,
                            'operator' => 'IN',
                        ],
                    ],
                ]
            );


            /*
             * Попытка удалить ограничение.
             */
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'tax_query' => [],
                ]
            );


            /*
             * Попытка сформировать
             * расширяющее OR-условие.
             */
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'tax_query' => [
                        'relation' => 'OR',

                        [
                            'taxonomy' => 'category',
                            'field' => 'term_id',

                            'terms' => [
                                $fixtures->root_category,
                            ],
                        ],

                        [
                            'taxonomy' => 'category',
                            'field' => 'term_id',

                            'terms' => [
                                $fixtures->outside_category,
                            ],
                        ],
                    ],
                ]
            );
        },


        /*
         * AM-T08.2.
         *
         * Альтернативный параметр cat.
         */
        'AM-T08.2' => static function () use (
            $fixtures
        ): void {
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'cat' =>
                        $fixtures->outside_category,
                ]
            );

            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'cat' =>
                        -$fixtures->root_category,
                ]
            );
        },


        /*
         * AM-T08.3.
         *
         * Альтернативный параметр category_name.
         */
        'AM-T08.3' => static function () use (
            $fixtures
        ): void {
            $outside = get_term(
                $fixtures->outside_category,
                'category'
            );

            dilitant_test_assert(
                $outside instanceof WP_Term,
                'Outside category fixture is missing.'
            );

            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'category_name' =>
                        $outside->slug,
                ]
            );
        },


        /*
         * AM-T08.4.
         *
         * Альтернативный параметр category__in.
         */
        'AM-T08.4' => static function () use (
            $fixtures
        ): void {
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'category__in' => [
                        $fixtures->outside_category,
                    ],
                ]
            );
        },


        /*
         * AM-T08.5.
         *
         * Попытка исключения всего дерева блога
         * посредством category__not_in.
         */
        'AM-T08.5' => static function () use (
            $fixtures
        ): void {
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'category__not_in' =>
                        $fixtures->expected_category_ids(),
                ]
            );
        },


        /*
         * AM-T08.6.
         *
         * Альтернативный параметр category__and.
         */
        'AM-T08.6' => static function () use (
            $fixtures
        ): void {
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'category__and' => [
                        $fixtures->outside_category,
                    ],
                ]
            );
        },


        /*
         * AM-T08.7.
         *
         * Разрешённый фильтр category_id.
         */
        'AM-T08.7' => static function () use (
            $fixtures
        ): void {
            /*
             * Дочерняя категория и её потомок.
             */
            dilitant_query_test_assert_selection(
                $fixtures,
                [
                    'category_id' =>
                        $fixtures->child_category,
                ],
                $fixtures->expected_child_post_ids(),
                [
                    $fixtures->child_category,
                    $fixtures->grandchild_category,
                ]
            );


            /*
             * Конечная категория дерева.
             */
            dilitant_query_test_assert_selection(
                $fixtures,
                [
                    'category_id' =>
                        $fixtures->grandchild_category,
                ],
                [
                    $fixtures->grandchild_post,
                ],
                [
                    $fixtures->grandchild_category,
                ]
            );


            /*
             * Корневая категория включает
             * всё нормативное дерево.
             */
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'category_id' =>
                        $fixtures->root_category,
                ]
            );


            /*
             * Строковое представление
             * допустимого идентификатора.
             */
            dilitant_query_test_assert_public_set(
                $fixtures,
                [
                    'category_id' =>
                        (string) $fixtures->root_category,
                ]
            );


            /*
             * Допустимый category_id совместно
             * с попыткой подмены tax_query.
             */
            dilitant_query_test_assert_selection(
                $fixtures,
                [
                    'category_id' =>
                        $fixtures->child_category,

                    'tax_query' => [
                        [
                            'taxonomy' => 'category',
                            'field' => 'term_id',

                            'terms' => [
                                $fixtures->outside_category,
                            ],
                        ],
                    ],
                ],
                $fixtures->expected_child_post_ids(),
                [
                    $fixtures->child_category,
                    $fixtures->grandchild_category,
                ]
            );
        },


        /*
         * AM-T08.8.
         *
         * Посторонний category_id.
         */
        'AM-T08.8' => static function () use (
            $fixtures
        ): void {
            $query = dilitant_query_test_execute([
                'category_id' =>
                    $fixtures->outside_category,
            ]);

            dilitant_query_test_assert_closed_failure(
                $query
            );
        },


        /*
         * AM-T08.9.
         *
         * Несуществующий category_id.
         */
        'AM-T08.9' => static function (): void {
            $missing_id = PHP_INT_MAX;

            $existing = get_term(
                $missing_id,
                'category'
            );

            dilitant_test_assert(
                !$existing instanceof WP_Term,
                'Chosen missing category actually exists.'
            );

            $query = dilitant_query_test_execute([
                'category_id' => $missing_id,
            ]);

            dilitant_query_test_assert_closed_failure(
                $query
            );
        },


        /*
         * AM-T08.10.
         *
         * Попытка обхода категорий
         * посредством разрешённого post__in.
         */
        'AM-T08.10' => static function () use (
            $fixtures
        ): void {
            /*
             * Публичная статья и посторонняя запись.
             */
            dilitant_query_test_assert_selection(
                $fixtures,
                [
                    'post__in' => [
                        $fixtures->root_post,
                        $fixtures->outside_post,
                    ],
                ],
                [
                    $fixtures->root_post,
                ]
            );


            /*
             * Только посторонняя запись.
             */
            $query = dilitant_query_test_execute([
                'post__in' => [
                    $fixtures->outside_post,
                ],
            ]);

            dilitant_query_test_assert_empty(
                $query
            );


            /*
             * Явные ID не должны расширять
             * выбранную дочернюю ветвь.
             */
            dilitant_query_test_assert_selection(
                $fixtures,
                [
                    'category_id' =>
                        $fixtures->child_category,

                    'post__in' => [
                        $fixtures->root_post,
                        $fixtures->child_post,
                        $fixtures->grandchild_post,
                        $fixtures->outside_post,
                    ],
                ],
                $fixtures->expected_child_post_ids(),
                [
                    $fixtures->child_category,
                    $fixtures->grandchild_category,
                ]
            );
        },
    ];
}