<?php
/**
 * AM-QI — Интеграционные испытания
 * состава выборки Article Model.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D02 — Защита инвариантов WP_Query
 *
 * Ответственность:
 *     - проверка базовой публичной выборки;
 *     - проверка исключения публикаций;
 *     - проверка приоритета исключений;
 *     - проверка полного исключения записей;
 *     - проверка сохранения обязательных
 *       ограничений при фильтрации по ID.
 *
 * Сценарии:
 *     AM-QI-01 — baseline public query;
 *     AM-QI-04 — exclusion filter;
 *     AM-QI-05 — inclusion exclusion precedence;
 *     AM-QI-06 — complete exclusion.
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
 * AM-QI-01.
 *
 * Проверяет базовую публичную выборку.
 *
 * В тестовой среде присутствуют
 * десять записей различных типов
 * и статусов.
 *
 * Публичная выборка должна возвратить
 * исключительно три допустимые статьи.
 */
function dilitant_query_test_baseline_selection(
    DilitantArticleFixtures $fixtures
): void {
    dilitant_query_test_assert_public_set(
        $fixtures,
        []
    );
}


/**
 * AM-QI-04.
 *
 * Проверяет исключение конкретных записей
 * из публичной выборки посредством
 * разрешённого аргумента post__not_in.
 */
function dilitant_query_test_exclusion(
    DilitantArticleFixtures $fixtures
): void {
    /*
     * Исключаем статью корневой категории.
     *
     * В результате должны остаться
     * статьи дочерней категории
     * и категории третьего уровня.
     */
    dilitant_query_test_assert_selection(
        $fixtures,
        [
            'post__not_in' => [
                $fixtures->root_post,
            ],
        ],
        [
            $fixtures->child_post,
            $fixtures->grandchild_post,
        ]
    );


    /*
     * Пустое множество исключений
     * не должно изменять выборку.
     */
    dilitant_query_test_assert_public_set(
        $fixtures,
        [
            'post__not_in' => [],
        ]
    );


    /*
     * Исключение уже запрещённых объектов
     * не должно изменять состав
     * публичной выборки.
     *
     * Посторонняя запись и защищённая
     * паролем статья исключаются
     * обязательной политикой независимо
     * от пользовательских аргументов.
     */
    dilitant_query_test_assert_public_set(
        $fixtures,
        [
            'post__not_in' => [
                $fixtures->outside_post,
                $fixtures->password_post,
            ],
        ]
    );
}


/**
 * AM-QI-05.
 *
 * Проверяет совместное использование
 * post__in и post__not_in.
 *
 * Приоритет принадлежит исключениям.
 *
 * Дополнительно проверяется:
 *     - удаление повторяющихся ID;
 *     - сохранение нормативного дерева;
 *     - невозможность включения
 *       посторонней публикации;
 *     - фактическая нормализация
 *       аргументов запроса.
 */
function dilitant_query_test_combined_ids(
    DilitantArticleFixtures $fixtures
): void {
    /*
     * Включаем:
     *
     *     root_post;
     *     child_post;
     *     root_post повторно;
     *     outside_post.
     *
     * Одновременно исключаем root_post.
     *
     * Единственным допустимым результатом
     * должна стать child_post.
     */
    $query = dilitant_query_test_assert_selection(
        $fixtures,
        [
            'post__in' => [
                $fixtures->root_post,
                $fixtures->child_post,
                $fixtures->root_post,
                $fixtures->outside_post,
            ],

            'post__not_in' => [
                $fixtures->root_post,
            ],
        ],
        [
            $fixtures->child_post,
        ]
    );


    /*
     * Проверяем фактически нормализованный
     * список включённых идентификаторов.
     */
    $included = $query->get('post__in');

    dilitant_test_assert(
        is_array($included),
        'Normalized inclusion list is missing.'
    );


    /*
     * После нормализации должны остаться:
     *
     *     child_post;
     *     outside_post.
     *
     * Посторонний ID может присутствовать
     * в списке post__in, поскольку
     * окончательное ограничение категории
     * выполняется посредством tax_query.
     */
    dilitant_test_assert_same(
        dilitant_test_normalize_ids([
            $fixtures->child_post,
            $fixtures->outside_post,
        ]),
        dilitant_test_normalize_ids(
            $included
        ),
        'Unexpected normalized inclusion list.'
    );


    /*
     * Фактическое отсутствие outside_post
     * в результатах уже подтверждено
     * функцией assert_selection().
     */
}


/**
 * AM-QI-06.
 *
 * Проверяет полное исключение
 * всех явно включённых записей.
 *
 * Если после применения post__not_in
 * список post__in становится пустым,
 * модель должна сформировать
 * закрытый отказ.
 */
function dilitant_query_test_complete_exclusion(
    DilitantArticleFixtures $fixtures
): void {
    $query = dilitant_query_test_execute([
        'post__in' => [
            $fixtures->root_post,
        ],

        'post__not_in' => [
            $fixtures->root_post,
        ],
    ]);


    /*
     * Проверяем не просто отсутствие записей,
     * а применение специального механизма
     * закрытого отказа:
     *
     *     post__in = [0]
     *     no_found_rows = true
     */
    dilitant_query_test_assert_closed_failure(
        $query
    );
}