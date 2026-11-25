<?php

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

require_once __DIR__ . '/fixtures/environment.php';
require_once __DIR__ . '/fixtures/state.php';
require_once __DIR__ . '/fixtures/ownership.php';
require_once __DIR__ . '/fixtures/users.php';
require_once __DIR__ . '/fixtures/categories.php';
require_once __DIR__ . '/fixtures/posts.php';
require_once __DIR__ . '/fixtures/verification.php';
require_once __DIR__ . '/fixtures/base-fixtures.php';
require_once __DIR__ . '/fixtures/query-fixtures.php';
require_once __DIR__ . '/fixtures/expectations.php';
require_once __DIR__ . '/fixtures/cleanup.php';

final class DilitantArticleFixtures
{
    use DilitantArticleFixtureEnvironment;
    use DilitantArticleFixtureState;
    use DilitantArticleFixtureOwnership;
    use DilitantArticleFixtureUsers;
    use DilitantArticleFixtureCategories;
    use DilitantArticleFixturePosts;
    use DilitantArticleFixtureVerification;
    use DilitantArticleFixtureBase;
    use DilitantArticleFixtureQuery;
    use DilitantArticleFixtureExpectations;
    use DilitantArticleFixtureCleanup;

    private const ROOT_SLUG = 'diletant-armenia';
    private const ARCHIVE_POST_TYPE = 'archive_photo';
    private const TEST_POST_PASSWORD = 'am-integration-test-password';

    private array $category_ids = [];
    private array $post_ids = [];

    private string $run_id;
    private int $author_id;

    private bool $base_created = false;
    private bool $query_created = false;

    public int $root_category = 0;
    public int $child_category = 0;
    public int $grandchild_category = 0;
    public int $outside_category = 0;

    public int $root_post = 0;
    public int $child_post = 0;
    public int $grandchild_post = 0;
    public int $outside_post = 0;
    public int $draft_post = 0;

    public int $page_post = 0;
    public int $private_post = 0;
    public int $future_post = 0;
    public int $password_post = 0;
    public int $archive_post = 0;

    public function __construct()
    {
        $this->verify_environment();

        $this->run_id = bin2hex(random_bytes(6));
        $author = get_user_by('login', 'testadmin');

        if (!$author instanceof WP_User || (int) $author->ID <= 0) {
            throw new RuntimeException('Test administrator unavailable.');
        }

        $this->author_id = (int) $author->ID;
    }
}