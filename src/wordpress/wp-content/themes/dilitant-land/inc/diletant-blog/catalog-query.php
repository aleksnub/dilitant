<?php
/**
 * Интеграция публичного каталога
 * с нормативной политикой Article Model.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial, редакция 1.1
 *     EDC-01 — Article Model
 *     AM-D02 — Защита инвариантов WP_Query
 *     AM-D05 — Пагинация каталога
 *
 * Ответственность:
 *     - идентификация целевого глобального запроса;
 *     - получение нормативных аргументов;
 *     - устранение ненормативных ограничений;
 *     - сохранение идентичности архива категории;
 *     - применение политики до исполнения SQL.
 *
 * Компонент не создаёт дополнительных
 * экземпляров WP_Query.
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/**
 * Определяет, относится ли запрос
 * к публичному каталогу статей.
 *
 * Не применяется к:
 *     - административной области;
 *     - вторичным запросам;
 *     - отдельным публикациям;
 *     - поисковым запросам;
 *     - RSS;
 *     - REST API;
 *     - AJAX;
 *     - архивам посторонних категорий.
 *
 * @internal
 *
 * @param WP_Query $query Проверяемый запрос.
 *
 * @return bool
 */
function dilitant_land_is_blog_catalog_query(
    WP_Query $query
): bool {
    /*
     * Административная область.
     */
    if ( is_admin() ) {
        return false;
    }


    /*
     * AJAX-запросы.
     */
    if ( wp_doing_ajax() ) {
        return false;
    }


    /*
     * REST API.
     */
    if (
        defined( 'REST_REQUEST' )
        && REST_REQUEST
    ) {
        return false;
    }


    /*
     * Только главный запрос WordPress.
     */
    if ( ! $query->is_main_query() ) {
        return false;
    }


    /*
     * Исключаем запросы, не относящиеся
     * к публичному архиву категории.
     */
    if (
        $query->is_singular()
        || $query->is_search()
        || $query->is_feed()
        || $query->is_preview()
        || $query->is_embed()
    ) {
        return false;
    }


    /*
     * Проверяем признаки архива категории.
     */
    if ( ! $query->is_category() ) {
        return false;
    }


    /*
     * Проверяем прямую идентификацию
     * корневой категории по slug.
     *
     * Это также позволяет распознать
     * запрос к корневому каталогу,
     * если сама категория отсутствует.
     */
    $category_name = $query->get(
        'category_name'
    );

    if (
        DILITANT_LAND_BLOG_CATEGORY_SLUG
        === $category_name
    ) {
        return true;
    }


    /*
     * Дополнительно поддерживаем
     * штатный архив WordPress,
     * определённый через ID категории.
     */
    $root_id =
        dilitant_land_get_blog_category_id();

    if ( $root_id <= 0 ) {
        return false;
    }


    /*
     * Проверяем фактически запрошенный
     * объект категории.
     */
    $category = $query->get_queried_object();

    if ( ! $category instanceof WP_Term ) {
        return false;
    }

    if ( 'category' !== $category->taxonomy ) {
        return false;
    }

    return $root_id === (int) $category->term_id;
}


/**
 * Формирует разрешённые входные аргументы
 * для публичного каталога.
 *
 * В текущей редакции из глобального запроса
 * переносится исключительно номер страницы.
 *
 * Размер страницы определяется действующей
 * политикой Article Model.
 *
 * Сортировка устанавливается доверенным кодом.
 *
 * @internal
 *
 * @param WP_Query $query Глобальный запрос.
 *
 * @return array
 */
function dilitant_land_get_blog_catalog_input_args(
    WP_Query $query
): array {
    /*
     * WordPress может использовать пустое
     * значение paged для первой страницы.
     */
    $paged = $query->get(
        'paged'
    );

    if (
        '' === $paged
        || null === $paged
        || 0 === $paged
        || '0' === $paged
    ) {
        $paged = 1;
    }


    /*
     * Остальные значения передаются
     * нормативному нормализатору.
     *
     * Отрицательное или некорректное
     * значение не исправляется молча.
     */
    return array(
        'paged'   => $paged,
        'orderby' => 'date',
        'order'   => 'DESC',
    );
}


/**
 * Получает корневой объект категории,
 * используемый для идентичности архива.
 *
 * @internal
 *
 * @return WP_Term|null
 */
function dilitant_land_get_blog_catalog_term() {
    $root_id =
        dilitant_land_get_blog_category_id();

    if ( $root_id <= 0 ) {
        return null;
    }

    $term = get_term(
        $root_id,
        'category'
    );

    if ( ! $term instanceof WP_Term ) {
        return null;
    }

    if ( 'category' !== $term->taxonomy ) {
        return null;
    }

    if (
        DILITANT_LAND_BLOG_CATEGORY_SLUG
        !== $term->slug
    ) {
        return null;
    }

    return $term;
}


/**
 * Применяет нормативные аргументы
 * к существующему глобальному запросу.
 *
 * Исходный массив query_vars заменяется,
 * а не объединяется с нормативным массивом.
 *
 * Это исключает сохранение альтернативных
 * ограничений:
 *
 *     cat;
 *     category_name;
 *     category__in;
 *     category__not_in;
 *     category__and;
 *     meta_query;
 *     offset;
 *     nopaging;
 *     post_status;
 *     post_password;
 *     и других произвольных параметров.
 *
 * Идентичность исходного архива сохраняется
 * отдельно от параметров SQL-выборки.
 *
 * @internal
 *
 * @param WP_Query     $query      Глобальный запрос.
 * @param array        $query_args Нормативные аргументы.
 * @param WP_Term|null $term       Корневая категория.
 *
 * @return void
 */
function dilitant_land_apply_blog_catalog_query_args(
    WP_Query $query,
    array $query_args,
    $term
): void {
    /*
     * Заполняем штатные значения WP_Query
     * на основании закрытого набора
     * нормативных аргументов.
     */
    $query->query_vars =
        $query->fill_query_vars(
            $query_args
        );


    /*
     * Сохраняем признаки архива.
     *
     * WP_Query уже выполнил разбор исходного
     * маршрута до вызова pre_get_posts.
     *
     * Повторный parse_query() не выполняется.
     */
    if ( $term instanceof WP_Term ) {
        $query->queried_object =
            $term;

        $query->queried_object_id =
            (int) $term->term_id;
    } else {
        $query->queried_object = null;

        $query->queried_object_id = 0;
    }
}


/**
 * Обработчик глобального запроса каталога.
 *
 * Выполняется до построения SQL-запроса.
 *
 * Использует тот же построитель,
 * что и dilitant_land_get_blog_articles().
 *
 * @param WP_Query $query Глобальный запрос.
 *
 * @return void
 */
function dilitant_land_apply_blog_catalog_query(
    WP_Query $query
): void {
    /*
     * 1. Подтверждаем назначение запроса.
     */
    if (
        ! dilitant_land_is_blog_catalog_query(
            $query
        )
    ) {
        return;
    }


    /*
     * 2. Получаем нормативную идентичность
     * корневой категории.
     */
    $term =
        dilitant_land_get_blog_catalog_term();


    /*
     * 3. При невозможности подтвердить
     * корневую категорию применяем
     * закрытый отказ.
     */
    if ( ! $term instanceof WP_Term ) {
        dilitant_land_apply_blog_catalog_query_args(
            $query,

            dilitant_land_build_empty_blog_query_args(),

            null
        );

        return;
    }


    /*
     * 4. Получаем разрешённые входные
     * аргументы каталога.
     */
    $input_args =
        dilitant_land_get_blog_catalog_input_args(
            $query
        );


    /*
     * 5. Формируем нормативные аргументы.
     */
    $query_args =
        dilitant_land_build_blog_query_args(
            $input_args
        );


    /*
     * 6. Применяем сформированную политику
     * к существующему глобальному запросу.
     */
    dilitant_land_apply_blog_catalog_query_args(
        $query,
        $query_args,
        $term
    );
}


/*
 * Подключаем обработчик до построения SQL.
 *
 * Поздний приоритет уменьшает вероятность
 * случайного переопределения параметров
 * обычными обработчиками темы.
 *
 * Он не является границей безопасности
 * против произвольных WordPress Hooks.
 */
add_action(
    'pre_get_posts',
    'dilitant_land_apply_blog_catalog_query',
    100,
    1
);