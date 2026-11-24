<?php
/**
 * Тестовые данные Article Model.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-T01 — AM-T05
 *
 * Ответственность:
 *     - создание дерева категорий;
 *     - создание тестовых публикаций;
 *     - проверка корректности тестовых данных;
 *     - моделирование отсутствия корневой категории;
 *     - удаление созданных тестовых объектов.
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


/**
 * Управляет жизненным циклом тестовых данных.
 */
final class DilitantArticleFixtures
{
    private const ROOT_SLUG = 'diletant-armenia';

    private array $category_ids = [];

    private array $post_ids = [];

    private string $run_id;

    private int $author_id;


    public int $root_category = 0;

    public int $child_category = 0;

    public int $grandchild_category = 0;

    public int $outside_category = 0;


    public int $root_post = 0;

    public int $child_post = 0;

    public int $grandchild_post = 0;

    public int $outside_post = 0;

    public int $draft_post = 0;


    /**
     * Инициализация экземпляра тестовых данных.
     */
    public function __construct()
    {
        $this->run_id = bin2hex(random_bytes(6));

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
    }


    /**
     * Проверяет изоляцию перед изменением данных.
     */
    private function verify_environment(): void
    {
        global $wpdb;

        dilitant_test_assert_same(
            DILITANT_TEST_DB_NAME,
            DB_NAME,
            'Unexpected configured database.'
        );

        dilitant_test_assert_same(
            DILITANT_TEST_DB_NAME,
            $wpdb->get_var('SELECT DATABASE()'),
            'Unexpected active database.'
        );

        dilitant_test_assert_same(
            DILITANT_TEST_TABLE_PREFIX,
            $wpdb->prefix,
            'Unexpected table prefix.'
        );
    }


    /**
     * Создаёт полный набор тестовых данных.
     */
    public function create(): void
    {
        $this->verify_environment();

        if (
            $this->category_ids !== []
            || $this->post_ids !== []
        ) {
            throw new RuntimeException(
                'Fixtures have already been created.'
            );
        }

        $existing = get_category_by_slug(
            self::ROOT_SLUG
        );

        if ($existing instanceof WP_Term) {
            throw new RuntimeException(
                'Blog root already exists in test database. '
                . 'A clean test environment is required.'
            );
        }


        /*
         * Корневая категория блога.
         */
        $this->root_category = $this->create_category(
            'AM Root',
            self::ROOT_SLUG
        );


        /*
         * Категория второго уровня.
         */
        $this->child_category = $this->create_category(
            'AM Child ' . $this->run_id,
            'am-child-' . $this->run_id,
            $this->root_category
        );


        /*
         * Категория третьего уровня.
         */
        $this->grandchild_category = $this->create_category(
            'AM Grandchild ' . $this->run_id,
            'am-grandchild-' . $this->run_id,
            $this->child_category
        );


        /*
         * Категория вне дерева блога.
         */
        $this->outside_category = $this->create_category(
            'AM Outside ' . $this->run_id,
            'am-outside-' . $this->run_id
        );


        /*
         * Публикация корневой категории.
         */
        $this->root_post = $this->create_post(
            'AM Root Article',
            $this->root_category
        );


        /*
         * Публикация дочерней категории.
         */
        $this->child_post = $this->create_post(
            'AM Child Article',
            $this->child_category
        );


        /*
         * Публикация третьего уровня.
         */
        $this->grandchild_post = $this->create_post(
            'AM Grandchild Article',
            $this->grandchild_category
        );


        /*
         * Посторонняя публикация.
         */
        $this->outside_post = $this->create_post(
            'AM Outside Article',
            $this->outside_category
        );


        /*
         * Неопубликованная статья блога.
         */
        $this->draft_post = $this->create_post(
            'AM Draft Article',
            $this->child_category,
            'draft'
        );
    }


    /**
     * Создаёт и проверяет тестовую категорию.
     */
    private function create_category(
        string $name,
        string $slug,
        int $parent = 0
    ): int {
        $this->verify_environment();

        $result = wp_insert_term(
            $name,
            'category',
            [
                'slug' => $slug,
                'parent' => $parent,
            ]
        );

        if (is_wp_error($result)) {
            throw new RuntimeException(
                'Category creation failed: '
                . $result->get_error_message()
            );
        }

        $id = (int) $result['term_id'];

        if ($id <= 0) {
            throw new RuntimeException(
                'Invalid category identifier.'
            );
        }

        /*
         * Регистрируем созданный объект до проверки,
         * чтобы гарантировать возможность очистки.
         */
        $this->category_ids[] = $id;

        $term = get_term(
            $id,
            'category'
        );

        dilitant_test_assert(
            $term instanceof WP_Term,
            'Created category cannot be resolved.'
        );

        dilitant_test_assert_same(
            $slug,
            $term->slug,
            'Unexpected category slug.'
        );

        dilitant_test_assert_same(
            $parent,
            (int) $term->parent,
            'Unexpected category parent.'
        );

        return $id;
    }


    /**
     * Создаёт и проверяет тестовую публикацию.
     */
    private function create_post(
        string $title,
        int $category_id,
        string $status = 'publish'
    ): int {
        $this->verify_environment();

        $result = wp_insert_post(
            [
                'post_type' => 'post',
                'post_title' =>
                    $title . ' ' . $this->run_id,
                'post_content' =>
                    'Article Model integration test fixture.',
                'post_status' => $status,
                'post_author' => $this->author_id,
                'post_category' => [$category_id],
                'comment_status' => 'closed',
                'ping_status' => 'closed',
            ],
            true
        );

        if (is_wp_error($result)) {
            throw new RuntimeException(
                'Post creation failed: '
                . $result->get_error_message()
            );
        }

        $id = (int) $result;

        if ($id <= 0) {
            throw new RuntimeException(
                'Invalid post identifier.'
            );
        }

        $this->post_ids[] = $id;

        $post = get_post($id);

        dilitant_test_assert(
            $post instanceof WP_Post,
            'Created post cannot be resolved.'
        );

        dilitant_test_assert_same(
            'post',
            $post->post_type,
            'Unexpected fixture post type.'
        );

        dilitant_test_assert_same(
            $status,
            $post->post_status,
            'Unexpected fixture post status.'
        );

        $actual_categories = wp_get_post_categories(
            $id,
            ['fields' => 'ids']
        );

        if (is_wp_error($actual_categories)) {
            throw new RuntimeException(
                'Unable to verify fixture categories.'
            );
        }

        dilitant_test_assert_same(
            [$category_id],
            dilitant_test_normalize_ids(
                $actual_categories
            ),
            'Fixture category assignment is incorrect.'
        );

        return $id;
    }


    /**
     * Ожидаемый полный состав дерева блога.
     */
    public function expected_category_ids(): array
    {
        return dilitant_test_normalize_ids([
            $this->root_category,
            $this->child_category,
            $this->grandchild_category,
        ]);
    }


    /**
     * Ожидаемый состав опубликованных статей.
     */
    public function expected_published_post_ids(): array
    {
        return dilitant_test_normalize_ids([
            $this->root_post,
            $this->child_post,
            $this->grandchild_post,
        ]);
    }


    /**
     * Возвращает идентификаторы всех созданных записей.
     */
    public function all_post_ids(): array
    {
        return dilitant_test_normalize_ids(
            $this->post_ids
        );
    }


    /**
     * Удаляет корневую категорию для AM-T05.
     *
     * Существующие записи сохраняются.
     * WordPress может изменить родительские связи
     * оставшихся категорий штатным механизмом.
     */
    public function remove_root_category(): void
    {
        $this->verify_environment();

        dilitant_test_assert(
            in_array(
                $this->root_category,
                $this->category_ids,
                true
            ),
            'Root category does not belong to fixtures.'
        );

        $root = get_term(
            $this->root_category,
            'category'
        );

        dilitant_test_assert(
            $root instanceof WP_Term,
            'Root fixture category is missing.'
        );

        dilitant_test_assert_same(
            self::ROOT_SLUG,
            $root->slug,
            'Unexpected root category identity.'
        );

        $result = wp_delete_term(
            $this->root_category,
            'category'
        );

        if (is_wp_error($result)) {
            throw new RuntimeException(
                'Root deletion failed: '
                . $result->get_error_message()
            );
        }

        dilitant_test_assert_same(
            true,
            $result,
            'Unable to remove root fixture category.'
        );

        dilitant_test_assert_same(
            false,
            get_category_by_slug(self::ROOT_SLUG),
            'Root category still exists after deletion.'
        );
    }


    /**
     * Удаляет созданные данным экземпляром записи
     * и категории в обратном порядке.
     *
     * Не выполняет очистку всей базы.
     */
    public function cleanup(): void
    {
        $this->verify_environment();

        $errors = [];


        /*
         * Сначала удаляем публикации.
         */
        foreach (array_reverse($this->post_ids) as $id) {
            try {
                if (get_post($id) === null) {
                    continue;
                }

                $deleted = wp_delete_post(
                    $id,
                    true
                );

                if (
                    !$deleted
                    || get_post($id) !== null
                ) {
                    $errors[] =
                        "Post {$id} was not deleted.";
                }

            } catch (Throwable $error) {
                $errors[] =
                    "Post {$id}: "
                    . $error->getMessage();
            }
        }


        /*
         * Затем удаляем категории:
         * от дочерних к родительским.
         */
        foreach (
            array_reverse($this->category_ids)
            as $id
        ) {
            try {
                $term = get_term(
                    $id,
                    'category'
                );

                if (is_wp_error($term)) {
                    /*
                     * WordPress может возвращать
                     * invalid_term для уже удалённого
                     * объекта.
                     */
                    if (
                        $term->get_error_code()
                        === 'invalid_term'
                    ) {
                        continue;
                    }

                    $errors[] =
                        "Category {$id} lookup failed.";

                    continue;
                }

                if ($term === null) {
                    continue;
                }

                $deleted = wp_delete_term(
                    $id,
                    'category'
                );

                if (
                    is_wp_error($deleted)
                    || $deleted !== true
                ) {
                    $errors[] =
                        "Category {$id} was not deleted.";

                    continue;
                }

                $remaining = get_term(
                    $id,
                    'category'
                );

                if ($remaining instanceof WP_Term) {
                    $errors[] =
                        "Category {$id} still exists.";
                }

            } catch (Throwable $error) {
                $errors[] =
                    "Category {$id}: "
                    . $error->getMessage();
            }
        }

        if ($errors !== []) {
            throw new RuntimeException(
                implode(PHP_EOL, $errors)
            );
        }

        $this->post_ids = [];
        $this->category_ids = [];

        echo "PASS: fixture cleanup\n";
    }
}