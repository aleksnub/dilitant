<?php
/**
 * Интеграционные испытания Article Model.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *
 * Испытания:
 *
 *     AM-T01 — статья корневой категории.
 *     AM-T02 — статья дочерней категории.
 *     AM-T03 — статья категории третьего уровня.
 *     AM-T04 — запись посторонней категории.
 *     AM-T05 — отсутствие корневой категории.
 *
 * Код завершения:
 *     0 — все испытания успешно пройдены.
 *     1 — обнаружены ошибки.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures.php';


/**
 * Получает опубликованные статьи блога.
 */
function dilitant_test_get_articles(): WP_Query
{
    return dilitant_land_get_blog_articles([
        'posts_per_page' => 100,
        'orderby' => 'ID',
        'order' => 'ASC',
    ]);
}


/**
 * AM-T01.
 *
 * Статья непосредственно принадлежит
 * корневой категории блога.
 */
function test_am_t01(
    DilitantArticleFixtures $fixtures
): void {
    dilitant_test_assert_same(
        $fixtures->root_category,
        dilitant_land_get_blog_category_id(),
        'Incorrect root category ID.'
    );

    $category_ids = dilitant_test_normalize_ids(
        dilitant_land_get_blog_category_ids()
    );

    dilitant_test_assert(
        in_array(
            $fixtures->root_category,
            $category_ids,
            true
        ),
        'Root category is missing from category tree.'
    );

    dilitant_test_assert_same(
        true,
        dilitant_land_is_blog_article(
            $fixtures->root_post
        ),
        'Root category post is not recognized.'
    );

    /*
     * Проверяем передачу WP_Post вместо ID.
     */
    $post = get_post($fixtures->root_post);

    dilitant_test_assert(
        $post instanceof WP_Post,
        'Root fixture post is missing.'
    );

    dilitant_test_assert_same(
        true,
        dilitant_land_is_blog_article($post),
        'Root WP_Post object is not recognized.'
    );

    $query = dilitant_test_get_articles();

    dilitant_test_assert(
        in_array(
            $fixtures->root_post,
            dilitant_test_query_ids($query),
            true
        ),
        'Root category post is missing from query.'
    );
}


/**
 * AM-T02.
 *
 * Статья принадлежит исключительно дочерней
 * категории, но должна распознаваться
 * как статья блога.
 */
function test_am_t02(
    DilitantArticleFixtures $fixtures
): void {
    $category_ids = dilitant_test_normalize_ids(
        dilitant_land_get_blog_category_ids()
    );

    dilitant_test_assert(
        in_array(
            $fixtures->child_category,
            $category_ids,
            true
        ),
        'Child category is missing from category tree.'
    );

    dilitant_test_assert_same(
        [$fixtures->child_category],
        dilitant_test_normalize_ids(
            wp_get_post_categories(
                $fixtures->child_post
            )
        ),
        'Child post fixture is incorrectly configured.'
    );

    dilitant_test_assert_same(
        true,
        dilitant_land_is_blog_article(
            $fixtures->child_post
        ),
        'Child category post is not recognized.'
    );

    $query = dilitant_test_get_articles();

    dilitant_test_assert(
        in_array(
            $fixtures->child_post,
            dilitant_test_query_ids($query),
            true
        ),
        'Child category post is missing from query.'
    );
}


/**
 * AM-T03.
 *
 * Проверка рекурсивной принадлежности:
 *
 *     root
 *       └── child
 *             └── grandchild
 */
function test_am_t03(
    DilitantArticleFixtures $fixtures
): void {
    $actual_category_ids =
        dilitant_test_normalize_ids(
            dilitant_land_get_blog_category_ids()
        );

    dilitant_test_assert_same(
        $fixtures->expected_category_ids(),
        $actual_category_ids,
        'Category tree is incomplete or contains extra terms.'
    );

    dilitant_test_assert_same(
        [$fixtures->grandchild_category],
        dilitant_test_normalize_ids(
            wp_get_post_categories(
                $fixtures->grandchild_post
            )
        ),
        'Grandchild fixture is incorrectly configured.'
    );

    dilitant_test_assert_same(
        true,
        dilitant_land_is_blog_article(
            $fixtures->grandchild_post
        ),
        'Grandchild category post is not recognized.'
    );

    $query = dilitant_test_get_articles();

    dilitant_test_assert(
        in_array(
            $fixtures->grandchild_post,
            dilitant_test_query_ids($query),
            true
        ),
        'Grandchild category post is missing from query.'
    );
}


/**
 * AM-T04.
 *
 * Запись посторонней категории:
 *     - не принадлежит дереву блога;
 *     - не является статьёй блога;
 *     - не должна присутствовать в выборке.
 *
 * Дополнительно проверяется точный состав
 * выборки и исключение черновиков.
 */
function test_am_t04(
    DilitantArticleFixtures $fixtures
): void {
    dilitant_test_assert_same(
        false,
        dilitant_land_is_blog_article(
            $fixtures->outside_post
        ),
        'Outside category post was recognized as blog article.'
    );

    dilitant_test_assert(
        !in_array(
            $fixtures->outside_category,
            dilitant_land_get_blog_category_ids(),
            true
        ),
        'Outside category entered the blog category tree.'
    );

    $query = dilitant_test_get_articles();

    $query_ids = dilitant_test_query_ids(
        $query
    );

    dilitant_test_assert(
        !in_array(
            $fixtures->outside_post,
            $query_ids,
            true
        ),
        'Outside category post entered published query.'
    );


    /*
     * Принадлежность к блогу не зависит
     * от статуса публикации.
     */
    dilitant_test_assert_same(
        true,
        dilitant_land_is_blog_article(
            $fixtures->draft_post
        ),
        'Draft belonging to blog was not recognized.'
    );


    /*
     * Однако черновик не должен попадать
     * в выборку опубликованных статей.
     */
    dilitant_test_assert(
        !in_array(
            $fixtures->draft_post,
            $query_ids,
            true
        ),
        'Draft entered published article query.'
    );


    /*
     * Проверяем точный состав результатов.
     *
     * Проверка также выявляет дублирование
     * или появление посторонних публикаций.
     */
    dilitant_test_assert_same(
        $fixtures->expected_published_post_ids(),
        $query_ids,
        'Published query has an unexpected set of articles.'
    );

    foreach ($query->posts as $post) {
        dilitant_test_assert_same(
            'post',
            $post->post_type,
            'Query returned an unexpected post type.'
        );

        dilitant_test_assert_same(
            'publish',
            $post->post_status,
            'Query returned an unpublished post.'
        );
    }

    dilitant_test_assert_same(
        count(
            $fixtures->expected_published_post_ids()
        ),
        (int) $query->post_count,
        'Unexpected article count.'
    );
}


/**
 * AM-T05.
 *
 * При отсутствии корневой категории
 * модель должна отказать закрытым образом.
 *
 * Записи продолжают существовать,
 * но ни одна из них не признаётся статьёй блога.
 */
function test_am_t05(
    DilitantArticleFixtures $fixtures
): void {
    $fixtures->remove_root_category();


    /*
     * Корневая категория не разрешается.
     */
    dilitant_test_assert_same(
        0,
        dilitant_land_get_blog_category_id(),
        'Missing root category did not return zero.'
    );


    /*
     * Дерево категорий должно быть пустым.
     */
    dilitant_test_assert_same(
        [],
        dilitant_land_get_blog_category_ids(),
        'Missing root category did not return empty tree.'
    );


    /*
     * Все публикации продолжают существовать,
     * но не должны распознаваться как статьи блога.
     */
    foreach ($fixtures->all_post_ids() as $post_id) {
        dilitant_test_assert(
            get_post($post_id) instanceof WP_Post,
            "Test post {$post_id} unexpectedly disappeared."
        );

        dilitant_test_assert_same(
            false,
            dilitant_land_is_blog_article($post_id),
            "Post {$post_id} accepted without root category."
        );
    }


    /*
     * Стандартная выборка должна быть пустой.
     */
    $query = dilitant_test_get_articles();

    dilitant_test_assert_same(
        [],
        dilitant_test_query_ids($query),
        'Missing root category returned posts.'
    );

    dilitant_test_assert_same(
        0,
        (int) $query->post_count,
        'Missing root category produced nonzero post count.'
    );

    dilitant_test_assert_same(
        0,
        (int) $query->found_posts,
        'Missing root category produced nonzero found_posts.'
    );


    /*
     * Явное указание существующих ID
     * не должно обходить закрытый отказ.
     */
    $query = dilitant_land_get_blog_articles([
        'posts_per_page' => 100,
        'post__in' => [
            $fixtures->root_post,
            $fixtures->outside_post,
        ],
    ]);

    dilitant_test_assert_same(
        [],
        dilitant_test_query_ids($query),
        'Missing root allowed explicit post selection.'
    );
}


/*
 * Регистрация испытаний.
 *
 * AM-T05 выполняется последним,
 * поскольку изменяет дерево категорий.
 */
$tests = [
    'AM-T01' => 'test_am_t01',
    'AM-T02' => 'test_am_t02',
    'AM-T03' => 'test_am_t03',
    'AM-T04' => 'test_am_t04',
    'AM-T05' => 'test_am_t05',
];


$fixtures = null;

$passed = 0;
$failed = 0;

$fixture_error = false;
$cleanup_error = false;


echo PHP_EOL;
echo "=== EDC-01 ARTICLE IDENTITY ===\n";
echo PHP_EOL;


/*
 * Создание тестовых данных
 * и последовательное выполнение испытаний.
 */
try {
    $fixtures = new DilitantArticleFixtures();

    $fixtures->create();

    echo "PASS: fixture creation\n";
    echo PHP_EOL;

    foreach ($tests as $id => $function) {
        try {
            $function($fixtures);

            echo "PASS: {$id}\n";

            $passed++;

        } catch (Throwable $error) {
            fwrite(
                STDERR,
                "FAIL: {$id}\n"
                . $error->getMessage()
                . PHP_EOL
            );

            $failed++;
        }
    }

} catch (Throwable $error) {
    fwrite(
        STDERR,
        "FAIL: fixture initialization\n"
        . $error->getMessage()
        . PHP_EOL
    );

    $fixture_error = true;

} finally {
    if ($fixtures instanceof DilitantArticleFixtures) {
        try {
            $fixtures->cleanup();

        } catch (Throwable $error) {
            fwrite(
                STDERR,
                "FAIL: fixture cleanup\n"
                . $error->getMessage()
                . PHP_EOL
            );

            $cleanup_error = true;
        }
    }
}


/*
 * Формирование итогового результата.
 */
$total = count($tests);

$not_executed = $total - $passed - $failed;

echo PHP_EOL;
echo "=== RESULT ===\n";

echo "Total: {$total}\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
echo "Not executed: {$not_executed}\n";

echo "Fixture initialization: "
    . ($fixture_error ? 'FAIL' : 'PASS')
    . PHP_EOL;

echo "Fixture cleanup: "
    . (
        $fixtures === null
            ? 'NOT EXECUTED'
            : ($cleanup_error ? 'FAIL' : 'PASS')
    )
    . PHP_EOL;


/*
 * Положительный результат допускается только
 * при прохождении всех испытаний и успешной очистке.
 */
if (
    $fixture_error
    || $cleanup_error
    || $failed > 0
    || $passed !== $total
) {
    echo PHP_EOL;
    echo "FAIL: EDC-01 AM-T01-AM-T05\n";

    exit(1);
}

echo PHP_EOL;
echo "PASS: EDC-01 AM-T01-AM-T05\n";

exit(0);