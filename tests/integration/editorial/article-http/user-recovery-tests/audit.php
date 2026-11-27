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

require_once dirname(__DIR__) . '/recovery-tests/support.php';

function hfur_audit_table(
    string $table,
    string $order
): array {
    $rows = hfur_query(
        "SELECT *
         FROM {$table}
         ORDER BY {$order}"
    );

    return [
        'count' => count($rows),
        'digest' => hash(
            'sha256',
            serialize($rows)
        ),
    ];
}

function hfur_audit_protected_user(): array
{
    global $wpdb;

    hfur_guard();

    $id = hfur_protected_id();

    $users = hfur_query(
        $wpdb->prepare(
            "SELECT *
             FROM {$wpdb->users}
             WHERE ID = %d",
            $id
        )
    );

    hfur_require(
        count($users) === 1
        && (int) $users[0]['ID'] === $id
        && $users[0]['user_login'] === 'testadmin',
        'Protected administrator audit failed.'
    );

    $metadata = hfur_query(
        $wpdb->prepare(
            "SELECT *
             FROM {$wpdb->usermeta}
             WHERE user_id = %d
             ORDER BY umeta_id",
            $id
        )
    );

    hfur_guard();

    return [
        'id' => $id,
        'login' => 'testadmin',
        'metadata_count' => count($metadata),
        'digest' => hash(
            'sha256',
            serialize([
                'user' => $users[0],
                'metadata' => $metadata,
            ])
        ),
    ];
}

function hfur_audit_user_ids(array $ids): void
{
    $protected_id = hfur_protected_id();

    foreach (array_unique($ids) as $id) {
        hfur_require(
            is_int($id)
            && $id > 0
            && $id !== $protected_id,
            'Invalid audited fixture user ID.'
        );

        hfur_require(
            hfur_user_rows($id) === [],
            "Residual fixture user: {$id}."
        );

        foreach (
            hfur_user_dependencies($id)
            as $name => $count
        ) {
            hfur_require(
                $count === 0,
                "Residual user {$id} dependency: {$name}."
            );
        }
    }
}

function hfur_audit_baseline(): array
{
    global $wpdb;

    $tables = [
        'posts' => [
            $wpdb->posts,
            'ID',
        ],
        'postmeta' => [
            $wpdb->postmeta,
            'meta_id',
        ],
        'terms' => [
            $wpdb->terms,
            'term_id',
        ],
        'termmeta' => [
            $wpdb->termmeta,
            'meta_id',
        ],
        'taxonomy' => [
            $wpdb->term_taxonomy,
            'term_taxonomy_id',
        ],
        'relationships' => [
            $wpdb->term_relationships,
            'object_id, term_taxonomy_id',
        ],
        'comments' => [
            $wpdb->comments,
            'comment_ID',
        ],
        'commentmeta' => [
            $wpdb->commentmeta,
            'meta_id',
        ],
        'users' => [
            $wpdb->users,
            'ID',
        ],
        'usermeta' => [
            $wpdb->usermeta,
            'umeta_id',
        ],
        'links' => [
            $wpdb->links,
            'link_id',
        ],
    ];

    $result = [];

    foreach ($tables as $name => [$table, $order]) {
        $result[$name] = hfur_audit_table(
            $table,
            $order
        );
    }

    return $result;
}

function hfur_audit(array $user_ids = []): array
{
    global $wpdb;

    hfur_guard();

    $manifest = hfur_manifest();

    hfur_require(
        $manifest['state'] === 'CLEANED',
        'Independent audit requires CLEANED manifest.'
    );

    $run_id = $manifest['run_id'];

    hfur_require(
        hfur_probe($run_id)['user_ids'] === [],
        'Residual users of completed fixture run.'
    );

    $post_ids = [];

    foreach ($manifest['objects'] as $record) {
        hfur_require(
            $record['id'] > 0,
            'Pending intent in cleaned manifest.'
        );

        if ($record['kind'] === 'user') {
            hfur_assert_user_absent(
                $record,
                $run_id
            );

            $user_ids[] = $record['id'];
        }

        if ($record['kind'] === 'post') {
            $post_ids[] = $record['id'];
        }
    }

    hfur_audit_user_ids($user_ids);

    $legacy = hf09_audit($post_ids);

    $checks = $legacy['checks'];

    $checks['fixture_users'] = hfur_count(
        "SELECT COUNT(*) AS n
         FROM {$wpdb->users}
         WHERE user_login LIKE 'am-http-%'
            OR user_login LIKE 'hfur-duplicate-%'
            OR user_nicename LIKE 'am-user-%'
            OR user_nicename LIKE 'hfur-duplicate-%'
            OR user_email LIKE 'am-http-%@example.invalid'"
    );

    $checks['orphan_post_authors'] = hfur_count(
        "SELECT COUNT(*) AS n
         FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->users} u
             ON u.ID = p.post_author
         WHERE p.post_author > 0
           AND u.ID IS NULL"
    );

    $checks['orphan_comment_users'] = hfur_count(
        "SELECT COUNT(*) AS n
         FROM {$wpdb->comments} c
         LEFT JOIN {$wpdb->users} u
             ON u.ID = c.user_id
         WHERE c.user_id > 0
           AND u.ID IS NULL"
    );

    $checks['orphan_link_owners'] = hfur_count(
        "SELECT COUNT(*) AS n
         FROM {$wpdb->links} l
         LEFT JOIN {$wpdb->users} u
             ON u.ID = l.link_owner
         WHERE l.link_owner > 0
           AND u.ID IS NULL"
    );

    foreach ($checks as $name => $count) {
        hfur_require(
            $count === 0,
            "Independent audit failed: {$name}={$count}."
        );
    }

    $result = [
        'checks' => $checks,
        'baseline' => hfur_audit_baseline(),
        'protected_user' => hfur_audit_protected_user(),
    ];

    hfur_require(
        hfur_manifest() === $manifest,
        'Manifest changed during independent audit.'
    );

    hfur_guard();

    return $result;
}