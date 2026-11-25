<?php
/**
 * Общие функции интеграционных испытаний Editorial.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     HTTP-D02 — Fixtures & Authentication
 *     HF-D01 — Shared Test Bootstrap
 *
 * Ответственность:
 *     - проверка логических условий;
 *     - проверка строгого равенства;
 *     - нормализация идентификаторов;
 *     - извлечение идентификаторов WP_Query.
 *
 * Компонент используется:
 *
 *     editorial/bootstrap.php
 *
 *     editorial/article-http/bootstrap.php
 *
 * Сигнатуры и семантика функций
 * исходного CLI Bootstrap сохранены.
 *
 * Компонент не создаёт испытательные объекты
 * и не выполняет самостоятельных запросов
 * к базе данных.
 */

declare(strict_types=1);


/*
 * --------------------------------------------------
 * 1. Проверка режима исполнения
 * --------------------------------------------------
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);

    exit(1);
}


/*
 * --------------------------------------------------
 * 2. Подключение общих констант
 * --------------------------------------------------
 */

require_once __DIR__
    . '/constants.php';


/*
 * --------------------------------------------------
 * 3. Логическая проверка
 * --------------------------------------------------
 */

/**
 * Проверяет логическое условие.
 *
 * Не зависит от настройки zend.assertions.
 *
 * При отрицательном результате
 * выбрасывает RuntimeException.
 *
 * @param bool   $condition Проверяемое условие.
 * @param string $message   Диагностическое сообщение.
 *
 * @throws RuntimeException
 */
function dilitant_test_assert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
}


/*
 * --------------------------------------------------
 * 4. Проверка строгого равенства
 * --------------------------------------------------
 */

/**
 * Проверяет строгое равенство значений.
 *
 * Сравнение выполняется без преобразования типов.
 *
 * При отказе выводятся ожидаемое
 * и фактическое значения.
 *
 * Не следует передавать секреты, пароли
 * и authentication cookies в эту функцию.
 *
 * @throws RuntimeException
 */
function dilitant_test_assert_same(
    mixed $expected,
    mixed $actual,
    string $message
): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message
            . "\nExpected: "
            . var_export(
                $expected,
                true
            )
            . "\nActual: "
            . var_export(
                $actual,
                true
            )
        );
    }
}


/*
 * --------------------------------------------------
 * 5. Нормализация идентификаторов
 * --------------------------------------------------
 */

/**
 * Нормализует массив идентификаторов.
 *
 * Последовательность:
 *
 *     1. Преобразование значений в integer.
 *     2. Числовая сортировка.
 *     3. Переиндексация массива.
 *
 * Дубликаты намеренно сохраняются,
 * поскольку их появление может означать
 * дефект испытуемой выборки.
 *
 * Данная функция предназначена для сравнения
 * результатов испытаний.
 *
 * Не используется для проверки недоверенных
 * идентификаторов Manifest.
 *
 * @param array $ids Исходные идентификаторы.
 *
 * @return array Нормализованные идентификаторы.
 */
function dilitant_test_normalize_ids(
    array $ids
): array {
    $ids = array_map(
        'intval',
        $ids
    );

    sort(
        $ids,
        SORT_NUMERIC
    );

    return array_values(
        $ids
    );
}


/*
 * --------------------------------------------------
 * 6. Извлечение идентификаторов WP_Query
 * --------------------------------------------------
 */

/**
 * Получает идентификаторы записей WP_Query.
 *
 * Каждый элемент результата должен
 * являться действительным объектом WP_Post.
 *
 * Сохраняет существующую семантику
 * испытаний Article Model.
 *
 * Не запускает новый WP_Query.
 *
 * @throws RuntimeException
 */
function dilitant_test_query_ids(
    WP_Query $query
): array {
    $ids = [];


    foreach ($query->posts as $post) {
        dilitant_test_assert(
            $post instanceof WP_Post,
            'Query returned an unexpected object.'
        );

        $ids[] = (int) $post->ID;
    }


    return dilitant_test_normalize_ids(
        $ids
    );
}