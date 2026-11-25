<?php
/**
 * AM-QI — Дополнительные интеграционные
 * испытания Article Model.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D02 — Защита инвариантов WP_Query
 *
 * Ответственность:
 *     - подключение функциональных компонентов;
 *     - регистрация всех сценариев AM-QI;
 *     - проверка закрытого отказа;
 *     - проверка обязательных ограничений;
 *     - проверка административного контекста.
 *
 * Собственные сценарии:
 *     AM-QI-02 — некорректные аргументы;
 *     AM-QI-03 — пустое включение;
 *     AM-QI-11 — запрещённые аргументы;
 *     AM-QI-12 — недопустимые категории;
 *     AM-QI-13 — административный контекст.
 *
 * Общее количество сценариев AM-QI: 13.
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


/*
 * Испытания состава выборки.
 */
require_once __DIR__
    . '/test-integration-selection.php';


/*
 * Испытания пагинации, сортировки и поиска.
 */
require_once __DIR__
    . '/test-integration-navigation.php';


/**
 * Формирует полный набор испытаний AM-QI.
 *
 * Публичный интерфейс сохраняется
 * для существующего runner.php.
 *
 * Все сценарии регистрируются явно.
 * Порядок нормативных идентификаторов
 * сохраняется.
 */
function dilitant_query_test_cases_integration(
    DilitantArticleFixtures $fixtures
): array {
    return [

        /*
         * AM-QI-01.
         *
         * Базовая публичная выборка.
         */
        'AM-QI-01 baseline public query' =>
            static function () use ($fixtures): void {
                dilitant_query_test_baseline_selection(
                    $fixtures
                );
            },


        /*
         * AM-QI-02.
         *
         * Некорректные аргументы.
         */
        'AM-QI-02 invalid arguments fail closed' =>
            'dilitant_query_test_invalid_arguments',


        /*
         * AM-QI-03.
         *
         * Пустое включение.
         */
        'AM-QI-03 empty inclusion' =>
            'dilitant_query_test_empty_inclusion',


        /*
         * AM-QI-04.
         *
         * Исключение записей.
         */
        'AM-QI-04 exclusion filter' =>
            static function () use ($fixtures): void {
                dilitant_query_test_exclusion(
                    $fixtures
                );
            },


        /*
         * AM-QI-05.
         *
         * Приоритет исключений.
         */
        'AM-QI-05 inclusion exclusion precedence' =>
            static function () use ($fixtures): void {
                dilitant_query_test_combined_ids(
                    $fixtures
                );
            },


        /*
         * AM-QI-06.
         *
         * Полное исключение записей.
         */
        'AM-QI-06 complete exclusion' =>
            static function () use ($fixtures): void {
                dilitant_query_test_complete_exclusion(
                    $fixtures
                );
            },


        /*
         * AM-QI-07.
         *
         * Пагинация.
         */
        'AM-QI-07 pagination' =>
            static function () use ($fixtures): void {
                dilitant_query_test_pagination(
                    $fixtures
                );
            },


        /*
         * AM-QI-08.
         *
         * Сортировка по ID.
         */
        'AM-QI-08 ID ordering' =>
            static function () use ($fixtures): void {
                dilitant_query_test_id_ordering(
                    $fixtures
                );
            },


        /*
         * AM-QI-09.
         *
         * Сортировка по порядку включения.
         */
        'AM-QI-09 inclusion ordering' =>
            static function () use ($fixtures): void {
                dilitant_query_test_inclusion_ordering(
                    $fixtures
                );
            },


        /*
         * AM-QI-10.
         *
         * Поиск публичных статей.
         */
        'AM-QI-10 public article search' =>
            static function () use ($fixtures): void {
                dilitant_query_test_search(
                    $fixtures
                );
            },


        /*
         * AM-QI-11.
         *
         * Комбинированные запрещённые аргументы.
         */
        'AM-QI-11 combined forbidden arguments' =>
            static function () use ($fixtures): void {
                dilitant_query_test_combined_forbidden(
                    $fixtures
                );
            },


        /*
         * AM-QI-12.
         *
         * Недопустимые идентификаторы категорий.
         */
        'AM-QI-12 invalid category identifiers' =>
            static function () use ($fixtures): void {
                dilitant_query_test_invalid_categories(
                    $fixtures
                );
            },


        /*
         * AM-QI-13.
         *
         * Административный контекст.
         */
        'AM-QI-13 privileged user context' =>
            static function () use ($fixtures): void {
                dilitant_query_test_privileged_context(
                    $fixtures
                );
            },
    ];
}


/**
 * AM-QI-02.
 *
 * Проверяет закрытый отказ
 * при некорректных аргументах.
 */
function dilitant_query_test_invalid_arguments(): void
{
    $invalid_cases = [

        ['posts_per_page' => 0],

        ['posts_per_page' => -1],

        ['posts_per_page' => 101],

        ['posts_per_page' => true],

        ['posts_per_page' => 1.5],

        ['posts_per_page' => '1e2'],

        ['paged' => 0],

        ['paged' => -1],

        ['paged' => []],

        ['paged' => (string) PHP_INT_MAX . '0'],

        [
            'posts_per_page' => 100,
            'paged' => PHP_INT_MAX,
        ],

        ['orderby' => 'rand'],

        ['orderby' => ['date' => 'DESC']],

        ['order' => 'INVALID'],

        ['s' => []],

        ['s' => str_repeat('a', 501)],

        ['post__in' => [-1]],

        ['post__not_in' => [0]],

        ['post__in' => '1,2,3'],

        ['post__not_in' => false],

        ['orderby' => 'post__in'],

        ['orderby' => 'relevance'],
    ];


    /*
     * Проверяем некорректные значения
     * поддерживаемых аргументов.
     */
    foreach ($invalid_cases as $args) {
        try {
            $query = dilitant_query_test_execute(
                $args
            );

            dilitant_query_test_assert_closed_failure(
                $query
            );

        } catch (Throwable $error) {
            throw new RuntimeException(
                "Invalid argument case failed:\n"
                . var_export($args, true)
                . "\n"
                . $error->getMessage(),
                0,
                $error
            );
        }
    }


    /*
     * Проверяем некорректный тип
     * исходного набора аргументов.
     */
    $invalid_inputs = [
        null,
        true,
        123,
        'posts_per_page=10',
        new stdClass(),
    ];

    foreach ($invalid_inputs as $input) {
        $query =
            dilitant_land_get_blog_articles(
                $input
            );

        dilitant_query_test_assert_closed_failure(
            $query
        );
    }
}


/**
 * AM-QI-03.
 *
 * Проверяет закрытый отказ
 * при пустом post__in.
 *
 * Пустой список включённых записей
 * не должен превращаться в запрос
 * всех опубликованных статей.
 */
function dilitant_query_test_empty_inclusion(): void
{
    $query = dilitant_query_test_execute([
        'post__in' => [],
    ]);

    dilitant_query_test_assert_closed_failure(
        $query
    );
}


/**
 * AM-QI-11.
 *
 * Проверяет одновременную передачу
 * многочисленных запрещённых аргументов.
 */
function dilitant_query_test_combined_forbidden(
    DilitantArticleFixtures $fixtures
): void {
    $query = dilitant_query_test_assert_selection(
        $fixtures,
        [
            'post_type' => 'any',

            'post_status' => 'any',

            'has_password' => true,

            'post_password' =>
                $fixtures->fixture_password(),

            'ignore_sticky_posts' => false,

            'tax_query' => [
                [
                    'taxonomy' => 'category',
                    'field' => 'term_id',

                    'terms' => [
                        $fixtures->outside_category,
                    ],
                ],
            ],

            'cat' =>
                $fixtures->outside_category,

            'p' =>
                $fixtures->outside_post,

            'page_id' =>
                $fixtures->page_post,

            'name' => 'nonexistent-test-post',

            'author' => -1,

            'offset' => 1000,

            'nopaging' => true,

            'posts_per_archive_page' => -1,

            'showposts' => -1,

            'fields' => 'ids',

            'no_found_rows' => true,

            'suppress_filters' => true,

            'meta_query' => [
                [
                    'key' => '_am_nonexistent_meta',
                    'compare' => 'EXISTS',
                ],
            ],
        ],
        $fixtures->expected_published_post_ids()
    );


    /*
     * Вызывающий код не должен отключать
     * подсчёт общего количества публикаций.
     */
    dilitant_test_assert_same(
        3,
        (int) $query->found_posts,
        'Caller disabled required article counting.'
    );


    /*
     * Проверяем количество страниц.
     */
    dilitant_test_assert_same(
        1,
        (int) $query->max_num_pages,
        'Unexpected page count after forbidden arguments.'
    );


    /*
     * Проверяем сохранение допустимого
     * размера страницы.
     */
    dilitant_test_assert_same(
        100,
        (int) $query->get('posts_per_page'),
        'Forbidden arguments changed page size.'
    );
}


/**
 * AM-QI-12.
 *
 * Проверяет недопустимые category_id.
 */
function dilitant_query_test_invalid_categories(
    DilitantArticleFixtures $fixtures
): void {
    $invalid_categories = [
        0,
        -1,
        true,
        false,
        null,
        [],
        '',
        1.5,
        'invalid',
        (string) PHP_INT_MAX . '0',
        $fixtures->outside_category,
    ];

    foreach ($invalid_categories as $category_id) {
        try {
            $query = dilitant_query_test_execute([
                'category_id' => $category_id,
            ]);

            dilitant_query_test_assert_closed_failure(
                $query
            );

        } catch (Throwable $error) {
            throw new RuntimeException(
                "Invalid category case failed:\n"
                . var_export($category_id, true)
                . "\n"
                . $error->getMessage(),
                0,
                $error
            );
        }
    }
}


/**
 * AM-QI-13.
 *
 * Публичная политика выборки не должна
 * зависеть от административных полномочий.
 */
function dilitant_query_test_privileged_context(
    DilitantArticleFixtures $fixtures
): void {
    $previous_user_id = get_current_user_id();

    $administrator = get_user_by(
        'login',
        'testadmin'
    );

    dilitant_test_assert(
        $administrator instanceof WP_User,
        'Test administrator is missing.'
    );

    try {
        /*
         * Переключаемся на тестового
         * администратора.
         */
        wp_set_current_user(
            (int) $administrator->ID
        );

        dilitant_test_assert(
            current_user_can('manage_options'),
            'Test user is not an administrator.'
        );


        /*
         * Проверяем базовую публичную выборку.
         */
        dilitant_query_test_assert_public_set(
            $fixtures,
            []
        );


        /*
         * Проверяем попытку расширения
         * публичной выборки посредством
         * административных полномочий.
         */
        dilitant_query_test_assert_public_set(
            $fixtures,
            [
                'post_status' => 'any',
                'has_password' => true,
                'perm' => 'readable',
            ]
        );


        /*
         * Проверяем явное включение
         * всех запрещённых объектов.
         */
        $query = dilitant_query_test_execute([
            'post_status' => 'any',

            'has_password' => true,

            'post__in' =>
                $fixtures->excluded_post_ids(),
        ]);

        dilitant_query_test_assert_empty(
            $query
        );

    } finally {
        /*
         * Восстанавливаем прежний контекст
         * независимо от результата испытания.
         */
        wp_set_current_user(
            $previous_user_id
        );
    }


    /*
     * Подтверждаем восстановление контекста.
     */
    dilitant_test_assert_same(
        $previous_user_id,
        get_current_user_id(),
        'Original user context was not restored.'
    );
}