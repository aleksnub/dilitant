<?php

declare(strict_types=1);

trait DilitantArticleFixtureCategories
{
    private function create_category(
        string $name,
        string $slug,
        int $parent = 0
    ): int {
        $this->verify_environment();

        if ($parent !== 0) {
            dilitant_test_assert(
                in_array($parent, $this->category_ids, true),
                'Parent category does not belong to fixtures.'
            );
        }

        dilitant_test_assert_same(
            false,
            get_category_by_slug($slug),
            "Fixture category already exists: {$slug}"
        );

        if (!str_ends_with($name, ' ' . $this->run_id)) {
            $name .= ' ' . $this->run_id;
        }

        $identity = [
            'name' => $name,
            'slug' => $slug,
            'parent' => $parent,
        ];

        $this->register_intent('category', $identity);

        $result = wp_insert_term($name, 'category', [
            'slug' => $slug,
            'parent' => $parent,
        ]);

        if (is_wp_error($result)) {
            throw new RuntimeException('Category creation failed.');
        }

        $id = (int) ($result['term_id'] ?? 0);
        $taxonomy_id = (int) ($result['term_taxonomy_id'] ?? 0);

        if ($id <= 0 || $taxonomy_id <= 0) {
            throw new RuntimeException('Invalid category identifier.');
        }

        $this->register_object(
            'category',
            $id,
            $identity,
            $taxonomy_id
        );

        if ($slug === self::ROOT_SLUG) {
            $this->root_category = $id;
        }

        $term = get_term($id, 'category');

        if (!$term instanceof WP_Term) {
            throw new RuntimeException('Created category unavailable.');
        }

        $this->verify_category_ownership($term);

        dilitant_test_assert_same(
            $parent,
            (int) $term->parent,
            'Unexpected category parent.'
        );

        return $id;
    }

    public function remove_root_category(): void
    {
        $this->verify_environment();

        $root = get_term($this->root_category, 'category');

        if (!$root instanceof WP_Term) {
            throw new RuntimeException('Root fixture category is missing.');
        }

        $this->verify_category_ownership($root);
        $this->verify_category_dependencies($root);

        if ($root->slug !== self::ROOT_SLUG) {
            throw new RuntimeException('Unexpected root category.');
        }

        $result = wp_delete_term($this->root_category, 'category');

        dilitant_test_assert_same(
            true,
            $result,
            'Unable to remove root fixture category.'
        );

        dilitant_test_assert_same(
            false,
            get_category_by_slug(self::ROOT_SLUG),
            'Root category still exists.'
        );
    }
}