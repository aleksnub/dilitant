<?php
/**
 * Проверка фактического состояния
 * тестовых записей Article Model.
 *
 * Ответственность:
 *     - проверка существования записи;
 *     - проверка типа;
 *     - проверка статуса;
 *     - проверка парольной защиты;
 *     - проверка автора;
 *     - проверка фактических категорий;
 *     - проверка даты будущей публикации;
 *     - проверка расширенного тестового набора.
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


trait DilitantArticleFixtureVerification
{
    /**
     * Проверяет фактические свойства записи.
     */
    private function verify_post(
        int $id,
        int $category_id,
        string $status,
        string $post_type,
        string $password = ''
    ): void {
        /*
         * Проверяем принадлежность объекта.
         */
        dilitant_test_assert(
            in_array(
                $id,
                $this->post_ids,
                true
            ),
            'Verified post does not belong to fixtures.'
        );


        /*
         * Получаем фактическую запись.
         */
        $post = get_post($id);

        dilitant_test_assert(
            $post instanceof WP_Post,
            "Created post {$id} cannot be resolved."
        );


        /*
         * Проверяем тип.
         */
        dilitant_test_assert_same(
            $post_type,
            $post->post_type,
            "Unexpected post type for fixture {$id}."
        );


        /*
         * Проверяем статус.
         */
        dilitant_test_assert_same(
            $status,
            $post->post_status,
            "Unexpected post status for fixture {$id}."
        );


        /*
         * Проверяем парольную защиту.
         */
        dilitant_test_assert_same(
            $password,
            $post->post_password,
            "Unexpected password state for fixture {$id}."
        );


        /*
         * Проверяем автора.
         */
        dilitant_test_assert_same(
            $this->author_id,
            (int) $post->post_author,
            "Unexpected author for fixture {$id}."
        );


        /*
         * Проверяем принадлежность категории.
         */
        $this->verify_post_categories(
            $id,
            $category_id
        );


        /*
         * Дополнительные условия
         * для будущей публикации.
         */
        if ($status === 'future') {
            $this->verify_future_post(
                $post
            );
        }
    }


    /**
     * Проверяет фактические связи записи
     * с терминами таксономии category.
     *
     * Применяется одинаково
     * для post, page и CPT.
     */
    private function verify_post_categories(
        int $post_id,
        int $category_id
    ): void {
        $actual_categories = wp_get_object_terms(
            $post_id,
            'category',
            [
                'fields' => 'ids',
            ]
        );

        if (is_wp_error($actual_categories)) {
            throw new RuntimeException(
                'Unable to verify fixture categories: '
                . $actual_categories->get_error_message()
            );
        }

        dilitant_test_assert_same(
            [$category_id],
            dilitant_test_normalize_ids(
                $actual_categories
            ),
            "Incorrect categories for fixture {$post_id}."
        );
    }


    /**
     * Проверяет дату и статус
     * запланированной публикации.
     */
    private function verify_future_post(
        WP_Post $post
    ): void {
        dilitant_test_assert_same(
            'future',
            $post->post_status,
            'Future fixture has incorrect status.'
        );


        /*
         * Проверяем UTC-время публикации.
         */
        $timestamp = strtotime(
            $post->post_date_gmt . ' UTC'
        );

        dilitant_test_assert(
            $timestamp !== false
            && $timestamp > time(),
            'Future fixture date is not in the future.'
        );


        /*
         * Проверяем согласованность
         * локальной даты и UTC.
         */
        dilitant_test_assert_same(
            get_date_from_gmt(
                $post->post_date_gmt,
                'Y-m-d H:i:s'
            ),
            $post->post_date,
            'Future fixture dates are inconsistent.'
        );
    }


    /**
     * Проверяет базовые тестовые данные.
     */
    public function verify_base_fixtures(): void
    {
        $this->verify_environment();

        dilitant_test_assert(
            $this->base_created,
            'Base fixtures are not available.'
        );


        $this->verify_post(
            $this->root_post,
            $this->root_category,
            'publish',
            'post'
        );

        $this->verify_post(
            $this->child_post,
            $this->child_category,
            'publish',
            'post'
        );

        $this->verify_post(
            $this->grandchild_post,
            $this->grandchild_category,
            'publish',
            'post'
        );

        $this->verify_post(
            $this->outside_post,
            $this->outside_category,
            'publish',
            'post'
        );

        $this->verify_post(
            $this->draft_post,
            $this->child_category,
            'draft',
            'post'
        );
    }


    /**
     * Проверяет полный расширенный набор
     * тестовых данных AM-D02.
     */
    public function verify_query_fixtures(): void
    {
        $this->verify_environment();

        dilitant_test_assert(
            $this->base_created
            && $this->query_created,
            'Complete query fixtures are not available.'
        );


        /*
         * Повторно проверяем базовые данные.
         */
        $this->verify_base_fixtures();


        /*
         * Страница.
         */
        $this->verify_post(
            $this->page_post,
            $this->root_category,
            'publish',
            'page'
        );


        /*
         * Приватная статья.
         */
        $this->verify_post(
            $this->private_post,
            $this->root_category,
            'private',
            'post'
        );


        /*
         * Запланированная статья.
         */
        $this->verify_post(
            $this->future_post,
            $this->root_category,
            'future',
            'post'
        );


        /*
         * Защищённая статья.
         */
        $this->verify_post(
            $this->password_post,
            $this->root_category,
            'publish',
            'post',
            self::TEST_POST_PASSWORD
        );


        /*
         * Запись фотоархива.
         */
        $this->verify_post(
            $this->archive_post,
            $this->root_category,
            'publish',
            self::ARCHIVE_POST_TYPE
        );


        /*
         * Проверяем полный состав объектов.
         */
        dilitant_test_assert_same(
            10,
            count($this->post_ids),
            'Unexpected number of query fixture posts.'
        );

        dilitant_test_assert_same(
            4,
            count($this->category_ids),
            'Unexpected number of fixture categories.'
        );
    }
}