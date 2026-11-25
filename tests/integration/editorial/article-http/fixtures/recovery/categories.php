<?php

declare(strict_types=1);

function dilitant_recover_category(array $identity): array
{
    global $wpdb;

    dilitant_http_require_bootstrap();
    dilitant_fixture_assert_lock();

    $name = $identity['name'] ?? null;
    $slug = $identity['slug'] ?? null;
    $parent = $identity['parent'] ?? null;

    if (
        !is_string($name)
        || $name === ''
        || !is_string($slug)
        || $slug === ''
        || !is_int($parent)
        || $parent < 0
    ) {
        throw new RuntimeException(
            'Invalid category recovery identity.'
        );
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT t.term_id, t.name, t.slug,
                    tt.term_taxonomy_id,
                    tt.taxonomy, tt.parent
             FROM {$wpdb->terms} t
             LEFT JOIN {$wpdb->term_taxonomy} tt
                 ON tt.term_id = t.term_id
             WHERE BINARY t.name = BINARY %s
                OR BINARY t.slug = BINARY %s",
            $name,
            $slug
        ),
        ARRAY_A
    );

    if ($wpdb->last_error !== '' || !is_array($rows)) {
        throw new RuntimeException('Category recovery SQL failed.');
    }

    dilitant_fixture_assert_lock();

    if ($rows === []) {
        $orphans = $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$wpdb->term_taxonomy} tt
             LEFT JOIN {$wpdb->terms} t
                 ON t.term_id = tt.term_id
             WHERE t.term_id IS NULL
               AND tt.taxonomy = 'category'"
        );

        if (
            $wpdb->last_error !== ''
            || $orphans === null
            || !ctype_digit((string) $orphans)
        ) {
            throw new RuntimeException(
                'Category integrity check failed.'
            );
        }

        dilitant_fixture_assert_lock();

        if ((int) $orphans !== 0) {
            return dilitant_recovery_result(
                DilitantRecoveryOutcome::BLOCKED,
                reason: 'Unresolved orphan category taxonomy.'
            );
        }

        return dilitant_recovery_result(
            DilitantRecoveryOutcome::ABSENT
        );
    }

    if (count($rows) !== 1) {
        return dilitant_recovery_result(
            DilitantRecoveryOutcome::BLOCKED,
            reason: 'Ambiguous category identity.'
        );
    }

    $row = $rows[0];

    $id = (int) $row['term_id'];
    $taxonomy_id = (int) $row['term_taxonomy_id'];

    if (
        $id <= 0
        || $taxonomy_id <= 0
        || $row['taxonomy'] !== 'category'
        || $row['name'] !== $name
        || $row['slug'] !== $slug
        || $row['parent'] === null
        || (int) $row['parent'] !== $parent
    ) {
        return dilitant_recovery_result(
            DilitantRecoveryOutcome::BLOCKED,
            reason: 'Incomplete or mismatched category identity.'
        );
    }

    return dilitant_recovery_result(
        DilitantRecoveryOutcome::RESOLVED,
        $id,
        $taxonomy_id
    );
}