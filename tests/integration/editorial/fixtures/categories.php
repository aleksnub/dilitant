<?php
/**
 * Управление тестовыми категориями
 * Article Model.
 *
 * Ответственность:
 *     - создание тестовых категорий;
 *     - проверка идентификаторов;
 *     - проверка родительских связей;
 *     - регистрация созданных объектов;
 *     - удаление корневой категории для AM-T05.
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


trait DilitantArticleFixtureCategories
{
    /**
     * Создаёт и проверяет тестовую категорию.
     *
     * Родительская категория должна
     * принадлежать данному экземпляру
     * тестовых данных.
     */
    private function create_category(
        string $name,
        string $slug,
        int $parent = 0
    ): int {
        $this->verify_environment();


        /*
         * Проверяем принадлежность родителя.
         */
        if ($parent !== 0) {
            dilitant_test_assert(
                in_array(
                    $parent,
                    $this->category_ids,
                    true
                ),
                'Parent category does not belong to fixtures.'
            );
        }


        /*
         * Запрещаем повторное использование
         * существующей категории.
         */
        $existing = get_category_by_slug(
            $slug
        );

        dilitant_test_assert_same(
            false,
            $existing,
            "Fixture category slug already exists: {$slug}"
        );


        /*
         * Создаём категорию.
         */
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
         * Регистрируем объект до проверки.
         *
         * При последующей ошибке категория
         * будет доступна механизму очистки.
         */
        $this->category_ids[] = $id;


        /*
         * Получаем фактическое состояние.
         */
        $term = get_term(
            $id,
            'category'
        );

        dilitant_test_assert(
            $term instanceof WP_Term,
            'Created category cannot be resolved.'
        );

        dilitant_test_assert_same(
            'category',
            $term->taxonomy,
            'Unexpected category taxonomy.'
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
     * Удаляет корневую категорию
     * для испытания AM-T05.
     *
     * Существующие публикации сохраняются.
     */
    public function remove_root_category(): void
    {
        $this->verify_environment();


        /*
         * Проверяем принадлежность категории
         * текущему тестовому экземпляру.
         */
        dilitant_test_assert(
            in_array(
                $this->root_category,
                $this->category_ids,
                true
            ),
            'Root category does not belong to fixtures.'
        );


        /*
         * Проверяем фактическую идентичность.
         */
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


        /*
         * Удаляем категорию.
         */
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


        /*
         * Подтверждаем отсутствие категории.
         */
        dilitant_test_assert_same(
            false,
            get_category_by_slug(
                self::ROOT_SLUG
            ),
            'Root category still exists after deletion.'
        );
    }
}