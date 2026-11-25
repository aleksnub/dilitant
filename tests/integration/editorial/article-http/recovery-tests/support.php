<?php

declare(strict_types=1);

function hf09_require(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function hf09_query(string $sql): array
{
    global $wpdb;

    dilitant_http_require_bootstrap();
    dilitant_fixture_assert_lock();

    $rows = $wpdb->get_results($sql, ARRAY_A);

    hf09_require(
        $wpdb->last_error === '' && is_array($rows),
        'Independent SQL query failed.'
    );

    dilitant_fixture_assert_lock();

    return $rows;
}

function hf09_count(string $sql): int
{
    $rows = hf09_query($sql);

    hf09_require(
        count($rows) === 1
        && isset($rows[0]['n'])
        && ctype_digit((string) $rows[0]['n']),
        'Invalid SQL count result.'
    );

    return (int) $rows[0]['n'];
}

function hf09_json(array $data): void
{
    fwrite(
        STDOUT,
        json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . PHP_EOL
    );

    fflush(STDOUT);
}

function hf09_run_id(string $run_id): string
{
    hf09_require(
        preg_match('/\A[0-9a-f]{12}\z/', $run_id) === 1,
        'Invalid test run ID.'
    );

    return $run_id;
}

function hf09_id(string $value): int
{
    hf09_require(
        ctype_digit($value)
        && (int) $value > 0
        && (string) (int) $value === $value,
        'Invalid test object ID.'
    );

    return (int) $value;
}

function hf09_manifest(): array
{
    global $wpdb;

    $rows = hf09_query(
        $wpdb->prepare(
            "SELECT option_value
             FROM {$wpdb->options}
             WHERE option_name = %s",
            DilitantHttpFixtureManifest::OPTION
        )
    );

    hf09_require(
        count($rows) === 1,
        'Persistent manifest unavailable.'
    );

    $data = unserialize(
        $rows[0]['option_value'],
        ['allowed_classes' => false]
    );

    hf09_require(
        is_array($data)
        && ($data['schema_version'] ?? null) === 1
        && ($data['database_name'] ?? null) === DILITANT_TEST_DB_NAME
        && ($data['table_prefix'] ?? null) === DILITANT_TEST_TABLE_PREFIX,
        'Invalid persistent manifest.'
    );

    return $data;
}

function hf09_checkpoint(string $stage, array $extra = []): never
{
    dilitant_fixture_assert_lock();

    hf09_json([
        'event' => 'CRASH_READY',
        'stage' => $stage,
        'pid' => getmypid(),
        'manifest' => hf09_manifest(),
        'extra' => $extra,
    ]);

    $deadline = microtime(true) + 60;

    while (microtime(true) < $deadline) {
        sleep(1);
    }

    throw new RuntimeException(
        'Crash controller did not terminate worker.'
    );
}

function hf09_assert_clean_start(): void
{
    $manifest = DilitantHttpFixtureManifest::load();

    hf09_require(
        $manifest->state() === 'CLEANED',
        'Previous fixture run is unfinished.'
    );
}

function hf09_pending(string $run_id, string $kind): array
{
    $data = hf09_manifest();

    hf09_require(
        $data['run_id'] === hf09_run_id($run_id)
        && in_array(
            $data['state'],
            ['CREATING', 'CLEANUP_FAILED'],
            true
        ),
        'Unexpected recovery manifest.'
    );

    $objects = array_values(array_filter(
        $data['objects'],
        static fn(array $object): bool => $object['id'] === 0
    ));

    hf09_require(
        count($objects) === 1
        && $objects[0]['kind'] === $kind,
        'Expected exactly one pending intent.'
    );

    return $objects[0]['identity'];
}

function hf09_post_row(int $id): array
{
    global $wpdb;

    $rows = hf09_query(
        $wpdb->prepare(
            "SELECT *
             FROM {$wpdb->posts}
             WHERE ID = %d",
            $id
        )
    );

    hf09_require(
        count($rows) === 1,
        "Post {$id} unavailable."
    );

    return $rows[0];
}

function hf09_verify_post_identity(
    array $row,
    array $identity
): void {
    hf09_require(
        $row['post_title'] === $identity['title']
        && $row['post_type'] === $identity['type']
        && (int) $row['post_author'] === $identity['author_id']
        && str_contains(
            $row['post_content'],
            $identity['content_marker']
        ),
        'Test post identity mismatch.'
    );
}

function hf09_probe(string $run_id): array
{
    global $wpdb;

    hf09_run_id($run_id);

    $posts = hf09_query(
        $wpdb->prepare(
            "SELECT ID
             FROM {$wpdb->posts}
             WHERE LOCATE(BINARY %s, BINARY post_title) > 0
                OR LOCATE(BINARY %s, BINARY post_content) > 0
             ORDER BY ID",
            ' ' . $run_id,
            'AM-CONTENT-' . $run_id . '-'
        )
    );

    $terms = hf09_query(
        $wpdb->prepare(
            "SELECT t.term_id, tt.term_taxonomy_id
             FROM {$wpdb->terms} t
             LEFT JOIN {$wpdb->term_taxonomy} tt
                 ON tt.term_id = t.term_id
             WHERE LOCATE(BINARY %s, BINARY t.name) > 0
                OR LOCATE(BINARY %s, BINARY t.slug) > 0
                OR BINARY t.slug = BINARY %s
             ORDER BY t.term_id, tt.term_taxonomy_id",
            ' ' . $run_id,
            '-' . $run_id,
            'diletant-armenia'
        )
    );

    $result = [
        'post_ids' => array_map(
            static fn(array $row): int => (int) $row['ID'],
            $posts
        ),
        'term_ids' => [],
        'taxonomy_ids' => [],
        'partial_term_ids' => [],
    ];

    foreach ($terms as $term) {
        $id = (int) $term['term_id'];

        $result['term_ids'][] = $id;

        if ($term['term_taxonomy_id'] === null) {
            $result['partial_term_ids'][] = $id;
        } else {
            $result['taxonomy_ids'][] =
                (int) $term['term_taxonomy_id'];
        }
    }

    $result['term_ids'] = array_values(
        array_unique($result['term_ids'])
    );

    return $result;
}

function hf09_audit(array $post_ids = []): array
{
    global $wpdb;

    $checks = [
        'fixture_posts' =>
            "SELECT COUNT(*) AS n FROM {$wpdb->posts}
             WHERE post_title LIKE 'AM %'
                OR post_title LIKE 'HF-M08 %'
                OR post_title LIKE 'HF-M09 %'
                OR post_content LIKE '%AM-CONTENT-%'",

        'fixture_terms' =>
            "SELECT COUNT(*) AS n FROM {$wpdb->terms}
             WHERE name LIKE 'AM %'
                OR name LIKE 'HF-M08 %'
                OR name LIKE 'HF-M09 %'
                OR slug = 'diletant-armenia'",

        'orphan_postmeta' =>
            "SELECT COUNT(*) AS n FROM {$wpdb->postmeta} m
             LEFT JOIN {$wpdb->posts} p ON p.ID = m.post_id
             WHERE p.ID IS NULL",

        'orphan_termmeta' =>
            "SELECT COUNT(*) AS n FROM {$wpdb->termmeta} m
             LEFT JOIN {$wpdb->terms} t ON t.term_id = m.term_id
             WHERE t.term_id IS NULL",

        'orphan_taxonomy' =>
            "SELECT COUNT(*) AS n FROM {$wpdb->term_taxonomy} tt
             LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
             WHERE t.term_id IS NULL",

        'orphan_relationships' =>
            "SELECT COUNT(*) AS n
             FROM {$wpdb->term_relationships} r
             LEFT JOIN {$wpdb->posts} p ON p.ID = r.object_id
             LEFT JOIN {$wpdb->term_taxonomy} tt
                 ON tt.term_taxonomy_id = r.term_taxonomy_id
             WHERE p.ID IS NULL OR tt.term_taxonomy_id IS NULL",

        'orphan_comments' =>
            "SELECT COUNT(*) AS n FROM {$wpdb->comments} c
             LEFT JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
             WHERE p.ID IS NULL",

        'orphan_commentmeta' =>
            "SELECT COUNT(*) AS n FROM {$wpdb->commentmeta} m
             LEFT JOIN {$wpdb->comments} c ON c.comment_ID = m.comment_id
             WHERE c.comment_ID IS NULL",

        'orphan_usermeta' =>
            "SELECT COUNT(*) AS n FROM {$wpdb->usermeta} m
             LEFT JOIN {$wpdb->users} u ON u.ID = m.user_id
             WHERE u.ID IS NULL",
    ];

    $counts = [];

    foreach ($checks as $name => $sql) {
        $counts[$name] = hf09_count($sql);

        hf09_require(
            $counts[$name] === 0,
            "Database audit failed: {$name}={$counts[$name]}"
        );
    }

    foreach ($post_ids as $id) {
        hf09_require(
            wp_next_scheduled('publish_future_post', [$id]) === false,
            "Residual scheduled event: {$id}"
        );
    }

    $tables = [
        'posts' => [$wpdb->posts, 'ID'],
        'postmeta' => [$wpdb->postmeta, 'meta_id, post_id'],
        'terms' => [$wpdb->terms, 'term_id'],
        'termmeta' => [$wpdb->termmeta, 'meta_id, term_id'],
        'taxonomy' => [
            $wpdb->term_taxonomy,
            'term_taxonomy_id, term_id, parent',
        ],
        'relationships' => [
            $wpdb->term_relationships,
            'object_id, term_taxonomy_id, term_order',
        ],
        'comments' => [$wpdb->comments, 'comment_ID, comment_post_ID'],
        'commentmeta' => [$wpdb->commentmeta, 'meta_id, comment_id'],
        'users' => [$wpdb->users, 'ID'],
        'usermeta' => [$wpdb->usermeta, 'umeta_id, user_id'],
    ];

    $baseline = [];

    foreach ($tables as $name => [$table, $columns]) {
        $rows = hf09_query(
            "SELECT {$columns}
             FROM {$table}
             ORDER BY {$columns}"
        );

        $baseline[$name] = [
            'count' => count($rows),
            'digest' => hash('sha256', serialize($rows)),
        ];
    }

    hf09_require(
        get_user_by('login', 'testadmin') instanceof WP_User,
        'Existing test administrator is missing.'
    );

    dilitant_fixture_assert_lock();

    return [
        'checks' => $counts,
        'baseline' => $baseline,
    ];
}