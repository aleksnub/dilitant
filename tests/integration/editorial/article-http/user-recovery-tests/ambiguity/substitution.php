<?php

declare(strict_types=1);

function hfur_repair_substitution(
    string $run_id,
    int $id,
    int $substituted_id
): void {
    hfur_guard();

    hfur_repair_require_ids(
        $id,
        $substituted_id
    );

    $manifest = hfur_repair_manifest($run_id);
    $record = $manifest['objects'][0];

    $protected_id = hfur_protected_id();

    hfur_require(
        $substituted_id === $protected_id
        && $record['id'] === $substituted_id,
        'Unexpected substituted manifest identifier.'
    );

    $restored_record = $record;
    $restored_record['id'] = $id;

    hfur_validate_user_record(
        $restored_record,
        $run_id
    );

    $original = hfur_user_row($id);

    hfur_require(
        hfur_verify_user_identity(
            $original,
            $restored_record,
            $run_id
        ) === $id,
        'Original substitution user mismatch.'
    );

    hfur_require(
        hfur_repair_candidate_ids(
            $restored_record,
            $run_id
        ) === [$id],
        'Original substitution identity is ambiguous.'
    );

    hfur_repair_require_no_dependencies(
        $id,
        true
    );

    $protected_digest = hfur_repair_user_digest(
        $protected_id
    );

    $original_digest = hfur_repair_user_digest($id);

    $next = $manifest;
    $next['objects'][0] = $restored_record;

    hfur_repair_commit(
        $manifest,
        $next
    );

    hfur_require(
        hfur_require_owned_user(
            $restored_record,
            $run_id
        ) === $id,
        'Restored user identifier cannot be resolved.'
    );

    hfur_require(
        hfur_repair_user_digest($id)
            === $original_digest,
        'Original user changed during substitution repair.'
    );

    hfur_require(
        hfur_repair_user_digest($protected_id)
            === $protected_digest,
        'Protected user changed during substitution repair.'
    );

    hfur_require(
        hfur_manifest() === $next,
        'Restored manifest changed unexpectedly.'
    );

    hfur_guard();
}