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

require_once __DIR__ . '/lock.php';

trait DilitantArticleFixtureBase
{
    public function create(): void
    {
        $this->verify_environment();

        dilitant_fixture_acquire_lock();

        if ($this->journal === null) {
            $manifest = get_option(
                'dilitant_http_fixture_manifest_v1',
                null
            );

            if (
                $manifest !== null
                && (
                    !is_array($manifest)
                    || ($manifest['state'] ?? null) !== 'CLEANED'
                )
            ) {
                throw new RuntimeException(
                    'An HTTP fixture run is active.'
                );
            }
        }

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

        if (
            $this->user_ids !== []
            && !$this->users_created
        ) {
            throw new RuntimeException(
                'Fixture user initialization is incomplete.'
            );
        }

        if ($this->users_created) {
            $this->verify_users();
        }

        if (get_category_by_slug(self::ROOT_SLUG) !== false) {
            throw new RuntimeException(
                'Blog root already exists. A clean environment is required.'
            );
        }

        $this->create_base_categories();
        $this->create_base_posts();

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

        $this->base_created = true;

        $this->verify_base_fixtures();
    }

    private function create_base_categories(): void
    {
        $this->root_category = $this->create_category(
            'AM Root',
            self::ROOT_SLUG
        );

        $this->child_category = $this->create_category(
            'AM Child ' . $this->run_id,
            'am-child-' . $this->run_id,
            $this->root_category
        );

        $this->grandchild_category = $this->create_category(
            'AM Grandchild ' . $this->run_id,
            'am-grandchild-' . $this->run_id,
            $this->child_category
        );

        $this->outside_category = $this->create_category(
            'AM Outside ' . $this->run_id,
            'am-outside-' . $this->run_id
        );
    }

    private function create_base_posts(): void
    {
        $this->root_post = $this->create_post(
            'AM Root Article',
            $this->root_category
        );

        $this->child_post = $this->create_post(
            'AM Child Article',
            $this->child_category
        );

        $this->grandchild_post = $this->create_post(
            'AM Grandchild Article',
            $this->grandchild_category
        );

        $this->outside_post = $this->create_post(
            'AM Outside Article',
            $this->outside_category
        );

        $this->draft_post = $this->create_post(
            'AM Draft Article',
            $this->child_category,
            'draft',
            'post',
            '',
            'author_a'
        );
    }
}