<?php

declare(strict_types=1);

final class DilitantUserRecoveryCrashJournal
    implements DilitantFixtureJournal
{
    public function __construct(
        private DilitantHttpFixtureManifest $manifest,
        private string $run_id,
        private string $stage
    ) {
        hfur_run_id($this->run_id);
    }

    public function intent(
        string $kind,
        array $identity
    ): void {
        hfur_guard();

        hfur_require(
            $kind === 'user',
            'Unexpected creation journal object.'
        );

        hfur_validate_identity(
            $identity,
            $this->run_id
        );

        $this->manifest->intent(
            $kind,
            $identity
        );

        $record = hfur_pending_record(
            $this->run_id
        );

        hfur_require(
            $record['identity'] === $identity,
            'Persistent user intent identity mismatch.'
        );

        hfur_require(
            hfur_identity_candidates(
                $record,
                $this->run_id
            ) === [],
            'User exists before creation.'
        );

        if ($this->stage === 'user-intent') {
            hfur_checkpoint(
                $this->stage,
                [
                    'identity' => $identity,
                ]
            );
        }
    }

    public function confirm(
        int $id,
        int $taxonomy_id = 0
    ): void {
        hfur_guard();

        hfur_require(
            $id > 0 && $taxonomy_id === 0,
            'Invalid user confirmation identifiers.'
        );

        $record = hfur_pending_record(
            $this->run_id
        );

        hfur_require(
            hfur_require_owned_user(
                $record,
                $this->run_id
            ) === $id,
            'Unconfirmed user identity mismatch.'
        );

        $dependencies = hfur_user_dependencies($id);

        hfur_require(
            $dependencies['usermeta'] > 0,
            'Expected fully created user before Confirm.'
        );

        if (in_array(
            $this->stage,
            [
                'user-unconfirmed',
                'user-sql-intent',
                'user-container',
            ],
            true
        )) {
            hfur_checkpoint(
                $this->stage,
                [
                    'id' => $id,
                    'identity' => $record['identity'],
                    'dependencies' => $dependencies,
                ]
            );
        }

        if ($this->stage === 'user-ambiguous') {
            $duplicate_id = hfur_create_ambiguous_user(
                $record,
                $this->run_id,
                $id
            );

            hfur_checkpoint(
                $this->stage,
                [
                    'id' => $id,
                    'duplicate_id' => $duplicate_id,
                    'identity' => $record['identity'],
                ]
            );
        }

        $this->manifest->confirm(
            $id,
            $taxonomy_id
        );

        if ($this->stage === 'user-substituted') {
            $substituted_id = hfur_substitute_user_id(
                $this->run_id,
                $id
            );

            hfur_checkpoint(
                $this->stage,
                [
                    'id' => $id,
                    'substituted_id' => $substituted_id,
                    'identity' => $record['identity'],
                ]
            );
        }
    }
}