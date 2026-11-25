<?php

declare(strict_types=1);

trait DilitantArticleFixtureOwnership
{
    private function owned_record(string $kind, int $id): array
    {
        $record = $this->owned_objects[$kind . ':' . $id] ?? null;

        if (
            $id <= 0
            || !is_array($record)
            || ($record['id'] ?? null) !== $id
            || ($record['kind'] ?? null) !== $kind
            || !is_array($record['identity'] ?? null)
        ) {
            throw new RuntimeException("Unregistered fixture: {$kind}:{$id}");
        }

        return $record;
    }

    private function verify_post_ownership(WP_Post $post): void
    {
        $record = $this->owned_record('post', (int) $post->ID);
        $identity = $record['identity'];
        $marker = $identity['content_marker'] ?? null;

        if (
            $post->post_title !== ($identity['title'] ?? null)
            || $post->post_type !== ($identity['type'] ?? null)
            || !str_ends_with($post->post_title, ' ' . $this->run_id)
        ) {
            throw new RuntimeException('Post ownership mismatch.');
        }

        if ((int) $post->post_author !== ($identity['author_id'] ?? null)) {
            throw new RuntimeException('Post author mismatch.');
        }

        if (
            !is_string($marker)
            || preg_match(
                '/\AAM-CONTENT-' . $this->run_id . '-[0-9a-f]{16}\z/',
                $marker
            ) !== 1
            || !str_contains($post->post_content, $marker)
        ) {
            throw new RuntimeException('Post content marker mismatch.');
        }
    }

    private function verify_category_ownership(WP_Term $term): void
    {
        $record = $this->owned_record('category', (int) $term->term_id);
        $identity = $record['identity'];

        if (
            $term->taxonomy !== 'category'
            || $term->slug !== ($identity['slug'] ?? null)
            || $term->name !== ($identity['name'] ?? null)
            || (int) $term->term_taxonomy_id !== $record['taxonomy_id']
            || !str_ends_with($term->name, ' ' . $this->run_id)
        ) {
            throw new RuntimeException('Category ownership mismatch.');
        }
    }

    private function verify_category_dependencies(WP_Term $term): void
    {
        $objects = get_objects_in_term(
            (int) $term->term_id,
            'category'
        );

        if (is_wp_error($objects) || !is_array($objects)) {
            throw new RuntimeException('Unable to resolve category objects.');
        }

        foreach ($objects as $id) {
            $post = get_post((int) $id);

            if (!$post instanceof WP_Post) {
                throw new RuntimeException('Unresolved category object.');
            }

            $this->verify_post_ownership($post);
        }

        $children = get_terms([
            'taxonomy' => 'category',
            'parent' => (int) $term->term_id,
            'hide_empty' => false,
        ]);

        if (is_wp_error($children) || !is_array($children)) {
            throw new RuntimeException('Unable to resolve child categories.');
        }

        foreach ($children as $child) {
            if (!$child instanceof WP_Term) {
                throw new RuntimeException('Unexpected category object.');
            }

            $this->verify_category_ownership($child);
        }
    }
}