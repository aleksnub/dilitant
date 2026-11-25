<?php
/**
 * Окружение интеграционных испытаний
 * публичного каталога Article Model.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D02 — Защита инвариантов WP_Query
 *
 * Ответственность:
 *     - подключение компонентов окружения;
 *     - сохранение глобального состояния;
 *     - восстановление глобального состояния;
 *     - проверка восстановления окружения;
 *     - временная настройка размера страницы;
 *     - управление временными фильтрами.
 *
 * Исполнение WP_Query делегируется
 * компоненту article-catalog-execution.php.
 *
 * Проверка результатов делегируется
 * компоненту article-catalog-assertions.php.
 *
 * Компонент не запускает испытания.
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
 * Подключаем механизмы проверки
 * результатов и состояния запросов.
 */
require_once __DIR__
    . '/article-catalog-assertions.php';


/*
 * Подключаем механизмы исполнения
 * и наблюдения за запросами.
 */
require_once __DIR__
    . '/article-catalog-execution.php';


/**
 * Сохраняет контролируемые глобальные
 * переменные WordPress.
 *
 * Сохраняется:
 *
 *     - имя переменной;
 *     - факт существования;
 *     - исходное значение.
 *
 * Наличие переменной со значением null
 * отличается от отсутствия переменной.
 *
 * @return array
 */
function dilitant_catalog_capture_globals(): array
{
    $names = [
        'wp_query',
        'wp_the_query',
        'post',
        'current_screen',
        'id',
        'authordata',
        'currentday',
        'currentmonth',
        'page',
        'pages',
        'multipage',
        'more',
        'numpages',
    ];

    $state = [];

    foreach ($names as $name) {
        $exists = array_key_exists(
            $name,
            $GLOBALS
        );

        $state[$name] = [
            'exists' => $exists,

            'value' => $exists
                ? $GLOBALS[$name]
                : null,
        ];
    }

    return $state;
}


/**
 * Восстанавливает сохранённое
 * глобальное состояние WordPress.
 *
 * Переменные, отсутствовавшие
 * до начала испытания,
 * удаляются из глобальной области.
 *
 * @param array $state Сохранённое состояние.
 *
 * @return void
 */
function dilitant_catalog_restore_globals(
    array $state
): void {
    foreach ($state as $name => $entry) {
        if ($entry['exists']) {
            $GLOBALS[$name] =
                $entry['value'];

        } else {
            unset($GLOBALS[$name]);
        }
    }
}


/**
 * Проверяет восстановление
 * контролируемого глобального состояния.
 *
 * Проверка выполняется независимо
 * от функции восстановления.
 *
 * @param array $expected Исходное состояние.
 *
 * @return void
 */
function dilitant_catalog_verify_globals(
    array $expected
): void {
    $actual =
        dilitant_catalog_capture_globals();

    dilitant_test_assert_same(
        $expected,
        $actual,
        'WordPress global state was not restored.'
    );
}


/**
 * Временно устанавливает размер страницы.
 *
 * Используется WordPress option API.
 *
 * Значение posts_per_page в таблице
 * wp_test_options не изменяется.
 *
 * Поддерживаются вложенные вызовы.
 *
 * Внешний исполнитель использует
 * приоритет 1000.
 *
 * Испытание пагинации AM-CQ-09
 * использует приоритет 2000.
 *
 * Временный фильтр удаляется
 * независимо от результата callback.
 *
 * @param int      $size     Размер страницы.
 * @param callable $callback Испытуемая операция.
 * @param int      $priority Приоритет фильтра.
 *
 * @return mixed
 */
function dilitant_catalog_with_page_size(
    int $size,
    callable $callback,
    int $priority = 2000
) {
    /*
     * Проверяем допустимость размера
     * страницы в соответствии
     * с действующей политикой модели.
     */
    dilitant_test_assert(
        $size > 0 && $size <= 100,
        'Invalid fixture page size.'
    );


    /*
     * Проверяем приоритет фильтра.
     */
    dilitant_test_assert(
        $priority > 0,
        'Invalid page size filter priority.'
    );


    /*
     * Сохраняем действующее значение.
     *
     * При вложенном вызове сохраняется
     * результат внешнего фильтра.
     */
    $previous_size =
        get_option(
            'posts_per_page'
        );


    /*
     * Создаём отдельный экземпляр
     * замыкания для текущего вызова.
     *
     * Это позволяет удалить именно
     * собственный фильтр, не затрагивая
     * фильтры других компонентов.
     */
    $filter = static function () use (
        $size
    ): string {
        return (string) $size;
    };


    /*
     * Подключаем временный фильтр.
     */
    add_filter(
        'pre_option_posts_per_page',
        $filter,
        $priority,
        0
    );

    try {
        /*
         * Подтверждаем фактическое
         * применение временного значения.
         */
        dilitant_test_assert_same(
            $size,
            (int) get_option(
                'posts_per_page'
            ),
            'Test page size was not applied.'
        );


        /*
         * Выполняем испытание
         * в изменённом окружении.
         */
        return $callback();

    } finally {
        /*
         * Удаляем исключительно фильтр,
         * установленный текущим вызовом.
         */
        $removed = remove_filter(
            'pre_option_posts_per_page',
            $filter,
            $priority
        );


        /*
         * Проверяем факт удаления.
         */
        dilitant_test_assert_same(
            true,
            $removed,
            'Page size filter was not removed.'
        );


        /*
         * Проверяем восстановление
         * исходного значения.
         */
        dilitant_test_assert_same(
            $previous_size,
            get_option(
                'posts_per_page'
            ),
            'Original page size was not restored.'
        );
    }
}