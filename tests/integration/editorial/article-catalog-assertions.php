<?php
/**
 * Проверки интеграционных испытаний
 * публичного каталога Article Model.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D02 — Защита инвариантов WP_Query
 *     AM-D05 — Пагинация каталога
 *
 * Ответственность:
 *     - проверка идентичности каталога;
 *     - проверка нормативной политики;
 *     - проверка фактического состава выборки;
 *     - проверка общего количества статей;
 *     - получение снимков состояния WP_Query;
 *     - проверка применения обработчика;
 *     - проверка невмешательства;
 *     - независимое определение
 *       ожидаемого порядка публикаций.
 *
 * Общие проверки Article Model
 * предоставляются компонентом:
 *
 *     article-query/assertions.php.
 *
 * Компонент не создаёт WP_Query,
 * не исполняет запросы самостоятельно
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
 * Проверяет идентичность
 * публичного каталога.
 *
 * Проверяется сохранение:
 *
 *     - признака архива категории;
 *     - признака архива;
 *     - отсутствия признака отдельной записи;
 *     - отсутствия признака поиска;
 *     - отсутствия признака RSS;
 *     - отсутствия признака главной страницы;
 *     - идентичности корневой категории.
 *
 * Идентичность queried_object необходима
 * WordPress для выбора шаблона
 * и формирования навигации.
 *
 * @param WP_Query                $query
 * @param DilitantArticleFixtures $fixtures
 *
 * @return void
 */
function dilitant_catalog_assert_identity(
    WP_Query $query,
    DilitantArticleFixtures $fixtures
): void {
    /*
     * Каталог должен сохранить
     * идентичность архива категории.
     */
    dilitant_test_assert_same(
        true,
        $query->is_category(),
        'Catalog lost category archive identity.'
    );


    /*
     * Каталог должен оставаться архивом.
     */
    dilitant_test_assert_same(
        true,
        $query->is_archive(),
        'Catalog lost archive identity.'
    );


    /*
     * Каталог не является запросом
     * отдельной публикации.
     */
    dilitant_test_assert_same(
        false,
        $query->is_singular(),
        'Catalog became singular query.'
    );


    /*
     * Каталог не является
     * глобальным поисковым запросом.
     */
    dilitant_test_assert_same(
        false,
        $query->is_search(),
        'Catalog became search query.'
    );


    /*
     * Каталог не является RSS-лентой.
     */
    dilitant_test_assert_same(
        false,
        $query->is_feed(),
        'Catalog became feed query.'
    );


    /*
     * Каталог не является главной
     * страницей записей WordPress.
     */
    dilitant_test_assert_same(
        false,
        $query->is_home(),
        'Catalog became home query.'
    );


    /*
     * Получаем запрошенный объект
     * после исполнения WP_Query.
     */
    $term =
        $query->get_queried_object();


    /*
     * Объект должен представлять
     * действительную категорию.
     */
    dilitant_test_assert(
        $term instanceof WP_Term,
        'Catalog queried object is missing.'
    );


    /*
     * Проверяем тип таксономии.
     */
    dilitant_test_assert_same(
        'category',
        $term->taxonomy,
        'Catalog queried object has invalid taxonomy.'
    );


    /*
     * Проверяем идентификатор
     * корневой категории.
     */
    dilitant_test_assert_same(
        $fixtures->root_category,
        (int) $term->term_id,
        'Catalog lost root category identity.'
    );


    /*
     * Проверяем согласованность
     * queried_object_id с объектом.
     */
    dilitant_test_assert_same(
        $fixtures->root_category,
        (int) $query->get_queried_object_id(),
        'Catalog queried object ID is incorrect.'
    );


    /*
     * Проверяем нормативный slug.
     */
    dilitant_test_assert_same(
        DILITANT_LAND_BLOG_CATEGORY_SLUG,
        $term->slug,
        'Unexpected catalog category slug.'
    );
}


/**
 * Проверяет нормативную политику
 * и фактический состав каталога.
 *
 * Проверяются три независимых аспекта:
 *
 *     1. Обязательные инварианты.
 *     2. Точный состав выборки.
 *     3. Идентичность каталога.
 *
 * @param WP_Query                $query
 * @param DilitantArticleFixtures $fixtures
 * @param array                   $expected_ids
 *
 * @return void
 */
function dilitant_catalog_assert_selection(
    WP_Query $query,
    DilitantArticleFixtures $fixtures,
    array $expected_ids
): void {
    /*
     * Проверяем общие инварианты
     * публичной выборки Article Model.
     *
     * Проверка использует ранее
     * испытанный компонент Article Query.
     */
    dilitant_query_test_assert_invariants(
        $query,
        $fixtures,
        $fixtures->expected_category_ids()
    );


    /*
     * Проверяем точное множество
     * полученных публикаций.
     */
    dilitant_query_test_assert_ids(
        $query,
        $expected_ids,
        'Unexpected catalog article set.'
    );


    /*
     * Проверяем сохранение идентичности
     * архива корневой категории.
     */
    dilitant_catalog_assert_identity(
        $query,
        $fixtures
    );
}


/**
 * Проверяет полный набор
 * публичных тестовых статей.
 *
 * Ожидаемое множество определяется
 * непосредственно Fixtures.
 *
 * Article Model не используется
 * для определения ожидаемого результата.
 *
 * @param WP_Query                $query
 * @param DilitantArticleFixtures $fixtures
 *
 * @return void
 */
function dilitant_catalog_assert_full_set(
    WP_Query $query,
    DilitantArticleFixtures $fixtures
): void {
    /*
     * Независимое ожидаемое множество.
     */
    $expected =
        $fixtures->expected_published_post_ids();


    /*
     * Проверяем точную выборку
     * и обязательные инварианты.
     */
    dilitant_catalog_assert_selection(
        $query,
        $fixtures,
        $expected
    );


    /*
     * Проверяем общее количество
     * найденных публичных статей.
     */
    dilitant_test_assert_same(
        count($expected),
        (int) $query->found_posts,
        'Unexpected total catalog article count.'
    );
}


/**
 * Формирует снимок наблюдаемого запроса.
 *
 * Используется наблюдателями,
 * зарегистрированными до и после
 * производственного обработчика.
 *
 * Метод get_queried_object()
 * намеренно не вызывается.
 *
 * Этот метод может самостоятельно
 * инициализировать внутренний кэш,
 * изменяя состояние испытуемого объекта.
 *
 * Наблюдение должно оставаться
 * пассивным.
 *
 * @param WP_Query $query
 *
 * @return array
 */
function dilitant_catalog_capture_query_state(
    WP_Query $query
): array {
    /*
     * Читаем уже существующий
     * кэш запрошенного объекта.
     */
    $queried_object =
        $query->queried_object ?? null;


    /*
     * Сохраняем идентификационные
     * свойства объекта.
     *
     * Простого сохранения ссылки
     * недостаточно, поскольку объект
     * может быть впоследствии изменён.
     */
    $object_identity = null;


    /*
     * Идентичность объекта категории.
     */
    if ($queried_object instanceof WP_Term) {
        $object_identity = [
            'type' => 'WP_Term',

            'term_id' =>
                (int) $queried_object->term_id,

            'taxonomy' =>
                $queried_object->taxonomy,

            'slug' =>
                $queried_object->slug,
        ];


    /*
     * Идентичность отдельной публикации.
     */
    } elseif ($queried_object instanceof WP_Post) {
        $object_identity = [
            'type' => 'WP_Post',

            'ID' =>
                (int) $queried_object->ID,

            'post_type' =>
                $queried_object->post_type,
        ];


    /*
     * Идентичность других объектов.
     */
    } elseif (is_object($queried_object)) {
        $object_identity = [
            'type' =>
                get_class(
                    $queried_object
                ),

            'object_id' =>
                spl_object_id(
                    $queried_object
                ),
        ];
    }


    /*
     * Формируем снимок аргументов,
     * признаков и идентичности запроса.
     */
    return [
        'query_vars' =>
            $query->query_vars,

        'is_category' =>
            $query->is_category(),

        'is_archive' =>
            $query->is_archive(),

        'is_singular' =>
            $query->is_singular(),

        'is_single' =>
            $query->is_single(),

        'is_search' =>
            $query->is_search(),

        'is_feed' =>
            $query->is_feed(),

        'is_home' =>
            $query->is_home(),

        'is_paged' =>
            $query->is_paged(),

        'is_main_query' =>
            $query->is_main_query(),

        'queried_object_id' =>
            $query->queried_object_id ?? null,

        'queried_object' =>
            $object_identity,
    ];
}


/**
 * Проверяет фактическое применение
 * нормативной политики обработчиком.
 *
 * Используется для целевых запросов,
 * аргументы которых должны измениться
 * после выполнения catalog-query.php.
 *
 * Проверяется:
 *
 *     - наличие изменения query_vars;
 *     - наличие обязательных аргументов;
 *     - точное соответствие их значений
 *       обязательной политике.
 *
 * Фактическая выборка дополнительно
 * проверяется нормативными сценариями
 * после завершения SQL-запроса.
 *
 * @param array $before Состояние до обработчика.
 * @param array $after  Состояние после обработчика.
 *
 * @return void
 */
function dilitant_catalog_assert_policy_applied(
    array $before,
    array $after
): void {
    /*
     * Проверяем комплектность снимков.
     */
    dilitant_test_assert(
        isset($before['query_vars'])
        && is_array($before['query_vars']),
        'Pre-handler query arguments are missing.'
    );

    dilitant_test_assert(
        isset($after['query_vars'])
        && is_array($after['query_vars']),
        'Post-handler query arguments are missing.'
    );


    /*
     * Обработчик должен фактически
     * изменить аргументы испытуемого
     * целевого запроса.
     */
    dilitant_test_assert(
        $before['query_vars']
            !== $after['query_vars'],
        'Catalog handler did not change query arguments.'
    );


    /*
     * Получаем обязательную политику
     * непосредственно после обработчика.
     *
     * Независимые фиксированные значения
     * дополнительно проверяются
     * общими assertions Article Query.
     */
    $mandatory =
        dilitant_land_get_blog_mandatory_query_args();


    /*
     * Проверяем каждый обязательный
     * аргумент нормативной политики.
     */
    foreach (
        $mandatory
        as $key => $value
    ) {
        /*
         * Аргумент должен существовать.
         *
         * Используем array_key_exists(),
         * поскольку допустимое значение
         * может быть false или null.
         */
        dilitant_test_assert(
            array_key_exists(
                $key,
                $after['query_vars']
            ),
            "Mandatory argument missing after handler: {$key}"
        );


        /*
         * Значение должно точно
         * соответствовать политике.
         */
        dilitant_test_assert_same(
            $value,
            $after['query_vars'][$key],
            "Invalid argument after handler: {$key}"
        );
    }
}


/**
 * Проверяет невмешательство
 * в посторонний запрос.
 *
 * Сравниваются:
 *
 *     - аргументы выборки;
 *     - признаки запроса;
 *     - уже установленная идентичность
 *       запрошенного объекта.
 *
 * Первичная ленивая инициализация
 * кэша queried_object допускается.
 *
 * Изменение уже существующей
 * идентичности не допускается.
 *
 * @param array $before Состояние до обработчика.
 * @param array $after  Состояние после обработчика.
 *
 * @return void
 */
function dilitant_catalog_assert_unchanged(
    array $before,
    array $after
): void {
    /*
     * Формируем отдельные копии
     * нормативно значимого состояния.
     */
    $before_policy = $before;

    $after_policy = $after;


    /*
     * Исключаем внутренний кэш
     * запрошенного объекта.
     *
     * Его первичная инициализация
     * проверяется отдельно.
     */
    unset(
        $before_policy['queried_object'],
        $before_policy['queried_object_id'],

        $after_policy['queried_object'],
        $after_policy['queried_object_id']
    );


    /*
     * Аргументы выборки и признаки
     * запроса должны совпадать.
     */
    dilitant_test_assert_same(
        $before_policy,
        $after_policy,
        'Catalog handler changed an unrelated query.'
    );


    /*
     * Если объект уже существовал
     * до вызова обработчика,
     * его идентичность должна сохраниться.
     */
    if ($before['queried_object'] !== null) {
        dilitant_test_assert_same(
            $before['queried_object'],
            $after['queried_object'],
            'Handler replaced unrelated queried object.'
        );
    }


    /*
     * Если идентификатор уже был установлен,
     * его изменение не допускается.
     */
    if (
        $before['queried_object_id'] !== null
        && (int) $before['queried_object_id'] > 0
    ) {
        dilitant_test_assert_same(
            (int) $before['queried_object_id'],
            (int) $after['queried_object_id'],
            'Handler changed unrelated queried object ID.'
        );
    }
}


/**
 * Независимо определяет ожидаемый
 * порядок публичных статей.
 *
 * Нормативная сортировка:
 *
 *     post_date DESC;
 *     ID DESC.
 *
 * Ожидаемые идентификаторы берутся
 * непосредственно из Fixtures.
 *
 * Article Model не используется
 * для построения ожидаемого порядка.
 *
 * @param DilitantArticleFixtures $fixtures
 *
 * @return int[]
 */
function dilitant_catalog_expected_order(
    DilitantArticleFixtures $fixtures
): array {
    $posts = [];


    /*
     * Получаем объекты ожидаемых
     * публичных публикаций.
     */
    foreach (
        $fixtures->expected_published_post_ids()
        as $id
    ) {
        $post = get_post(
            $id
        );


        /*
         * Каждая ожидаемая публикация
         * должна существовать.
         */
        dilitant_test_assert(
            $post instanceof WP_Post,
            'Expected catalog article is missing.'
        );

        $posts[] = $post;
    }


    /*
     * Независимо сортируем публикации
     * по нормативным критериям.
     */
    usort(
        $posts,

        static function (
            WP_Post $left,
            WP_Post $right
        ): int {
            /*
             * Основной критерий:
             *
             *     post_date DESC.
             */
            $date_order = strcmp(
                $right->post_date,
                $left->post_date
            );

            if ($date_order !== 0) {
                return $date_order;
            }


            /*
             * Дополнительный критерий:
             *
             *     ID DESC.
             *
             * Обеспечивает однозначный
             * порядок при совпадении дат.
             */
            return (int) $right->ID
                <=> (int) $left->ID;
        }
    );


    /*
     * Возвращаем идентификаторы
     * в ожидаемом порядке.
     */
    return array_map(
        static function (
            WP_Post $post
        ): int {
            return (int) $post->ID;
        },
        $posts
    );
}