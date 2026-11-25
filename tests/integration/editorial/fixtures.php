<?php
/**
 * Точка входа тестовых данных Article Model.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D01 — Единая семантика принадлежности
 *     AM-D02 — Защита инвариантов WP_Query
 *
 * Испытания:
 *     AM-T01 — AM-T05
 *     AM-T06 — AM-T08
 *
 * Ответственность:
 *     - подключение функциональных компонентов;
 *     - определение состояния тестового экземпляра;
 *     - предоставление единого публичного интерфейса;
 *     - владение идентификаторами тестовых объектов.
 */

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    || !defined('DILITANT_TEST_DB_NAME')
    || !defined('DB_NAME')
    || DB_NAME !== DILITANT_TEST_DB_NAME
    || !function_exists('dilitant_test_assert')
) {
    exit(1);
}


/*
 * Подключение функциональных компонентов.
 */
require_once __DIR__ . '/fixtures/environment.php';

require_once __DIR__ . '/fixtures/categories.php';

require_once __DIR__ . '/fixtures/posts.php';

require_once __DIR__ . '/fixtures/verification.php';

require_once __DIR__ . '/fixtures/base-fixtures.php';

require_once __DIR__ . '/fixtures/query-fixtures.php';

require_once __DIR__ . '/fixtures/expectations.php';

require_once __DIR__ . '/fixtures/cleanup.php';


/**
 * Единый управляющий объект тестовых данных.
 *
 * Все функциональные компоненты используют
 * состояние данного экземпляра.
 *
 * Отдельные экземпляры Traits не создаются.
 */
final class DilitantArticleFixtures
{
    use DilitantArticleFixtureEnvironment;

    use DilitantArticleFixtureCategories;

    use DilitantArticleFixturePosts;

    use DilitantArticleFixtureVerification;

    use DilitantArticleFixtureBase;

    use DilitantArticleFixtureQuery;

    use DilitantArticleFixtureExpectations;

    use DilitantArticleFixtureCleanup;


    /*
     * Константы тестовой модели.
     */
    private const ROOT_SLUG =
        'diletant-armenia';

    private const ARCHIVE_POST_TYPE =
        'archive_photo';

    private const TEST_POST_PASSWORD =
        'am-integration-test-password';


    /*
     * Реестр объектов, созданных
     * данным экземпляром.
     *
     * Используется исключительно для
     * управления жизненным циклом и очистки.
     */
    private array $category_ids = [];

    private array $post_ids = [];


    /*
     * Идентификатор запуска.
     */
    private string $run_id;


    /*
     * Автор тестовых публикаций.
     */
    private int $author_id;


    /*
     * Состояние жизненного цикла.
     */
    private bool $base_created = false;

    private bool $query_created = false;


    /*
     * Категории.
     */
    public int $root_category = 0;

    public int $child_category = 0;

    public int $grandchild_category = 0;

    public int $outside_category = 0;


    /*
     * Базовые публикации AM-T01–AM-T05.
     */
    public int $root_post = 0;

    public int $child_post = 0;

    public int $grandchild_post = 0;

    public int $outside_post = 0;

    public int $draft_post = 0;


    /*
     * Дополнительные публикации AM-T06–AM-T08.
     */
    public int $page_post = 0;

    public int $private_post = 0;

    public int $future_post = 0;

    public int $password_post = 0;

    public int $archive_post = 0;


    /**
     * Инициализирует экземпляр
     * управляющего объекта.
     *
     * На данной стадии категории
     * и публикации не создаются.
     */
    public function __construct()
    {
        $this->verify_environment();

        $this->run_id = bin2hex(
            random_bytes(6)
        );

        $author = get_user_by(
            'login',
            'testadmin'
        );

        if (!$author instanceof WP_User) {
            throw new RuntimeException(
                'Test administrator does not exist.'
            );
        }

        $this->author_id = (int) $author->ID;

        dilitant_test_assert(
            $this->author_id > 0,
            'Invalid test administrator identifier.'
        );
    }
}