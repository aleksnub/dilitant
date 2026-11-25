<?php

declare(strict_types=1);

enum DilitantRecoveryOutcome: string
{
    case ABSENT = 'ABSENT';
    case RESOLVED = 'RESOLVED';
    case BLOCKED = 'BLOCKED';
}

function dilitant_recovery_result(
    DilitantRecoveryOutcome $outcome,
    int $id = 0,
    int $taxonomy_id = 0,
    string $reason = ''
): array {
    return [
        'outcome' => $outcome,
        'id' => $id,
        'taxonomy_id' => $taxonomy_id,
        'reason' => $reason,
    ];
}

function dilitant_recover_pending_object(
    array $object,
    string $run_id
): array {
    dilitant_http_require_bootstrap();
    dilitant_fixture_assert_lock();

    if (
        preg_match('/\A[0-9a-f]{12}\z/', $run_id) !== 1
        || ($object['id'] ?? null) !== 0
        || ($object['taxonomy_id'] ?? null) !== 0
        || !is_array($object['identity'] ?? null)
    ) {
        throw new RuntimeException('Invalid recovery intent.');
    }

    $identity = $object['identity'];

    $result = match ($object['kind'] ?? null) {
        'post' => dilitant_recover_post(
            $identity,
            $run_id
        ),

        'category' => dilitant_recover_category(
            $identity
        ),

        'user' => dilitant_recover_user(
            $identity,
            $run_id
        ),

        default => throw new RuntimeException(
            'Unsupported recovery object.'
        ),
    };

    dilitant_fixture_assert_lock();

    return $result;
}

trait DilitantManifestPendingRecovery
{
    public function recover_pending(): void
    {
        $this->require_state('CLEANING');

        $index = $this->pending_index();

        if ($index === null) {
            return;
        }

        $object = $this->data['objects'][$index];

        $result = dilitant_recover_pending_object(
            $object,
            $this->data['run_id']
        );

        $next = $this->data;

        switch ($result['outcome']) {
            case DilitantRecoveryOutcome::ABSENT:
                if ($object['kind'] === 'user') {
                    global $wpdb;

                    $orphans = DilitantFixtureUserResolution::count(
                        "SELECT COUNT(*) AS n
                         FROM {$wpdb->usermeta} m
                         LEFT JOIN {$wpdb->users} u
                             ON u.ID = m.user_id
                         WHERE u.ID IS NULL"
                    );

                    if ($orphans !== 0) {
                        throw new RuntimeException(
                            'Pending user recovery blocked: '
                            . 'orphan user metadata detected.'
                        );
                    }
                }

                array_splice($next['objects'], $index, 1);
                break;

            case DilitantRecoveryOutcome::RESOLVED:
                $id = $result['id'];
                $taxonomy_id = $result['taxonomy_id'];

                if ($id <= 0 || $taxonomy_id < 0) {
                    throw new RuntimeException(
                        'Invalid recovered identifiers.'
                    );
                }

                $object['id'] = $id;
                $object['taxonomy_id'] = $taxonomy_id;

                self::validate_object(
                    $object,
                    $next['run_id'],
                    $next['schema_version']
                );

                $next['objects'][$index] = $object;
                break;

            case DilitantRecoveryOutcome::BLOCKED:
                throw new RuntimeException(
                    'Pending fixture recovery blocked: '
                    . $result['reason']
                );
        }

        $this->commit($next);
    }
}