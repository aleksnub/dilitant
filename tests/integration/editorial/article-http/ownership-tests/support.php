<?php

declare(strict_types=1);

final class DilitantOwnershipTestJournal implements DilitantFixtureJournal
{
    private array $objects = [];

    public function intent(string $kind, array $identity): void
    {
        $this->objects[] = [
            'kind' => $kind,
            'id' => 0,
            'taxonomy_id' => 0,
            'identity' => $identity,
        ];
    }

    public function confirm(int $id, int $taxonomy_id = 0): void
    {
        $index = array_key_last($this->objects);

        if ($index === null || $this->objects[$index]['id'] !== 0) {
            throw new RuntimeException('Unexpected journal confirmation.');
        }

        $this->objects[$index]['id'] = $id;
        $this->objects[$index]['taxonomy_id'] = $taxonomy_id;
    }

    public function record(string $kind, int $id): array
    {
        foreach ($this->objects as $object) {
            if ($object['kind'] === $kind && $object['id'] === $id) {
                return $object;
            }
        }

        throw new RuntimeException("Test record missing: {$kind}:{$id}");
    }
}

function ownership_expect_rejection(
    callable $operation,
    string $expected
): void {
    try {
        $operation();
    } catch (RuntimeException $error) {
        if (!str_contains($error->getMessage(), $expected)) {
            throw new RuntimeException(
                'Unexpected rejection: ' . $error->getMessage(),
                0,
                $error
            );
        }

        return;
    }

    throw new RuntimeException('Unsafe operation was accepted.');
}

function ownership_cleanup_record(
    DilitantArticleFixtures $fixtures,
    array $record
): void {
    $restored = DilitantArticleFixtures::restore_for_cleanup(
        $fixtures->run_id(),
        [$record]
    );

    $restored->cleanup();
}

function ownership_update_post(
    int $id,
    array $data
): void {
    global $wpdb;

    dilitant_http_require_bootstrap();

    $result = $wpdb->update(
        $wpdb->posts,
        $data,
        ['ID' => $id]
    );

    if ($result === false || $wpdb->last_error !== '') {
        throw new RuntimeException('Test post mutation failed.');
    }

    clean_post_cache($id);
}

function ownership_update_category_name(
    int $id,
    string $name
): void {
    global $wpdb;

    dilitant_http_require_bootstrap();

    $result = $wpdb->update(
        $wpdb->terms,
        ['name' => $name],
        ['term_id' => $id]
    );

    if ($result === false || $wpdb->last_error !== '') {
        throw new RuntimeException('Test category mutation failed.');
    }

    clean_term_cache($id, 'category');
}

function ownership_post(int $id): WP_Post
{
    $post = get_post($id);

    if (!$post instanceof WP_Post) {
        throw new RuntimeException("Post {$id} does not exist.");
    }

    return $post;
}

function ownership_category(int $id): WP_Term
{
    $term = get_term($id, 'category');

    if (!$term instanceof WP_Term) {
        throw new RuntimeException("Category {$id} does not exist.");
    }

    return $term;
}

function ownership_post_categories(int $id): array
{
    $terms = wp_get_object_terms(
        $id,
        'category',
        ['fields' => 'ids']
    );

    if (is_wp_error($terms)) {
        throw new RuntimeException('Category lookup failed.');
    }

    return dilitant_test_normalize_ids($terms);
}

function ownership_create_foreign_post(
    string $title,
    int $author,
    int $category
): int {
    $id = wp_insert_post([
        'post_title' => $title,
        'post_content' => 'Independent ownership test object.',
        'post_author' => $author,
        'post_type' => 'post',
        'post_status' => 'draft',
        'post_category' => [$category],
    ], true);

    if (is_wp_error($id) || !is_int($id) || $id <= 0) {
        throw new RuntimeException('Foreign test post creation failed.');
    }

    return $id;
}

function ownership_delete_foreign_post(
    int $id,
    string $title
): void {
    $post = get_post($id);

    if ($post === null) {
        return;
    }

    if (
        !$post instanceof WP_Post
        || $post->post_title !== $title
        || $post->post_content !== 'Independent ownership test object.'
    ) {
        throw new RuntimeException('Foreign test post identity mismatch.');
    }

    wp_delete_post($id, true);

    dilitant_test_assert_same(
        null,
        get_post($id),
        'Foreign test post cleanup failed.'
    );
}