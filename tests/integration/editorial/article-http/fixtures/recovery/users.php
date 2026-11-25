<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/fixtures/users.php';

function dilitant_recover_user(
    array $identity,
    string $run_id
): array {
    DilitantFixtureUserResolution::guard();

    $record = [
        'kind' => 'user',
        'id' => 0,
        'taxonomy_id' => 0,
        'identity' => $identity,
    ];

    $result = DilitantFixtureUserResolution::resolve(
        $record,
        $run_id
    );

    return match ($result['status']) {
        DilitantUserResolutionStatus::ABSENT =>
            dilitant_recovery_result(
                DilitantRecoveryOutcome::ABSENT
            ),

        DilitantUserResolutionStatus::RESOLVED =>
            dilitant_recovery_result(
                DilitantRecoveryOutcome::RESOLVED,
                $result['id'],
                0
            ),

        DilitantUserResolutionStatus::BLOCKED =>
            dilitant_recovery_result(
                DilitantRecoveryOutcome::BLOCKED,
                reason: $result['reason']
            ),
    };
}

function dilitant_verify_confirmed_user(
    array $record,
    string $run_id
): void {
    DilitantFixtureUserIdentity::validate_record(
        $record,
        $run_id
    );

    if ($record['id'] <= 0) {
        throw new RuntimeException(
            'Confirmed user ID required.'
        );
    }

    DilitantFixtureUserPermissions::verify(
        $record,
        $run_id
    );
}

function dilitant_verify_cleaned_user(
    array $record,
    string $run_id
): void {
    DilitantFixtureUserDependencies::require_absent(
        $record,
        $run_id
    );
}