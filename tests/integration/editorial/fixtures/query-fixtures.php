<?php
/**
 * Расширенный тестовый набор Article Model.
 *
 * Нормативная основа:
 *     EDC-01
 *     AM-D02 — Защита инвариантов WP_Query
 *     AM-T06 — AM-T08
 *
 * Ответственность:
 *     - создание страницы;
 *     - создание приватной публикации;
 *     - создание запланированной публикации;
 *     - создание защищённой публикации;
 *     - создание записи фотоархива;
 *     - управление состоянием расширенного набора.
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


trait DilitantArticleFixtureQuery
{
    /**
     * Создаёт дополнительные тестовые записи.
     *
     * Метод вызывается после create().
     */
    public function create_query_fixtures(): void
    {
        $this->verify_environment();


        /*
         * Проверяем наличие базового набора.
         */
        if (!$this->base_created) {
            throw new RuntimeException(
                'Base fixtures have not been created.'
            );
        }


        /*
         * Не допускаем повторное создание,
         * включая повтор после частичной ошибки.
         */
        if (
            $this->query_created
            || $this->page_post !== 0
            || $this->private_post !== 0
            || $this->future_post !== 0
            || $this->password_post !== 0
            || $this->archive_post !== 0
            || count($this->post_ids) !== 5
        ) {
            throw new RuntimeException(
                'Query fixtures have already been created '
                . 'or initialization was incomplete.'
            );
        }


        /*
         * Подтверждаем корректность
         * базового набора.
         */
        $this->verify_base_fixtures();


        /*
         * Проверяем наличие действительного
         * зарегистрированного CPT фотоархива.
         */
        if (
            !post_type_exists(
                self::ARCHIVE_POST_TYPE
            )
        ) {
            throw new RuntimeException(
                'Archive post type is not registered: '
                . self::ARCHIVE_POST_TYPE
            );
        }


        /*
         * Создаём записи для проверки
         * инварианта типа.
         */
        $this->create_type_fixtures();


        /*
         * Создаём записи для проверки
         * статусной и парольной политики.
         */
        $this->create_visibility_fixtures();


        /*
         * Расширенный набор сформирован.
         */
        $this->query_created = true;


        /*
         * Проверяем полный состав данных.
         */
        $this->verify_query_fixtures();
    }


    /**
     * Создаёт записи запрещённых типов.
     *
     * Обе записи принудительно связываются
     * с категорией блога.
     *
     * Это позволяет проверить, что одной
     * принадлежности категории недостаточно.
     */
    private function create_type_fixtures(): void
    {
        /*
         * Стандартная страница WordPress.
         */
        $this->page_post =
            $this->create_post(
                'AM Page Fixture',
                $this->root_category,
                'publish',
                'page'
            );


        /*
         * Запись зарегистрированного CPT
         * фотоархива.
         */
        $this->archive_post =
            $this->create_post(
                'AM Archive Photo Fixture',
                $this->root_category,
                'publish',
                self::ARCHIVE_POST_TYPE
            );
    }


    /**
     * Создаёт записи для проверки
     * публичной доступности.
     */
    private function create_visibility_fixtures(): void
    {
        /*
         * Приватная статья.
         */
        $this->private_post =
            $this->create_post(
                'AM Private Article',
                $this->root_category,
                'private'
            );


        /*
         * Запланированная статья.
         */
        $this->future_post =
            $this->create_post(
                'AM Future Article',
                $this->root_category,
                'future'
            );


        /*
         * Опубликованная статья
         * с парольной защитой.
         */
        $this->password_post =
            $this->create_post(
                'AM Password Protected Article',
                $this->root_category,
                'publish',
                'post',
                self::TEST_POST_PASSWORD
            );
    }
}