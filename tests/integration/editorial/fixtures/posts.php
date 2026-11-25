<?php
/**
 * Создание тестовых записей Article Model.
 *
 * Ответственность:
 *     - формирование параметров публикации;
 *     - создание post, page и CPT;
 *     - создание запланированных записей;
 *     - создание защищённых записей;
 *     - назначение тестовых категорий;
 *     - регистрация созданных объектов.
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


trait DilitantArticleFixturePosts
{
    /**
     * Создаёт тестовую запись.
     *
     * Категория назначается явно,
     * независимо от типа записи.
     */
    private function create_post(
        string $title,
        int $category_id,
        string $status = 'publish',
        string $post_type = 'post',
        string $password = ''
    ): int {
        $this->verify_environment();


        /*
         * Проверяем наличие зарегистрированного
         * типа записи.
         */
        dilitant_test_assert(
            post_type_exists($post_type),
            "Unknown fixture post type: {$post_type}"
        );


        /*
         * Проверяем допустимость статуса.
         */
        dilitant_test_assert(
            in_array(
                $status,
                [
                    'publish',
                    'draft',
                    'private',
                    'future',
                ],
                true
            ),
            "Unsupported fixture status: {$status}"
        );


        /*
         * Проверяем принадлежность категории.
         */
        dilitant_test_assert(
            in_array(
                $category_id,
                $this->category_ids,
                true
            ),
            'Category does not belong to fixtures.'
        );


        /*
         * Формируем параметры записи.
         */
        $data = [
            'post_type' => $post_type,

            'post_title' =>
                $title . ' ' . $this->run_id,

            'post_content' =>
                'Article Model integration test fixture.',

            'post_status' => $status,

            'post_author' => $this->author_id,

            'post_password' => $password,

            'comment_status' => 'closed',

            'ping_status' => 'closed',
        ];


        /*
         * Для обычных записей назначаем
         * категорию штатным способом.
         */
        if ($post_type === 'post') {
            $data['post_category'] = [
                $category_id,
            ];
        }


        /*
         * Для будущих публикаций устанавливаем
         * согласованные даты.
         */
        if ($status === 'future') {
            $data = array_merge(
                $data,
                $this->build_future_post_dates()
            );
        }


        /*
         * Создаём запись.
         */
        $result = wp_insert_post(
            $data,
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


        /*
         * Регистрируем созданный объект
         * до назначения категории.
         */
        $this->post_ids[] = $id;


        /*
         * Назначаем категорию.
         */
        $this->assign_post_category(
            $id,
            $category_id
        );


        /*
         * Проверяем фактическое состояние.
         */
        $this->verify_post(
            $id,
            $category_id,
            $status,
            $post_type,
            $password
        );

        return $id;
    }


    /**
     * Формирует согласованные даты
     * запланированной публикации.
     *
     * Дата публикации устанавливается
     * на семь суток вперёд.
     */
    private function build_future_post_dates(): array
    {
        $future_gmt = gmdate(
            'Y-m-d H:i:s',
            time() + (7 * DAY_IN_SECONDS)
        );

        return [
            'post_date_gmt' => $future_gmt,

            'post_date' => get_date_from_gmt(
                $future_gmt,
                'Y-m-d H:i:s'
            ),
        ];
    }


    /**
     * Назначает записи ровно одну
     * тестовую категорию.
     *
     * Допускает назначение категории
     * страницам и CPT для моделирования
     * нарушений инварианта типа записи.
     */
    private function assign_post_category(
        int $post_id,
        int $category_id
    ): void {
        $this->verify_environment();


        /*
         * Проверяем принадлежность записи.
         */
        dilitant_test_assert(
            in_array(
                $post_id,
                $this->post_ids,
                true
            ),
            'Post does not belong to fixtures.'
        );


        /*
         * Проверяем принадлежность категории.
         */
        dilitant_test_assert(
            in_array(
                $category_id,
                $this->category_ids,
                true
            ),
            'Category does not belong to fixtures.'
        );


        /*
         * Назначаем категорию непосредственно
         * через API таксономий WordPress.
         */
        $assigned = wp_set_object_terms(
            $post_id,
            [$category_id],
            'category',
            false
        );

        if (is_wp_error($assigned)) {
            throw new RuntimeException(
                'Category assignment failed: '
                . $assigned->get_error_message()
            );
        }

        dilitant_test_assert(
            is_array($assigned)
            && count($assigned) === 1,
            'Unexpected category assignment result.'
        );
    }
}