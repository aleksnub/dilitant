<?php

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
    public function create_query_fixtures(): void
    {
        $this->verify_environment();
        dilitant_fixture_assert_lock();

        if (!$this->base_created) {
            throw new RuntimeException(
                'Base fixtures have not been created.'
            );
        }

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

        $this->verify_base_fixtures();

        if (!post_type_exists(self::ARCHIVE_POST_TYPE)) {
            throw new RuntimeException(
                'Archive post type is not registered: '
                . self::ARCHIVE_POST_TYPE
            );
        }

        $this->create_type_fixtures();
        $this->create_visibility_fixtures();

        $this->query_created = true;

        $this->verify_query_fixtures();
    }

    private function create_type_fixtures(): void
    {
        $this->page_post = $this->create_post(
            'AM Page Fixture',
            $this->root_category,
            'publish',
            'page'
        );

        $this->archive_post = $this->create_post(
            'AM Archive Photo Fixture',
            $this->root_category,
            'publish',
            self::ARCHIVE_POST_TYPE
        );
    }

    private function create_visibility_fixtures(): void
    {
        $this->private_post = $this->create_post(
            'AM Private Article',
            $this->root_category,
            'private',
            'post',
            '',
            'author_b'
        );

        $this->future_post = $this->create_post(
            'AM Future Article',
            $this->root_category,
            'future'
        );

        $this->password_post = $this->create_post(
            'AM Password Protected Article',
            $this->root_category,
            'publish',
            'post',
            self::TEST_POST_PASSWORD
        );
    }
}