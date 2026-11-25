<?php

declare(strict_types=1);

trait DilitantManifestLifecycle
{
    private const TRANSITIONS = [
        'CREATING' => ['FAILED', 'CLEANING'],
        'READY' => ['TESTING', 'CLEANING'],
        'TESTING' => ['CLEANING'],
        'FAILED' => ['CLEANING'],
        'CLEANING' => ['CLEANED', 'CLEANUP_FAILED'],
        'CLEANUP_FAILED' => ['CLEANING'],
        'CLEANED' => [],
    ];

    public function intent(string $kind, array $identity): void
    {
        $this->require_state('CREATING');

        if ($this->pending_index() !== null) {
            throw new RuntimeException('Unresolved creation intent.');
        }

        $next = $this->data;

        $object = [
            'kind' => $kind,
            'id' => 0,
            'taxonomy_id' => 0,
            'identity' => $identity,
        ];

        self::validate_object(
            $object,
            $next['run_id'],
            $next['schema_version']
        );

        $result = dilitant_recover_pending_object(
            $object,
            $next['run_id']
        );

        if ($result['outcome'] !== DilitantRecoveryOutcome::ABSENT) {
            throw new RuntimeException(
                'Fixture identity already exists.'
            );
        }

        $next['objects'][] = $object;

        $this->commit($next);
    }

    public function confirm(
        int $id,
        int $taxonomy_id = 0
    ): void {
        $this->require_state('CREATING');

        $index = $this->pending_index();

        if ($index === null || $id <= 0) {
            throw new RuntimeException('Invalid fixture confirmation.');
        }

        $next = $this->data;
        $object = $next['objects'][$index];

        $result = dilitant_recover_pending_object(
            $object,
            $next['run_id']
        );

        if (
            $result['outcome'] !== DilitantRecoveryOutcome::RESOLVED
            || $result['id'] !== $id
            || $result['taxonomy_id'] !== $taxonomy_id
        ) {
            throw new RuntimeException(
                'Fixture confirmation identity mismatch.'
            );
        }

        $object['id'] = $id;
        $object['taxonomy_id'] = $taxonomy_id;

        self::validate_object(
            $object,
            $next['run_id'],
            $next['schema_version']
        );

        if ($object['kind'] === 'user') {
            dilitant_verify_confirmed_user(
                $object,
                $next['run_id']
            );
        }

        $next['objects'][$index] = $object;

        $this->commit($next);
    }

    public function ready(array $snapshot): void
    {
        $this->require_state('CREATING');

        if ($this->pending_index() !== null) {
            throw new RuntimeException('Unresolved fixture object.');
        }

        $next = $this->data;
        $next['snapshot'] = $snapshot;
        $next['state'] = 'READY';

        self::validate($next);

        DilitantArticleFixtures::restore_ready($next);

        $this->commit($next);
    }

    public function transition(string $state): void
    {
        self::assert_environment();

        $current = $this->state();

        if (!in_array($state, self::TRANSITIONS[$current], true)) {
            throw new RuntimeException(
                "Invalid manifest transition: {$current} -> {$state}"
            );
        }

        if ($state === 'CLEANED') {
            $this->verify_cleaned_registry();
        }

        $next = $this->data;
        $next['state'] = $state;

        $this->commit($next);
    }

    private function require_state(string $expected): void
    {
        self::assert_environment();

        if ($this->state() !== $expected) {
            throw new RuntimeException(
                "Expected manifest state: {$expected}"
            );
        }
    }

    private function pending_index(): ?int
    {
        foreach ($this->data['objects'] as $index => $object) {
            if ($object['id'] === 0) {
                return $index;
            }
        }

        return null;
    }

    private function verify_cleaned_registry(): void
    {
        global $wpdb;

        $this->require_state('CLEANING');

        if ($this->pending_index() !== null) {
            throw new RuntimeException('Unresolved fixture intent.');
        }

        foreach ($this->data['objects'] as $object) {
            $id = $object['id'];

            if ($object['kind'] === 'user') {
                dilitant_verify_cleaned_user(
                    $object,
                    $this->data['run_id']
                );

                continue;
            }

            if ($object['kind'] === 'post') {
                $queries = [
                    $wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$wpdb->posts}
                         WHERE ID = %d OR post_parent = %d",
                        $id,
                        $id
                    ),

                    $wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$wpdb->postmeta}
                         WHERE post_id = %d",
                        $id
                    ),

                    $wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$wpdb->term_relationships}
                         WHERE object_id = %d",
                        $id
                    ),

                    $wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$wpdb->comments}
                         WHERE comment_post_ID = %d",
                        $id
                    ),
                ];

                if (
                    wp_next_scheduled(
                        'publish_future_post',
                        [$id]
                    ) !== false
                ) {
                    throw new RuntimeException(
                        "Residual publication event: {$id}"
                    );
                }
            } else {
                $taxonomy_id = $object['taxonomy_id'];

                $queries = [
                    $wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$wpdb->terms}
                         WHERE term_id = %d",
                        $id
                    ),

                    $wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$wpdb->term_taxonomy}
                         WHERE term_id = %d
                            OR term_taxonomy_id = %d
                            OR (taxonomy = 'category' AND parent = %d)",
                        $id,
                        $taxonomy_id,
                        $id
                    ),

                    $wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$wpdb->termmeta}
                         WHERE term_id = %d",
                        $id
                    ),

                    $wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$wpdb->term_relationships}
                         WHERE term_taxonomy_id = %d",
                        $taxonomy_id
                    ),
                ];
            }

            foreach ($queries as $sql) {
                self::assert_environment();

                $count = $wpdb->get_var($sql);

                if (
                    $wpdb->last_error !== ''
                    || $count === null
                    || !ctype_digit((string) $count)
                ) {
                    throw new RuntimeException(
                        "Cleanup verification failed: {$id}"
                    );
                }

                self::assert_environment();

                if ((int) $count !== 0) {
                    throw new RuntimeException(
                        "Residual fixture object: {$id}"
                    );
                }
            }

            $candidate = $object;
            $candidate['id'] = 0;
            $candidate['taxonomy_id'] = 0;

            $result = dilitant_recover_pending_object(
                $candidate,
                $this->data['run_id']
            );

            if ($result['outcome'] !== DilitantRecoveryOutcome::ABSENT) {
                throw new RuntimeException(
                    "Residual fixture identity: {$id}"
                );
            }
        }
    }
}