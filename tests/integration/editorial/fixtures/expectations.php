<?php
/**
 * Ожидаемые результаты испытаний Article Model.
 *
 * Ответственность:
 *     - предоставление ожидаемого дерева категорий;
 *     - предоставление ожидаемых публичных записей;
 *     - предоставление запрещённых записей;
 *     - предоставление идентификаторов тестовых данных;
 *     - предоставление синтетических параметров.
 *
 * Компонент не выполняет запросов Article Model
 * для вычисления ожидаемых результатов.
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


trait DilitantArticleFixtureExpectations
{
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
     * Ожидаемый состав публичных статей.
     *
     * Включает исключительно обычные,
     * опубликованные, незащищённые статьи
     * внутри дерева категорий блога.
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
     * Ожидаемый состав публичных статей
     * дочерней ветви.
     */
    public function expected_child_post_ids(): array
    {
        return dilitant_test_normalize_ids([
            $this->child_post,
            $this->grandchild_post,
        ]);
    }


    /**
     * Возвращает идентификаторы всех
     * созданных данным экземпляром записей.
     */
    public function all_post_ids(): array
    {
        return dilitant_test_normalize_ids(
            $this->post_ids
        );
    }


    /**
     * Возвращает идентификаторы всех
     * созданных данным экземпляром категорий.
     */
    public function all_category_ids(): array
    {
        return dilitant_test_normalize_ids(
            $this->category_ids
        );
    }


    /**
     * Возвращает идентификаторы объектов,
     * запрещённых для публичной выборки.
     */
    public function excluded_post_ids(): array
    {
        dilitant_test_assert(
            $this->query_created,
            'Query fixtures have not been created.'
        );

        return dilitant_test_normalize_ids([
            /*
             * Нарушение принадлежности
             * дереву категорий.
             */
            $this->outside_post,

            /*
             * Нарушение статуса публикации.
             */
            $this->draft_post,
            $this->private_post,
            $this->future_post,

            /*
             * Нарушение парольной политики.
             */
            $this->password_post,

            /*
             * Нарушение типа записи.
             */
            $this->page_post,
            $this->archive_post,
        ]);
    }


    /**
     * Возвращает идентификатор
     * типа записи фотоархива.
     */
    public function archive_post_type(): string
    {
        return self::ARCHIVE_POST_TYPE;
    }


    /**
     * Возвращает синтетический пароль
     * тестовой публикации.
     *
     * Не является паролем рабочей системы.
     */
    public function fixture_password(): string
    {
        return self::TEST_POST_PASSWORD;
    }


    /**
     * Возвращает уникальный идентификатор запуска.
     *
     * Используется в поисковых испытаниях
     * и для идентификации тестовых данных.
     */
    public function run_id(): string
    {
        return $this->run_id;
    }
}