<?php
/**
 * Очистка тестовых данных Article Model.
 *
 * Ответственность:
 *     - проверка изоляции перед удалением;
 *     - удаление запланированных событий;
 *     - удаление связей записей с категориями;
 *     - удаление тестовых записей;
 *     - удаление тестовых категорий;
 *     - подтверждение отсутствия объектов;
 *     - сброс состояния экземпляра.
 *
 * Удалению подлежат исключительно объекты,
 * созданные данным тестовым экземпляром.
 *
 * Массовое удаление содержимого базы данных
 * не допускается.
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


trait DilitantArticleFixtureCleanup
{
    /**
     * Удаляет все объекты данного экземпляра.
     *
     * Метод допускает вызов после частично
     * завершённой инициализации.
     *
     * Ошибка одного объекта не препятствует
     * попытке очистки остальных объектов.
     */
    public function cleanup(): void
    {
        $this->verify_environment();

        $errors = [];


        /*
         * 1. Удаляем публикации.
         */
        foreach (
            array_reverse($this->post_ids)
            as $id
        ) {
            try {
                $this->cleanup_post($id);

            } catch (Throwable $error) {
                $errors[] =
                    "Post {$id}: "
                    . $error->getMessage();
            }
        }


        /*
         * 2. Удаляем категории в порядке,
         * обратном порядку создания.
         */
        foreach (
            array_reverse($this->category_ids)
            as $id
        ) {
            try {
                $this->cleanup_category($id);

            } catch (Throwable $error) {
                $errors[] =
                    "Category {$id}: "
                    . $error->getMessage();
            }
        }


        /*
         * 3. При ошибке сохраняем состояние
         * для диагностики или повторной очистки.
         */
        if ($errors !== []) {
            throw new RuntimeException(
                implode(PHP_EOL, $errors)
            );
        }


        /*
         * 4. Подтверждаем отсутствие объектов.
         */
        $this->verify_cleanup();


        /*
         * 5. Сбрасываем состояние.
         */
        $this->reset_fixture_state();

        echo "PASS: fixture cleanup\n";
    }


    /**
     * Удаляет одну тестовую публикацию.
     */
    private function cleanup_post(
        int $id
    ): void {
        $this->verify_environment();


        /*
         * Проверяем принадлежность объекта.
         */
        dilitant_test_assert(
            in_array(
                $id,
                $this->post_ids,
                true
            ),
            'Post is not owned by this fixture instance.'
        );


        /*
         * Удаляем события запланированной
         * публикации, даже если сама запись
         * уже отсутствует.
         */
        $this->cleanup_post_schedule($id);


        /*
         * Получаем запись.
         */
        $post = get_post($id);


        /*
         * Если запись существует, проверяем
         * её принадлежность текущему запуску.
         */
        if ($post !== null) {
            dilitant_test_assert(
                $post instanceof WP_Post,
                'Unexpected post object.'
            );

            dilitant_test_assert(
                str_ends_with(
                    $post->post_title,
                    ' ' . $this->run_id
                ),
                'Post does not belong to current fixture run.'
            );


            /*
             * Явно удаляем связи с категориями.
             *
             * Не полагаемся на то, что taxonomy
             * category зарегистрирована
             * для данного post_type.
             */
            $this->cleanup_post_categories($id);


            /*
             * Удаляем запись без корзины.
             */
            $deleted = wp_delete_post(
                $id,
                true
            );

            dilitant_test_assert(
                $deleted !== false
                && $deleted !== null,
                'Post deletion failed.'
            );

        } else {

            /*
             * Запись могла быть удалена ранее.
             *
             * Проверяем и очищаем оставшиеся
             * связи с категориями.
             */
            $this->cleanup_post_categories($id);
        }


        /*
         * Подтверждаем отсутствие записи.
         */
        dilitant_test_assert_same(
            null,
            get_post($id),
            'Post still exists after cleanup.'
        );


        /*
         * Проверяем отсутствие будущих событий.
         */
        dilitant_test_assert_same(
            false,
            wp_next_scheduled(
                'publish_future_post',
                [$id]
            ),
            'Scheduled publication event still exists.'
        );
    }


    /**
     * Удаляет события запланированной
     * публикации конкретной записи.
     */
    private function cleanup_post_schedule(
        int $id
    ): void {
        $cleared = wp_clear_scheduled_hook(
            'publish_future_post',
            [$id],
            true
        );

        if (is_wp_error($cleared)) {
            throw new RuntimeException(
                'Scheduled event cleanup failed: '
                . $cleared->get_error_message()
            );
        }

        dilitant_test_assert(
            $cleared !== false,
            'Unable to clear scheduled publication event.'
        );

        dilitant_test_assert_same(
            false,
            wp_next_scheduled(
                'publish_future_post',
                [$id]
            ),
            'Scheduled publication event was not removed.'
        );
    }


    /**
     * Удаляет связи тестовой записи
     * с категориями.
     *
     * Требуется, в частности, для страниц
     * и CPT с принудительно назначенной
     * категорией.
     */
    private function cleanup_post_categories(
        int $id
    ): void {
        /*
         * Удаляем связи через WordPress API.
         */
        wp_delete_object_term_relationships(
            $id,
            'category'
        );


        /*
         * Подтверждаем отсутствие связей.
         */
        $remaining = wp_get_object_terms(
            $id,
            'category',
            [
                'fields' => 'ids',
            ]
        );

        if (is_wp_error($remaining)) {
            throw new RuntimeException(
                'Unable to verify category relationship cleanup: '
                . $remaining->get_error_message()
            );
        }

        dilitant_test_assert_same(
            [],
            $remaining,
            'Post category relationships still exist.'
        );
    }


    /**
     * Удаляет одну тестовую категорию.
     */
    private function cleanup_category(
        int $id
    ): void {
        $this->verify_environment();


        /*
         * Проверяем принадлежность категории.
         */
        dilitant_test_assert(
            in_array(
                $id,
                $this->category_ids,
                true
            ),
            'Category is not owned by this fixture instance.'
        );


        /*
         * Получаем фактическое состояние.
         */
        $term = get_term(
            $id,
            'category'
        );


        /*
         * Уже удалённая категория допустима.
         *
         * Это необходимо для AM-T05.
         */
        if ($term === null) {
            return;
        }

        if (is_wp_error($term)) {
            if (
                $term->get_error_code()
                === 'invalid_term'
            ) {
                return;
            }

            throw new RuntimeException(
                'Category lookup failed: '
                . $term->get_error_message()
            );
        }


        /*
         * Проверяем идентичность категории.
         */
        dilitant_test_assert(
            $term instanceof WP_Term,
            'Unexpected category object.'
        );

        dilitant_test_assert_same(
            'category',
            $term->taxonomy,
            'Unexpected taxonomy during cleanup.'
        );


        /*
         * Проверяем принадлежность
         * текущему тестовому запуску.
         */
        if ($id === $this->root_category) {
            dilitant_test_assert_same(
                self::ROOT_SLUG,
                $term->slug,
                'Unexpected root category identity.'
            );

        } else {
            dilitant_test_assert(
                str_ends_with(
                    $term->slug,
                    '-' . $this->run_id
                ),
                'Category does not belong to current fixture run.'
            );
        }


        /*
         * Удаляем категорию.
         */
        $deleted = wp_delete_term(
            $id,
            'category'
        );

        if (is_wp_error($deleted)) {
            throw new RuntimeException(
                'Category deletion failed: '
                . $deleted->get_error_message()
            );
        }

        dilitant_test_assert_same(
            true,
            $deleted,
            'Category was not deleted.'
        );


        /*
         * Проверяем отсутствие категории.
         */
        $this->assert_category_absent($id);
    }


    /**
     * Подтверждает отсутствие категории.
     */
    private function assert_category_absent(
        int $id
    ): void {
        $remaining = get_term(
            $id,
            'category'
        );

        if ($remaining === null) {
            return;
        }

        if (
            is_wp_error($remaining)
            && $remaining->get_error_code()
                === 'invalid_term'
        ) {
            return;
        }

        throw new RuntimeException(
            "Category {$id} absence was not confirmed."
        );
    }


    /**
     * Проверяет итоговое состояние
     * после удаления тестовых объектов.
     */
    private function verify_cleanup(): void
    {
        $this->verify_environment();


        /*
         * Проверяем отсутствие публикаций
         * и связанных запланированных событий.
         */
        foreach ($this->post_ids as $id) {
            dilitant_test_assert_same(
                null,
                get_post($id),
                "Fixture post {$id} still exists."
            );

            dilitant_test_assert_same(
                false,
                wp_next_scheduled(
                    'publish_future_post',
                    [$id]
                ),
                "Fixture post {$id} still has scheduled event."
            );

            $terms = wp_get_object_terms(
                $id,
                'category',
                [
                    'fields' => 'ids',
                ]
            );

            dilitant_test_assert_same(
                [],
                $terms,
                "Fixture post {$id} still has category relationships."
            );
        }


        /*
         * Проверяем отсутствие категорий.
         */
        foreach ($this->category_ids as $id) {
            $this->assert_category_absent($id);
        }


        /*
         * Проверяем отсутствие созданной
         * данным экземпляром корневой категории.
         *
         * Если экземпляр не создавал корень,
         * существующие чужие категории
         * не относятся к его ответственности.
         */
        if ($this->root_category !== 0) {
            dilitant_test_assert_same(
                false,
                get_category_by_slug(
                    self::ROOT_SLUG
                ),
                'Fixture root category still exists.'
            );
        }
    }


    /**
     * Сбрасывает состояние экземпляра.
     *
     * Вызывается только после подтверждённой
     * успешной очистки.
     */
    private function reset_fixture_state(): void
    {
        /*
         * Списки принадлежащих объектов.
         */
        $this->post_ids = [];

        $this->category_ids = [];


        /*
         * Состояние инициализации.
         */
        $this->base_created = false;

        $this->query_created = false;


        /*
         * Категории.
         */
        $this->root_category = 0;

        $this->child_category = 0;

        $this->grandchild_category = 0;

        $this->outside_category = 0;


        /*
         * Базовые записи.
         */
        $this->root_post = 0;

        $this->child_post = 0;

        $this->grandchild_post = 0;

        $this->outside_post = 0;

        $this->draft_post = 0;


        /*
         * Дополнительные записи.
         */
        $this->page_post = 0;

        $this->private_post = 0;

        $this->future_post = 0;

        $this->password_post = 0;

        $this->archive_post = 0;
    }
}