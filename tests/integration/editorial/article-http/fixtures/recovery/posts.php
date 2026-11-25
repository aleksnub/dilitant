<?php

declare(strict_types=1);

function dilitant_recover_post(
    array $identity,
    string $run_id
): array {
    global $wpdb;

    dilitant_http_require_bootstrap();
    dilitant_fixture_assert_lock();

    if (
        preg_match('/\A[0-9a-f]{12}\z/', $run_id) !== 1
        || !is_string($identity['title'] ?? null)
        || !str_ends_with($identity['title'], ' ' . $run_id)
        || !in_array(
            $identity['type'] ?? null,
            ['post', 'page', 'archive_photo'],
            true
        )
        || !is_int($identity['author_id'] ?? null)
        || $identity['author_id'] <= 0
        || !is_string($identity['content_marker'] ?? null)
        || preg_match(
            '/\AAM-CONTENT-' . $run_id . '-[0-9a-f]{16}\z/',
            $identity['content_marker']
        ) !== 1
    ) {
        throw new RuntimeException(
            'Invalid post recovery identity.'
        );
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT ID, post_title, post_type,
                    post_author, post_content, post_parent
             FROM {$wpdb->posts}
             WHERE BINARY post_title = BINARY %s
                OR LOCATE(BINARY %s, BINARY post_content) > 0
             ORDER BY ID",
            $identity['title'],
            $identity['content_marker']
        ),
        ARRAY_A
    );

    if ($wpdb->last_error !== '' || !is_array($rows)) {
        throw new RuntimeException(
            'Post recovery SQL failed.'
        );
    }

    dilitant_fixture_assert_lock();

    if ($rows === []) {
        return dilitant_recovery_result(
            DilitantRecoveryOutcome::ABSENT
        );
    }

    $candidates = [];
    $revisions = [];

    foreach ($rows as $row) {
        if ($row['post_type'] === 'revision') {
            $revisions[] = $row;
        } else {
            $candidates[] = $row;
        }
    }

    if (count($candidates) !== 1) {
        return dilitant_recovery_result(
            DilitantRecoveryOutcome::BLOCKED,
            reason: 'Ambiguous or incomplete post identity.'
        );
    }

    $post = $candidates[0];
    $id = (int) $post['ID'];

    if (
        $id <= 0
        || $post['post_title'] !== $identity['title']
        || $post['post_type'] !== $identity['type']
        || (int) $post['post_author'] !== $identity['author_id']
        || !str_contains(
            $post['post_content'],
            $identity['content_marker']
        )
    ) {
        return dilitant_recovery_result(
            DilitantRecoveryOutcome::BLOCKED,
            reason: 'Post identity mismatch.'
        );
    }

    foreach ($revisions as $revision) {
        if (
            (int) $revision['ID'] <= 0
            || (int) $revision['post_parent'] !== $id
        ) {
            return dilitant_recovery_result(
                DilitantRecoveryOutcome::BLOCKED,
                reason: 'Unexpected dependent revision.'
            );
        }
    }

    dilitant_fixture_assert_lock();

    return dilitant_recovery_result(
        DilitantRecoveryOutcome::RESOLVED,
        $id,
        0
    );
}