<?php
/**
 * Базовый тестовый набор Article Model.
 *
 * Нормативная основа:
 *     EDC-01
 *     AM-D01
 *     AM-T01 — AM-T05
 *
 * Ответственность:
 *     - создание дерева категорий;
 *     - создание базовых публикаций;
 *     - управление состоянием инициализации;
 *     - сохранение совместимости испытаний AM-D01.
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


trait DilitantArticleFixtureBase
{
    /**
     * Создаёт базовый набор тестовых данных.
     *
     * Дополнительные данные AM-D02
     * создаются отдельным методом.
     */
    public function create(): void
    {
        $this->verify_environment();


        /*
         * Запрещаем повторное создание
         * без предварительной очистки.
         */
        if (
            $this->base_created
            || $this->query_created
            || $this->category_ids !== []
            || $this->post_ids !== []
        ) {
            throw new RuntimeException(
                'Fixtures have already been created.'
            );
        }


        /*
         * Не допускаем использование
         * существующей корневой категории.
         */
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
         * Создаём категории.
         */
        $this->create_base_categories();


        /*
         * Создаём публикации.
         */
        $this->create_base_posts();


        /*
         * Подтверждаем состав данных.
         */
        dilitant_test_assert_same(
            4,
            count($this->category_ids),
            'Unexpected number of base categories.'
        );

        dilitant_test_assert_same(
            5,
            count($this->post_ids),
            'Unexpected number of base posts.'
        );


        /*
         * Базовый набор сформирован.
         */
        $this->base_created = true;


        /*
         * Проверяем итоговое состояние.
         */
        $this->verify_base_fixtures();
    }


    /**
     * Создаёт дерево тестовых категорий.
     */
    private function create_base_categories(): void
    {
        /*
         * Корневая категория блога.
         */
        $this->root_category =
            $this->create_category(
                'AM Root',
                self::ROOT_SLUG
            );


        /*
         * Категория второго уровня.
         */
        $this->child_category =
            $this->create_category(
                'AM Child ' . $this->run_id,
                'am-child-' . $this->run_id,
                $this->root_category
            );


        /*
         * Категория третьего уровня.
         */
        $this->grandchild_category =
            $this->create_category(
                'AM Grandchild ' . $this->run_id,
                'am-grandchild-' . $this->run_id,
                $this->child_category
            );


        /*
         * Посторонняя категория.
         */
        $this->outside_category =
            $this->create_category(
                'AM Outside ' . $this->run_id,
                'am-outside-' . $this->run_id
            );
    }


    /**
     * Создаёт базовые тестовые публикации.
     */
    private function create_base_posts(): void
    {
        /*
         * Статья корневой категории.
         */
        $this->root_post =
            $this->create_post(
                'AM Root Article',
                $this->root_category
            );


        /*
         * Статья дочерней категории.
         */
        $this->child_post =
            $this->create_post(
                'AM Child Article',
                $this->child_category
            );


        /*
         * Статья третьего уровня.
         */
        $this->grandchild_post =
            $this->create_post(
                'AM Grandchild Article',
                $this->grandchild_category
            );


        /*
         * Посторонняя публикация.
         */
        $this->outside_post =
            $this->create_post(
                'AM Outside Article',
                $this->outside_category
            );


        /*
         * Черновик статьи блога.
         */
        $this->draft_post =
            $this->create_post(
                'AM Draft Article',
                $this->child_category,
                'draft'
            );
    }
}