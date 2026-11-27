<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';

require_once dirname(__DIR__)
    . '/recovery-tests/ambiguity.php';

function hfr2_create_duplicate_post(
    int $original_id,
    array $identity
): int {
    hfur_guard();

    $manifest = hfur_manifest();

    $record = hfr2_pending(
        $manifest['run_id'],
        'post',
        ['CREATING']
    );

    hfur_require(
        $record['identity'] === $identity,
        'Unexpected duplicate post identity.'
    );

    hfr2_verify_users($manifest);

    $duplicate_id = hf09_create_duplicate_post(
        $original_id,
        $identity
    );

    $probe = hfr2_probe($manifest['run_id']);

    $expected = [$original_id, $duplicate_id];
    sort($expected, SORT_NUMERIC);

    hfur_require(
        $probe['post_ids'] === $expected,
        'Unexpected ambiguous post candidates.'
    );

    hfur_guard();

    return $duplicate_id;
}

function hfr2_create_partial_category(
    array $identity
): int {
    hfur_guard();

    $manifest = hfur_manifest();

    $record = hfr2_pending(
        $manifest['run_id'],
        'category',
        ['CREATING']
    );

    hfur_require(
        $record['identity'] === $identity
        && $identity['slug'] === 'diletant-armenia'
        && $identity['parent'] === 0,
        'Unexpected partial category identity.'
    );

    hfr2_verify_users($manifest);

    $probe = hfr2_probe($manifest['run_id']);

    hfur_require(
        $probe['term_ids'] === []
        && $probe['taxonomy_ids'] === [],
        'Unexpected existing category.'
    );

    $id = hf09_create_partial_category($identity);

    $probe = hfr2_probe($manifest['run_id']);

    hfur_require(
        $probe['term_ids'] === [$id]
        && $probe['taxonomy_ids'] === []
        && $probe['partial_term_ids'] === [$id],
        'Partial category creation mismatch.'
    );

    hfur_guard();

    return $id;
}

function hfr2_repair_ambiguity(
    string $run_id,
    int $original_id,
    int $duplicate_id
): void {
    global $wpdb;

    hfur_guard();

    $manifest = hfur_require_manifest(
        $run_id,
        ['CLEANUP_FAILED']
    );

    $record = hfr2_pending(
        $run_id,
        'post',
        ['CLEANUP_FAILED']
    );

    hfr2_verify_users($manifest);

    hfur_require(
        $original_id > 0
        && $duplicate_id > 0
        && $original_id !== $duplicate_id,
        'Invalid ambiguous post identifiers.'
    );

    $identity = $record['identity'];

    $original = hf09_post_row($original_id);
    $duplicate = hf09_post_row($duplicate_id);

    hf09_verify_post_identity($original, $identity);
    hf09_verify_post_identity($duplicate, $identity);

    hfur_require(
        $duplicate['post_status'] === 'draft'
        && $duplicate['post_content'] ===
            $original['post_content']
        && (int) $duplicate['post_parent'] === 0,
        'Duplicate post identity changed.'
    );

    $registered_users = [];

    foreach ($manifest['objects'] as $item) {
        if ($item['kind'] === 'user') {
            $registered_users[] = $item['id'];
        }
    }

    hfur_require(
        in_array(
            $identity['author_id'],
            $registered_users,
            true
        ),
        'Duplicate post has foreign author.'
    );

    $before = hfr2_probe($run_id);

    $expected_ids = [$original_id, $duplicate_id];
    sort($expected_ids, SORT_NUMERIC);

    hfur_require(
        $before['post_ids'] === $expected_ids,
        'Unexpected ambiguous post candidates.'
    );

    $children = hfur_count(
        $wpdb->prepare(
            "SELECT COUNT(*) AS n
             FROM {$wpdb->posts}
             WHERE post_parent = %d",
            $duplicate_id
        )
    );

    $comments = hfur_count(
        $wpdb->prepare(
            "SELECT COUNT(*) AS n
             FROM {$wpdb->comments}
             WHERE comment_post_ID = %d",
            $duplicate_id
        )
    );

    hfur_require(
        $children === 0 && $comments === 0,
        'Duplicate post has unexpected dependencies.'
    );

    clean_post_cache($duplicate_id);

    hfur_guard();

    $deleted = wp_delete_post($duplicate_id, true);

    hfur_require(
        $deleted instanceof WP_Post
        && (int) $deleted->ID === $duplicate_id,
        'Duplicate post deletion failed.'
    );

    hfur_guard();

    hfur_require(
        hfur_count(
            $wpdb->prepare(
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->posts}
                 WHERE ID = %d",
                $duplicate_id
            )
        ) === 0,
        'Duplicate post still exists.'
    );

    hfur_require(
        hfur_count(
            $wpdb->prepare(
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->postmeta}
                 WHERE post_id = %d",
                $duplicate_id
            )
        ) === 0,
        'Residual duplicate post metadata.'
    );

    hfur_require(
        hfur_count(
            $wpdb->prepare(
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->term_relationships}
                 WHERE object_id = %d",
                $duplicate_id
            )
        ) === 0,
        'Residual duplicate post relationships.'
    );

    hf09_verify_post_identity(
        hf09_post_row($original_id),
        $identity
    );

    $expected = $before;
    $expected['post_ids'] = [$original_id];

    hfur_require(
        hfr2_probe($run_id) === $expected,
        'Controlled repair changed other fixture identities.'
    );

    hfur_require(
        hfur_manifest() === $manifest,
        'Controlled repair modified Manifest V2.'
    );

    hfr2_verify_users($manifest);

    hfur_guard();
}

function hfr2_repair_partial_category(
    string $run_id,
    int $id
): void {
    global $wpdb;

    hfur_guard();

    $manifest = hfur_require_manifest(
        $run_id,
        ['CLEANUP_FAILED']
    );

    $record = hfr2_pending(
        $run_id,
        'category',
        ['CLEANUP_FAILED']
    );

    hfr2_verify_users($manifest);

    $identity = $record['identity'];

    hfur_require(
        $id > 0
        && $identity['slug'] === 'diletant-armenia'
        && $identity['parent'] === 0,
        'Invalid partial category repair target.'
    );

    $before = hfr2_probe($run_id);

    hfur_require(
        $before['post_ids'] === []
        && $before['term_ids'] === [$id]
        && $before['taxonomy_ids'] === []
        && $before['partial_term_ids'] === [$id],
        'Unexpected partial category state.'
    );

    $rows = hfur_query(
        $wpdb->prepare(
            "SELECT term_id, name, slug, term_group
             FROM {$wpdb->terms}
             WHERE term_id = %d",
            $id
        )
    );

    hfur_require(
        count($rows) === 1
        && (int) $rows[0]['term_id'] === $id
        && $rows[0]['name'] === $identity['name']
        && $rows[0]['slug'] === $identity['slug']
        && (int) $rows[0]['term_group'] === 0,
        'Partial category identity mismatch.'
    );

    $taxonomy = hfur_count(
        $wpdb->prepare(
            "SELECT COUNT(*) AS n
             FROM {$wpdb->term_taxonomy}
             WHERE term_id = %d",
            $id
        )
    );

    $metadata = hfur_count(
        $wpdb->prepare(
            "SELECT COUNT(*) AS n
             FROM {$wpdb->termmeta}
             WHERE term_id = %d",
            $id
        )
    );

    hfur_require(
        $taxonomy === 0 && $metadata === 0,
        'Partial category has unexpected dependencies.'
    );

    hfur_guard();

    $affected = $wpdb->delete(
        $wpdb->terms,
        [
            'term_id' => $id,
            'name' => $identity['name'],
            'slug' => $identity['slug'],
            'term_group' => 0,
        ],
        ['%d', '%s', '%s', '%d']
    );

    $error = $wpdb->last_error;

    hfur_guard();

    hfur_require(
        $affected === 1 && $error === '',
        'Partial category repair failed.'
    );

    hfur_require(
        hfur_count(
            $wpdb->prepare(
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->terms}
                 WHERE term_id = %d",
                $id
            )
        ) === 0,
        'Partial category still exists.'
    );

    $expected = $before;
    $expected['term_ids'] = [];
    $expected['partial_term_ids'] = [];

    hfur_require(
        hfr2_probe($run_id) === $expected,
        'Category repair changed other fixture identities.'
    );

    hfur_require(
        hfur_manifest() === $manifest,
        'Category repair modified Manifest V2.'
    );

    hfr2_verify_users($manifest);

    hfur_guard();
}