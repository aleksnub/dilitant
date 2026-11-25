<?php
/**
 * Регистрация интеграционных
 * испытаний AM-D03.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *
 * Ответственность:
 *     - определение нормативного
 *       перечня сценариев;
 *     - определение порядка исполнения;
 *     - проверка обязательных интерфейсов;
 *     - регистрация исполняемых функций;
 *     - проверка полноты регистрации.
 *
 * Компонент не исполняет сценарии
 * и не изменяет тестовые данные.
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
 * Возвращает точный нормативный
 * перечень идентификаторов испытаний.
 */
function dilitant_visibility_expected_ids(): array
{
    $ids = [];

    $groups = [
        'AM-T09' => 9,
        'AM-T10' => 3,
        'AM-T11' => 8,
    ];

    foreach (
        $groups as $prefix => $count
    ) {
        for (
            $index = 1;
            $index <= $count;
            $index++
        ) {
            $ids[] =
                $prefix . '.' . $index;
        }
    }

    return $ids;
}


/**
 * Возвращает нормативный порядок
 * исполнения сценариев.
 *
 * AM-T09.9 удаляет корневую категорию
 * блога и поэтому должен выполняться
 * после всех остальных испытаний.
 */
function dilitant_visibility_execution_order(): array
{
    $ids = array_values(
        array_diff(
            dilitant_visibility_expected_ids(),
            ['AM-T09.9']
        )
    );

    $ids[] = 'AM-T09.9';

    return $ids;
}


/**
 * Проверяет наличие обязательных
 * производственных и тестовых интерфейсов.
 */
function dilitant_visibility_verify_interfaces(): void
{
    dilitant_visibility_verify_environment();

    $functions = [
        /*
         * Производственные интерфейсы.
         */
        'dilitant_land_is_blog_article',
        'dilitant_land_is_public_blog_article',
        'dilitant_land_can_preview_blog_article',
        'dilitant_land_can_render_blog_article',
        'dilitant_land_reject_blog_article',
        'dilitant_land_enforce_blog_article_visibility',
        'dilitant_land_get_blog_articles',
        'dilitant_land_get_blog_category_id',
        'dilitant_land_get_blog_category_ids',


        /*
         * Общая тестовая инфраструктура.
         */
        'dilitant_test_assert',
        'dilitant_test_assert_same',
        'dilitant_test_query_ids',
        'dilitant_catalog_capture_globals',
        'dilitant_catalog_restore_globals',
        'dilitant_catalog_verify_globals',
        'dilitant_catalog_capture_query_state',
        'dilitant_catalog_assert_unchanged',


        /*
         * Окружение AM-D03.
         */
        'dilitant_visibility_verify_environment',
        'dilitant_visibility_with_user',
        'dilitant_visibility_with_trashed_post',
        'dilitant_visibility_with_cookie',


        /*
         * Общие проверки AM-D03.
         */
        'dilitant_visibility_assert_identity',
        'dilitant_visibility_assert_post',
        'dilitant_visibility_assert_edit_permission',
        'dilitant_visibility_assert_preview',
        'dilitant_visibility_assert_public_query',
        'dilitant_visibility_assert_excluded',


        /*
         * Маршрутизация и Preview.
         */
        'dilitant_visibility_with_query',
        'dilitant_visibility_with_post_context',
        'dilitant_visibility_assert_handler_decision',
        'dilitant_visibility_with_request_args',
        'dilitant_visibility_capture_wp_die',
        'dilitant_visibility_check_native_nonce',


        /*
         * Штатные интерфейсы WordPress.
         */
        'wp_create_nonce',
        'wp_verify_nonce',
        '_show_post_preview',
        'current_user_can',
        'post_password_required',
    ];

    foreach ($functions as $function) {
        dilitant_test_assert(
            function_exists($function),
            "Required visibility function is missing: {$function}"
        );
    }


    /*
     * Проверяем классы Fixtures.
     */
    dilitant_test_assert(
        class_exists(
            DilitantArticleFixtures::class
        ),
        'Article fixtures class is missing.'
    );

    dilitant_test_assert(
        class_exists(
            DilitantVisibilityUsers::class
        ),
        'Visibility users class is missing.'
    );

    dilitant_test_assert(
        class_exists(
            DilitantVisibilityWpDie::class
        ),
        'Visibility wp_die exception class is missing.'
    );


    /*
     * Проверяем обязательные методы
     * существующего набора Fixtures.
     */
    $methods = [
        'create',
        'create_query_fixtures',
        'verify_query_fixtures',
        'all_post_ids',
        'expected_category_ids',
        'expected_published_post_ids',
        'excluded_post_ids',
        'remove_root_category',
        'archive_post_type',
        'fixture_password',
        'cleanup',
    ];

    foreach ($methods as $method) {
        dilitant_test_assert(
            method_exists(
                DilitantArticleFixtures::class,
                $method
            ),
            "Required fixture method is missing: {$method}"
        );
    }


    /*
     * Проверяем регистрацию
     * производственных обработчиков.
     */
    dilitant_test_assert_same(
        1,
        has_action(
            'template_redirect',
            'dilitant_land_enforce_blog_article_visibility'
        ),
        'Visibility handler is not registered.'
    );

    dilitant_test_assert_same(
        10,
        has_filter(
            'post_link',
            'dilitant_land_filter_blog_post_permalink'
        ),
        'Article permalink filter is not registered.'
    );


    /*
     * Подтверждаем наличие штатного
     * механизма проверки Preview nonce.
     */
    dilitant_test_assert(
        has_action(
            'init',
            '_show_post_preview'
        ) !== false,
        'Native WordPress preview handler is not registered.'
    );


    /*
     * Испытания выполняются в публичном
     * контексте WordPress, а не в wp-admin.
     */
    dilitant_test_assert_same(
        false,
        is_admin(),
        'Unexpected administrative test context.'
    );

    echo "PASS: required visibility interfaces\n";
}


/**
 * Регистрирует нормативные сценарии.
 *
 * Для каждого идентификатора
 * должна существовать соответствующая
 * исполняемая функция.
 */
function dilitant_visibility_register_tests(
    DilitantArticleFixtures $fixtures,
    DilitantVisibilityUsers $users
): array {
    $tests = [];

    foreach (
        dilitant_visibility_execution_order()
        as $id
    ) {
        /*
         * Преобразование идентификатора
         * в имя исполняемой функции.
         *
         * Например:
         *
         *     AM-T09.1
         *
         *     dilitant_visibility_test_t09_1
         */
        $suffix = strtolower(
            str_replace(
                '.',
                '_',
                substr($id, 3)
            )
        );

        $function =
            'dilitant_visibility_test_'
            . $suffix;


        /*
         * Каждый нормативный сценарий
         * должен иметь реализацию.
         */
        dilitant_test_assert(
            function_exists($function),
            "Required scenario is missing: {$id}"
        );

        dilitant_test_assert(
            is_callable($function),
            "Required scenario is not callable: {$id}"
        );

        dilitant_test_assert(
            !array_key_exists(
                $id,
                $tests
            ),
            "Duplicate scenario identifier: {$id}"
        );


        /*
         * Сценарии AM-T10.3 и AM-T11.2–8
         * используют дополнительных
         * пользователей тестового окружения.
         *
         * AM-T11.1 выполняется анонимно.
         */
        $requires_users =
            $id === 'AM-T10.3'
            || (
                str_starts_with(
                    $id,
                    'AM-T11.'
                )
                && $id !== 'AM-T11.1'
            );


        /*
         * Формируем единый исполняемый
         * интерфейс для всех сценариев.
         */
        $tests[$id] =
            static function () use (
                $function,
                $fixtures,
                $users,
                $requires_users
            ): void {
                if ($requires_users) {
                    $function(
                        $fixtures,
                        $users
                    );

                } else {
                    $function(
                        $fixtures
                    );
                }
            };
    }


    /*
     * Проверяем нормативное количество
     * зарегистрированных сценариев.
     */
    dilitant_test_assert_same(
        DILITANT_VISIBILITY_EXPECTED_TOTAL,
        count($tests),
        'Unexpected visibility scenario count.'
    );


    /*
     * Проверяем точное множество
     * зарегистрированных идентификаторов.
     *
     * Совпадения одного количества
     * недостаточно.
     */
    $expected =
        dilitant_visibility_expected_ids();

    $actual = array_keys(
        $tests
    );

    sort($expected);
    sort($actual);

    dilitant_test_assert_same(
        $expected,
        $actual,
        'Unexpected visibility scenario manifest.'
    );


    /*
     * Проверяем нормативный порядок.
     */
    dilitant_test_assert_same(
        dilitant_visibility_execution_order(),
        array_keys($tests),
        'Unexpected visibility execution order.'
    );


    /*
     * Разрушающий сценарий обязательно
     * должен выполняться последним.
     */
    dilitant_test_assert_same(
        'AM-T09.9',
        array_key_last($tests),
        'Root removal scenario must execute last.'
    );

    return $tests;
}