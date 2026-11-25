<?php
/**
 * AM-CQ-09 — согласованность пагинации
 * главного запроса публичного каталога.
 *
 * EDC-01 / AM-D02 / AM-D05.
 *
 * Проверяет:
 *     - состав страниц;
 *     - нормативную сортировку;
 *     - общее количество публикаций;
 *     - количество страниц;
 *     - отсутствие повторов и пропусков;
 *     - пустую выборку за пределами каталога;
 *     - восстановление размера страницы.
 *
 * HTTP-маршрутизация здесь не проверяется.
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
 * AM-CQ-09.
 */
function dilitant_catalog_test_cq09(
    DilitantArticleFixtures $fixtures
): void {
    $expected_order =
        dilitant_catalog_expected_order(
            $fixtures
        );

    dilitant_test_assert_same(
        3,
        count($expected_order),
        'Unexpected pagination fixture count.'
    );


    /*
     * Вложенный фильтр размера страницы
     * с приоритетом выше внешнего.
     */
    dilitant_catalog_with_page_size(
        2,

        static function () use (
            $fixtures,
            $expected_order
        ): void {
            $first =
                dilitant_catalog_execute([
                    'paged' => 1,
                ]);

            $second =
                dilitant_catalog_execute([
                    'paged' => 2,
                ]);


            /*
             * Точный состав страниц.
             */
            dilitant_catalog_assert_selection(
                $first,
                $fixtures,
                array_slice(
                    $expected_order,
                    0,
                    2
                )
            );

            dilitant_catalog_assert_selection(
                $second,
                $fixtures,
                array_slice(
                    $expected_order,
                    2,
                    1
                )
            );


            /*
             * Общие метаданные пагинации.
             */
            foreach (
                [$first, $second]
                as $query
            ) {
                dilitant_test_assert_same(
                    2,
                    (int) $query->get(
                        'posts_per_page'
                    ),
                    'Incorrect catalog page size.'
                );

                dilitant_test_assert_same(
                    3,
                    (int) $query->found_posts,
                    'Incorrect catalog total count.'
                );

                dilitant_test_assert_same(
                    2,
                    (int) $query->max_num_pages,
                    'Incorrect catalog page count.'
                );
            }


            /*
             * Номера страниц и признаки
             * пагинированного запроса.
             */
            dilitant_test_assert_same(
                1,
                (int) $first->get('paged'),
                'Incorrect first page number.'
            );

            dilitant_test_assert_same(
                2,
                (int) $second->get('paged'),
                'Incorrect second page number.'
            );

            dilitant_test_assert_same(
                true,
                $second->is_paged(),
                'Second page lost paginated query identity.'
            );


            /*
             * Фактический порядок записей.
             */
            $first_ids =
                dilitant_query_test_ordered_ids(
                    $first
                );

            $second_ids =
                dilitant_query_test_ordered_ids(
                    $second
                );


            /*
             * Отсутствие пересечения страниц.
             */
            dilitant_test_assert_same(
                [],
                array_values(
                    array_intersect(
                        $first_ids,
                        $second_ids
                    )
                ),
                'Duplicate article across catalog pages.'
            );


            /*
             * Объединение страниц должно
             * воспроизводить полный порядок
             * нормативной выборки.
             */
            dilitant_test_assert_same(
                $expected_order,
                array_merge(
                    $first_ids,
                    $second_ids
                ),
                'Catalog pagination lost or reordered articles.'
            );


            /*
             * Запрос несуществующей страницы.
             */
            $third =
                dilitant_catalog_execute([
                    'paged' => 3,
                ]);

            dilitant_catalog_assert_selection(
                $third,
                $fixtures,
                []
            );

            dilitant_test_assert_same(
                3,
                (int) $third->get('paged'),
                'Out-of-range page number was changed.'
            );

            dilitant_test_assert_same(
                0,
                (int) $third->post_count,
                'Out-of-range page contains posts.'
            );
        },

        2000
    );


    /*
     * Восстановление внешнего фильтра
     * размера страницы.
     */
    dilitant_test_assert_same(
        100,
        (int) get_option('posts_per_page'),
        'Outer page size was not restored.'
    );
}