<?php
/**
 * AM-QI — Интеграционные испытания
 * навигационных параметров Article Model.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D02 — Защита инвариантов WP_Query
 *     AM-D05 — Пагинация каталога
 *
 * Ответственность:
 *     - проверка фактической пагинации;
 *     - проверка количества публикаций;
 *     - проверка количества страниц;
 *     - проверка полноты выборки;
 *     - проверка сортировки по ID;
 *     - проверка порядка post__in;
 *     - проверка действительного поиска;
 *     - проверка сохранения инвариантов
 *       публичной выборки при поиске.
 *
 * Сценарии:
 *     AM-QI-07 — pagination;
 *     AM-QI-08 — ID ordering;
 *     AM-QI-09 — inclusion ordering;
 *     AM-QI-10 — public article search.
 *
 * Количество сценариев: 4.
 *
 * Зависимости:
 *     bootstrap.php
 *     fixtures.php
 *     article-query/execution.php
 *     article-query/assertions.php
 *
 * Компонент не регистрирует и не запускает
 * испытания самостоятельно.
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
 * AM-QI-07.
 *
 * Проверяет действительную пагинацию
 * на трёх опубликованных статьях.
 *
 * Размер страницы: 2 статьи.
 *
 * Ожидаемый результат:
 *
 *     страница 1:
 *         root_post;
 *         child_post;
 *
 *     страница 2:
 *         grandchild_post;
 *
 *     страница 3:
 *         пустая выборка.
 */
function dilitant_query_test_pagination(
    DilitantArticleFixtures $fixtures
): void {
    /*
     * Получаем первую страницу.
     */
    $page_one = dilitant_query_test_assert_selection(
        $fixtures,
        [
            'posts_per_page' => 2,
            'paged' => 1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ],
        [
            $fixtures->root_post,
            $fixtures->child_post,
        ]
    );


    /*
     * Получаем вторую страницу.
     */
    $page_two = dilitant_query_test_assert_selection(
        $fixtures,
        [
            'posts_per_page' => 2,
            'paged' => 2,
            'orderby' => 'ID',
            'order' => 'ASC',
        ],
        [
            $fixtures->grandchild_post,
        ]
    );


    /*
     * Обе непустые страницы должны
     * сообщать одинаковое общее
     * количество публикаций и страниц.
     */
    foreach ([$page_one, $page_two] as $query) {
        dilitant_test_assert_same(
            3,
            (int) $query->found_posts,
            'Incorrect total article count.'
        );

        dilitant_test_assert_same(
            2,
            (int) $query->max_num_pages,
            'Incorrect total page count.'
        );
    }


    /*
     * Получаем идентификаторы публикаций
     * каждой страницы.
     */
    $first_ids = dilitant_test_query_ids(
        $page_one
    );

    $second_ids = dilitant_test_query_ids(
        $page_two
    );


    /*
     * Проверяем отсутствие пересечения.
     *
     * Одна публикация не должна
     * присутствовать одновременно
     * на двух страницах.
     */
    dilitant_test_assert_same(
        [],
        array_values(
            array_intersect(
                $first_ids,
                $second_ids
            )
        ),
        'Article duplicated across pages.'
    );


    /*
     * Проверяем полноту объединения страниц.
     *
     * Ни одна допустимая статья
     * не должна быть потеряна.
     */
    dilitant_test_assert_same(
        $fixtures->expected_published_post_ids(),
        dilitant_test_normalize_ids(
            array_merge(
                $first_ids,
                $second_ids
            )
        ),
        'Pagination lost public articles.'
    );


    /*
     * Запрашиваем страницу
     * за пределами выборки.
     *
     * Третья страница отсутствует,
     * поскольку при размере страницы 2
     * три статьи занимают две страницы.
     */
    $page_three = dilitant_query_test_execute([
        'posts_per_page' => 2,
        'paged' => 3,
    ]);


    /*
     * Даже пустая страница должна
     * сохранять нормативные аргументы.
     */
    dilitant_query_test_assert_invariants(
        $page_three,
        $fixtures,
        $fixtures->expected_category_ids()
    );


    /*
     * Не требуем конкретного found_posts
     * для страницы за пределами выборки.
     *
     * На данной стадии сохраняется
     * штатная семантика WP_Query.
     *
     * HTTP 404 для такой страницы
     * относится к реализации AM-D05.
     */
    dilitant_query_test_assert_ids(
        $page_three,
        [],
        'Out-of-range page returned posts.'
    );
}


/**
 * AM-QI-08.
 *
 * Проверяет фактическую сортировку
 * публикаций по уникальному ID.
 *
 * Проверяем:
 *     - ASC;
 *     - DESC;
 *     - нормализацию нижнего регистра.
 */
function dilitant_query_test_id_ordering(
    DilitantArticleFixtures $fixtures
): void {
    /*
     * Независимо определяем ожидаемый
     * возрастающий порядок ID.
     */
    $expected =
        $fixtures->expected_published_post_ids();


    /*
     * Сортировка по возрастанию.
     */
    $ascending = dilitant_query_test_assert_selection(
        $fixtures,
        [
            'orderby' => 'ID',
            'order' => 'ASC',
        ],
        $expected
    );

    dilitant_test_assert_same(
        $expected,
        dilitant_query_test_ordered_ids(
            $ascending
        ),
        'Ascending ID ordering failed.'
    );


    /*
     * Сортировка по убыванию.
     */
    $descending = dilitant_query_test_assert_selection(
        $fixtures,
        [
            'orderby' => 'ID',
            'order' => 'DESC',
        ],
        $expected
    );

    dilitant_test_assert_same(
        array_reverse($expected),
        dilitant_query_test_ordered_ids(
            $descending
        ),
        'Descending ID ordering failed.'
    );


    /*
     * Проверяем нормализацию
     * направления сортировки.
     *
     * Значение asc должно интерпретироваться
     * как нормативное ASC.
     */
    $lowercase = dilitant_query_test_assert_selection(
        $fixtures,
        [
            'orderby' => 'ID',
            'order' => 'asc',
        ],
        $expected
    );

    dilitant_test_assert_same(
        $expected,
        dilitant_query_test_ordered_ids(
            $lowercase
        ),
        'Lowercase order normalization failed.'
    );
}


/**
 * AM-QI-09.
 *
 * Проверяет сохранение заданного
 * порядка публикаций при сортировке
 * orderby = post__in.
 *
 * Проверяем:
 *     - сохранение порядка включения;
 *     - устранение повторяющихся ID;
 *     - исключение посторонних записей;
 *     - сохранение относительного порядка
 *       после применения post__not_in.
 */
function dilitant_query_test_inclusion_ordering(
    DilitantArticleFixtures $fixtures
): void {
    /*
     * Передаём публикации в порядке,
     * отличающемся от сортировки по ID.
     *
     * Дополнительно включаем
     * постороннюю публикацию
     * и повторяющийся идентификатор.
     */
    $query = dilitant_query_test_assert_selection(
        $fixtures,
        [
            'orderby' => 'post__in',

            'post__in' => [
                $fixtures->grandchild_post,
                $fixtures->outside_post,
                $fixtures->root_post,
                $fixtures->child_post,
                $fixtures->grandchild_post,
            ],
        ],
        $fixtures->expected_published_post_ids()
    );


    /*
     * Проверяем фактический порядок.
     *
     * Посторонняя запись исключена,
     * повторяющийся ID устранён.
     */
    dilitant_test_assert_same(
        [
            $fixtures->grandchild_post,
            $fixtures->root_post,
            $fixtures->child_post,
        ],
        dilitant_query_test_ordered_ids(
            $query
        ),
        'Explicit inclusion order was not preserved.'
    );


    /*
     * Исключаем одну из публикаций.
     *
     * Относительный порядок оставшихся
     * объектов должен сохраниться.
     */
    $query = dilitant_query_test_assert_selection(
        $fixtures,
        [
            'orderby' => 'post__in',

            'post__in' => [
                $fixtures->grandchild_post,
                $fixtures->root_post,
                $fixtures->child_post,
            ],

            'post__not_in' => [
                $fixtures->root_post,
            ],
        ],
        [
            $fixtures->grandchild_post,
            $fixtures->child_post,
        ]
    );


    /*
     * Проверяем сохранение порядка
     * после применения исключений.
     */
    dilitant_test_assert_same(
        [
            $fixtures->grandchild_post,
            $fixtures->child_post,
        ],
        dilitant_query_test_ordered_ids(
            $query
        ),
        'Inclusion order changed after exclusion.'
    );
}


/**
 * AM-QI-10.
 *
 * Проверяет действительный поиск
 * публичных статей блога.
 *
 * Поиск не должен изменять обязательные
 * ограничения типа, статуса,
 * парольной защиты и категорий.
 */
function dilitant_query_test_search(
    DilitantArticleFixtures $fixtures
): void {
    /*
     * Идентификатор запуска присутствует
     * в заголовках всех десяти записей.
     *
     * Несмотря на совпадение поискового
     * запроса, модель должна возвратить
     * только три публичные статьи.
     */
    dilitant_query_test_assert_public_set(
        $fixtures,
        [
            's' => $fixtures->run_id(),
        ]
    );


    /*
     * Поиск конкретной статьи.
     */
    dilitant_query_test_assert_selection(
        $fixtures,
        [
            's' => 'AM Root Article',
        ],
        [
            $fixtures->root_post,
        ]
    );


    /*
     * Поиск защищённой паролем статьи
     * по её известному заголовку.
     *
     * Результат должен быть пустым.
     */
    $query = dilitant_query_test_execute([
        's' => 'AM Password Protected Article',
    ]);

    dilitant_query_test_assert_empty(
        $query
    );


    /*
     * Поиск записи посторонней категории.
     *
     * Совпадение заголовка не должно
     * обходить нормативное дерево.
     */
    $query = dilitant_query_test_execute([
        's' => 'AM Outside Article',
    ]);

    dilitant_query_test_assert_empty(
        $query
    );


    /*
     * Поиск с сортировкой
     * по релевантности.
     *
     * Проверяем состав результатов,
     * не устанавливая собственные
     * требования к алгоритму релевантности.
     */
    dilitant_query_test_assert_public_set(
        $fixtures,
        [
            's' => $fixtures->run_id(),
            'orderby' => 'relevance',
        ]
    );


    /*
     * Пустой поисковый запрос
     * не должен изменять состав
     * базовой публичной выборки.
     */
    dilitant_query_test_assert_public_set(
        $fixtures,
        [
            's' => '',
        ]
    );
}