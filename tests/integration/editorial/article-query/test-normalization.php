<?php
/**
 * AM-QN — Испытания нормализаторов.
 *
 * Ответственность:
 *     - проверка допустимых значений;
 *     - проверка недопустимых значений;
 *     - проверка границ диапазонов;
 *     - проверка приоритета исключений;
 *     - проверка закрытого перечня аргументов.
 *
 * Количество сценариев: 20.
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
 * Проверяет результат нормализации.
 *
 * null в expected означает обязательный отказ.
 *
 * Массив в expected содержит значения,
 * которые должны присутствовать в результате.
 */
function dilitant_query_test_assert_normalization(
    array $args,
    ?array $expected
): void {
    $result =
        dilitant_land_normalize_blog_query_args(
            $args
        );

    if ($expected === null) {
        dilitant_test_assert_same(
            null,
            $result,
            'Invalid arguments were accepted.'
        );

        return;
    }

    dilitant_test_assert(
        is_array($result),
        'Valid arguments were rejected.'
    );

    foreach ($expected as $key => $value) {
        dilitant_test_assert(
            array_key_exists($key, $result),
            "Normalized argument is missing: {$key}"
        );

        dilitant_test_assert_same(
            $value,
            $result[$key],
            "Unexpected normalized argument: {$key}"
        );
    }
}


/**
 * Формирует постоянный набор AM-QN.
 */
function dilitant_query_test_cases_normalization(): array
{
    /*
     * Первые 18 сценариев описываются
     * входным набором и ожидаемым результатом.
     */
    $inputs = [

        'AM-QN-01 pagination valid' => [
            [
                'posts_per_page' => 12,
                'paged' => 2,
            ],
            [
                'posts_per_page' => 12,
                'paged' => 2,
            ],
        ],

        'AM-QN-02 pagination upper limit' => [
            [
                'posts_per_page' => 100,
            ],
            [
                'posts_per_page' => 100,
            ],
        ],

        'AM-QN-03 pagination excessive limit' => [
            [
                'posts_per_page' => 101,
            ],
            null,
        ],

        'AM-QN-04 pagination unlimited' => [
            [
                'posts_per_page' => -1,
            ],
            null,
        ],

        'AM-QN-05 pagination zero page' => [
            [
                'paged' => 0,
            ],
            null,
        ],

        'AM-QN-06 pagination overflow' => [
            [
                'posts_per_page' => 100,
                'paged' => PHP_INT_MAX,
            ],
            null,
        ],

        'AM-QN-07 search valid' => [
            [
                's' => 'Армения',
            ],
            [
                's' => 'Армения',
            ],
        ],

        'AM-QN-08 search invalid type' => [
            [
                's' => ['Армения'],
            ],
            null,
        ],

        'AM-QN-09 search excessive length' => [
            [
                's' => str_repeat('a', 501),
            ],
            null,
        ],

        'AM-QN-10 empty inclusion' => [
            [
                'post__in' => [],
            ],
            null,
        ],

        'AM-QN-11 invalid identifier' => [
            [
                'post__in' => [55, -1],
            ],
            null,
        ],

        'AM-QN-12 complete exclusion' => [
            [
                'post__in' => [55],
                'post__not_in' => [55],
            ],
            null,
        ],

        'AM-QN-13 valid ordering' => [
            [
                'orderby' => 'date',
                'order' => 'DESC',
            ],
            [
                'orderby' => [
                    'date' => 'DESC',
                    'ID' => 'DESC',
                ],
                'order' => 'DESC',
            ],
        ],

        'AM-QN-14 invalid ordering' => [
            [
                'orderby' => 'rand',
            ],
            null,
        ],

        'AM-QN-15 invalid order' => [
            [
                'order' => 'UNKNOWN',
            ],
            null,
        ],

        'AM-QN-16 missing relevance search' => [
            [
                'orderby' => 'relevance',
            ],
            null,
        ],

        'AM-QN-17 valid relevance search' => [
            [
                'orderby' => 'relevance',
                's' => 'Армения',
            ],
            [
                'orderby' => 'relevance',
                's' => 'Армения',
            ],
        ],

        'AM-QN-18 missing inclusion ordering' => [
            [
                'orderby' => 'post__in',
            ],
            null,
        ],
    ];


    /*
     * Формируем вызываемые испытания.
     */
    $tests = [];

    foreach (
        $inputs
        as $name => [$args, $expected]
    ) {
        $tests[$name] =
            static function () use (
                $args,
                $expected
            ): void {
                dilitant_query_test_assert_normalization(
                    $args,
                    $expected
                );
            };
    }


    /*
     * AM-QN-19.
     *
     * Приоритет исключений.
     */
    $tests['AM-QN-19 exclusion precedence'] =
        static function (): void {
            $result =
                dilitant_land_normalize_blog_query_args([
                    'post__in' => [55, 59, 55],
                    'post__not_in' => [55],
                ]);

            dilitant_test_assert(
                is_array($result),
                'Valid ID selection was rejected.'
            );

            dilitant_test_assert_same(
                [59],
                $result['post__in'] ?? null,
                'Exclusion precedence failed.'
            );

            dilitant_test_assert(
                !array_key_exists(
                    'post__not_in',
                    $result
                ),
                'Redundant exclusion argument remained.'
            );


            /*
             * Нормализатор обязан сохранить
             * исходный порядок идентификаторов.
             */
            dilitant_query_test_assert_normalization(
                [
                    'post__in' => [
                        '59',
                        '055',
                        59,
                        70,
                    ],
                ],
                [
                    'post__in' => [
                        59,
                        55,
                        70,
                    ],
                ]
            );
        };


    /*
     * AM-QN-20.
     *
     * Закрытый перечень аргументов.
     */
    $tests['AM-QN-20 closed argument list'] =
        static function (): void {
            $result =
                dilitant_land_normalize_blog_query_args([
                    'post_type' => 'page',
                    'post_status' => 'any',
                    'has_password' => true,
                    'tax_query' => [],
                    'nopaging' => true,
                    'offset' => 500,
                    'suppress_filters' => true,
                    'fields' => 'ids',
                    'no_found_rows' => true,
                    'category_id' => 999,
                    'unknown_argument' => 'test',
                ]);

            dilitant_test_assert(
                is_array($result),
                'Unknown arguments caused unexpected rejection.'
            );

            $allowed = [
                'posts_per_page',
                'paged',
                'orderby',
                'order',
                's',
                'post__in',
                'post__not_in',
            ];

            $unexpected = array_values(
                array_diff(
                    array_keys($result),
                    $allowed
                )
            );

            dilitant_test_assert_same(
                [],
                $unexpected,
                'Unapproved arguments escaped normalization.'
            );


            /*
             * Проверяем, что неизвестные параметры
             * вообще не влияют на результат.
             */
            $baseline =
                dilitant_land_normalize_blog_query_args(
                    []
                );

            dilitant_test_assert_same(
                $baseline,
                $result,
                'Unknown arguments changed normalization result.'
            );
        };

    return $tests;
}