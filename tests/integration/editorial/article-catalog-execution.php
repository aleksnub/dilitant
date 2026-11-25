<?php
/**
 * Исполнение запросов интеграционных
 * испытаний публичного каталога.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D02 — Защита инвариантов WP_Query
 *
 * Ответственность:
 *     - создание экземпляров WP_Query;
 *     - назначение главного запроса;
 *     - исполнение главного запроса;
 *     - исполнение вторичных запросов;
 *     - воспроизведение аргументов каталога;
 *     - наблюдение за pre_get_posts;
 *     - контроль идентичности наблюдаемого
 *       экземпляра WP_Query;
 *     - удаление временных наблюдателей.
 *
 * Производственный обработчик:
 *
 *     dilitant_land_apply_blog_catalog_query()
 *
 * зарегистрирован на pre_get_posts
 * с приоритетом 100.
 *
 * Компонент не запускает испытания
 * и не управляет жизненным циклом Fixtures.
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
 * Исполняет конкретный экземпляр WP_Query.
 *
 * При main_query = true испытуемый
 * экземпляр назначается главным запросом
 * до вызова метода query().
 *
 * При main_query = false создаётся
 * отдельный объект главного запроса.
 *
 * Признаки is_category, is_archive,
 * is_single и другие определяются
 * непосредственно WordPress.
 *
 * Признаки запроса вручную
 * не устанавливаются.
 *
 * @param WP_Query $query      Испытуемый объект.
 * @param array    $args       Аргументы запроса.
 * @param bool     $main_query Роль запроса.
 *
 * @return WP_Query
 */
function dilitant_catalog_execute_instance(
    WP_Query $query,
    array $args,
    bool $main_query
): WP_Query {
    /*
     * Сохраняем исходное окружение
     * до назначения главного запроса.
     */
    $state =
        dilitant_catalog_capture_globals();

    try {
        if ($main_query) {
            /*
             * Назначаем испытуемый объект
             * главным запросом WordPress.
             */
            $GLOBALS['wp_query'] =
                $query;

            $GLOBALS['wp_the_query'] =
                $query;

        } else {
            /*
             * Для вторичного запроса
             * создаём отдельный объект,
             * представляющий главный запрос.
             *
             * Сам этот объект не исполняется.
             */
            $main = new WP_Query();

            $GLOBALS['wp_query'] =
                $main;

            $GLOBALS['wp_the_query'] =
                $main;
        }


        /*
         * Проверяем назначенную роль
         * до исполнения запроса.
         */
        dilitant_test_assert_same(
            $main_query,
            $query->is_main_query(),
            'Incorrect main query assignment.'
        );


        /*
         * Выполняем настоящий WP_Query.
         *
         * WordPress самостоятельно выполняет:
         *
         *     parse_query();
         *     pre_get_posts;
         *     построение SQL;
         *     получение записей;
         *     подсчёт результатов.
         */
        $query->query(
            $args
        );


        /*
         * Проверяем, что роль объекта
         * не изменилась при исполнении.
         */
        dilitant_test_assert_same(
            $main_query,
            $query->is_main_query(),
            'Query role changed during execution.'
        );


        /*
         * Возвращаем тот же экземпляр
         * с фактическими результатами.
         */
        return $query;

    } finally {
        /*
         * Восстанавливаем исходное
         * глобальное состояние независимо
         * от результата WP_Query.
         */
        dilitant_catalog_restore_globals(
            $state
        );


        /*
         * Подтверждаем восстановление.
         */
        dilitant_catalog_verify_globals(
            $state
        );
    }
}


/**
 * Создаёт и выполняет WP_Query.
 *
 * По умолчанию запрос выполняется
 * в роли главного запроса WordPress.
 *
 * @param array $args       Аргументы запроса.
 * @param bool  $main_query Роль запроса.
 *
 * @return WP_Query
 */
function dilitant_catalog_execute_query(
    array $args,
    bool $main_query = true
): WP_Query {
    $query = new WP_Query();

    return dilitant_catalog_execute_instance(
        $query,
        $args,
        $main_query
    );
}


/**
 * Выполняет главный запрос
 * публичного каталога статей.
 *
 * Воспроизводит query variables
 * действующего rewrite rule:
 *
 *     index.php?category_name=diletant-armenia
 *
 * Дополнительные аргументы используются
 * для положительных и отрицательных
 * интеграционных испытаний.
 *
 * Аргументы намеренно не нормализуются
 * тестовым исполнителем.
 *
 * Нормативную обработку обязан выполнить
 * производственный компонент catalog-query.php.
 *
 * @param array $args Дополнительные аргументы.
 *
 * @return WP_Query
 */
function dilitant_catalog_execute(
    array $args = []
): WP_Query {
    /*
     * Базовые аргументы,
     * соответствующие маршруту каталога.
     */
    $defaults = [
        'category_name' =>
            DILITANT_LAND_BLOG_CATEGORY_SLUG,
    ];


    /*
     * Разрешаем испытаниям передавать
     * конфликтующие аргументы.
     *
     * Проверяется именно способность
     * производственного обработчика
     * устранить недопустимые условия.
     */
    $query_args = array_replace(
        $defaults,
        $args
    );


    /*
     * Исполняем запрос как главный.
     */
    return dilitant_catalog_execute_query(
        $query_args,
        true
    );
}


/**
 * Исполняет WP_Query с наблюдением
 * за производственным обработчиком.
 *
 * Производственный обработчик:
 *
 *     pre_get_posts;
 *     priority = 100.
 *
 * Наблюдатель до обработчика:
 *
 *     priority = 99.
 *
 * Наблюдатель после обработчика:
 *
 *     priority = 101.
 *
 * Оба наблюдателя работают только
 * с заранее созданным экземпляром.
 *
 * Вложенные и посторонние WP_Query
 * не могут подменить наблюдаемый объект.
 *
 * @param array $args          Аргументы запроса.
 * @param bool  $main_query    Роль запроса.
 * @param bool  $expect_change Ожидаемое вмешательство.
 *
 * @return WP_Query
 */
function dilitant_catalog_observe_query(
    array $args,
    bool $main_query,
    bool $expect_change
): WP_Query {
    /*
     * Создаём испытуемый объект
     * до регистрации наблюдателей.
     */
    $target = new WP_Query();


    /*
     * Снимки состояния запроса.
     */
    $before = null;

    $after = null;


    /*
     * Счётчики наблюдений.
     *
     * Каждый наблюдатель должен
     * обработать испытуемый объект
     * ровно один раз.
     */
    $before_count = 0;

    $after_count = 0;


    /*
     * Наблюдатель непосредственно
     * перед производственным обработчиком.
     */
    $before_hook = static function (
        WP_Query $query
    ) use (
        $target,
        &$before,
        &$before_count
    ): void {
        /*
         * Игнорируем все запросы,
         * кроме целевого экземпляра.
         */
        if ($query !== $target) {
            return;
        }

        $before_count++;


        /*
         * Получаем снимок состояния
         * до применения политики.
         */
        $before =
            dilitant_catalog_capture_query_state(
                $query
            );
    };


    /*
     * Наблюдатель непосредственно
     * после производственного обработчика.
     */
    $after_hook = static function (
        WP_Query $query
    ) use (
        $target,
        &$after,
        &$after_count
    ): void {
        /*
         * Исключаем посторонние
         * и вложенные запросы.
         */
        if ($query !== $target) {
            return;
        }

        $after_count++;


        /*
         * Получаем снимок состояния
         * после применения политики.
         */
        $after =
            dilitant_catalog_capture_query_state(
                $query
            );
    };


    /*
     * Регистрируем наблюдателя
     * перед производственным обработчиком.
     */
    add_action(
        'pre_get_posts',
        $before_hook,
        99,
        1
    );


    /*
     * Регистрируем наблюдателя
     * после производственного обработчика.
     */
    add_action(
        'pre_get_posts',
        $after_hook,
        101,
        1
    );

    try {
        /*
         * Исполняем именно тот экземпляр,
         * который отслеживают наблюдатели.
         */
        $query =
            dilitant_catalog_execute_instance(
                $target,
                $args,
                $main_query
            );


        /*
         * Проверяем идентичность
         * возвращённого экземпляра.
         */
        dilitant_test_assert_same(
            $target,
            $query,
            'Unexpected observed query instance.'
        );


        /*
         * Проверяем полноту наблюдения.
         */
        dilitant_test_assert_same(
            1,
            $before_count,
            'Missing or repeated pre-handler observation.'
        );

        dilitant_test_assert_same(
            1,
            $after_count,
            'Missing or repeated post-handler observation.'
        );


        /*
         * Оба снимка должны существовать.
         */
        dilitant_test_assert(
            is_array($before)
            && is_array($after),
            'Query observation is incomplete.'
        );


        /*
         * Проверяем ожидаемый эффект
         * производственного обработчика.
         */
        if ($expect_change) {
            /*
             * Для целевого запроса
             * требуется фактическое
             * изменение аргументов.
             */
            dilitant_catalog_assert_policy_applied(
                $before,
                $after
            );

        } else {
            /*
             * Для постороннего запроса
             * требуется невмешательство.
             */
            dilitant_catalog_assert_unchanged(
                $before,
                $after
            );
        }


        /*
         * Возвращаем фактические
         * результаты SQL-выборки.
         */
        return $query;

    } finally {
        /*
         * Удаляем оба временных
         * наблюдателя независимо
         * от результата испытания.
         */
        $before_removed = remove_action(
            'pre_get_posts',
            $before_hook,
            99
        );

        $after_removed = remove_action(
            'pre_get_posts',
            $after_hook,
            101
        );


        /*
         * Подтверждаем удаление
         * первого наблюдателя.
         */
        dilitant_test_assert_same(
            true,
            $before_removed,
            'Pre-handler observer was not removed.'
        );


        /*
         * Подтверждаем удаление
         * второго наблюдателя.
         */
        dilitant_test_assert_same(
            true,
            $after_removed,
            'Post-handler observer was not removed.'
        );
    }
}