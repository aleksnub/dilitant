<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once __DIR__ . '/ambiguity.php';

final class DilitantFixtureRecoveryV2Journal
    implements DilitantFixtureJournal
{
    public function __construct(
        private DilitantHttpFixtureManifest $manifest,
        private string $stage
    ) {
    }

    public function intent(
        string $kind,
        array $identity
    ): void {
        hfur_guard();

        $this->manifest->intent($kind, $identity);

        if ($kind === 'user') {
            return;
        }

        $run_id = $this->manifest->data()['run_id'];

        if (
            $kind === 'category'
            && in_array(
                $this->stage,
                [
                    'fixture-intent',
                    'fixture-partial-category',
                ],
                true
            )
        ) {
            hfr2_pending($run_id, 'category');

            if ($this->stage === 'fixture-intent') {
                hfr2_checkpoint($this->stage);
            }

            if ($this->stage === 'fixture-partial-category') {
                $id = hfr2_create_partial_category($identity);

                hfr2_checkpoint($this->stage, [
                    'term_id' => $id,
                ]);
            }
        }

        if ($kind === 'post') {
            hfr2_pending($run_id, 'post');

            if ($this->stage === 'fixture-sql-intent') {
                hfr2_checkpoint($this->stage);
            }
        }
    }

    public function confirm(
        int $id,
        int $taxonomy_id = 0
    ): void {
        hfur_guard();

        $data = $this->manifest->data();
        $objects = $data['objects'];

        hfur_require(
            $objects !== [],
            'Missing fixture intent.'
        );

        $record = $objects[array_key_last($objects)];

        hfur_require(
            $record['id'] === 0 && $id > 0,
            'Invalid fixture confirmation.'
        );

        $kind = $record['kind'];

        if ($kind === 'user') {
            hfur_require(
                $taxonomy_id === 0,
                'Unexpected user taxonomy ID.'
            );

            $this->manifest->confirm($id, $taxonomy_id);

            return;
        }

        $extra = [
            'id' => $id,
            'taxonomy_id' => $taxonomy_id,
        ];

        if ($kind === 'category') {
            hfur_require(
                $taxonomy_id > 0,
                'Missing category taxonomy ID.'
            );

            if ($this->stage === 'fixture-category') {
                hfr2_checkpoint($this->stage, $extra);
            }
        }

        if ($kind === 'post') {
            hfur_require(
                $taxonomy_id === 0,
                'Unexpected post taxonomy ID.'
            );

            hf09_verify_post_identity(
                hf09_post_row($id),
                $record['identity']
            );

            if (in_array(
                $this->stage,
                ['fixture-post', 'fixture-container'],
                true
            )) {
                hfr2_checkpoint($this->stage, $extra);
            }

            if ($this->stage === 'fixture-ambiguous') {
                $extra['duplicate_id'] =
                    hfr2_create_duplicate_post(
                        $id,
                        $record['identity']
                    );

                hfr2_checkpoint($this->stage, $extra);
            }
        }

        $this->manifest->confirm($id, $taxonomy_id);

        if (
            $kind === 'post'
            && $this->stage === 'fixture-confirmed'
        ) {
            hfr2_checkpoint($this->stage, $extra);
        }
    }
}

function hfr2_crash_creation(string $stage): never
{
    hfur_guard();

    hfur_require(
        in_array($stage, [
            'fixture-empty',
            'fixture-intent',
            'fixture-category',
            'fixture-post',
            'fixture-confirmed',
            'fixture-ambiguous',
            'fixture-container',
            'fixture-sql-intent',
            'fixture-partial-category',
        ], true),
        'Unsupported fixture creation stage.'
    );

    hfr2_assert_clean_start();

    $fixtures = new DilitantArticleFixtures();

    $manifest = DilitantHttpFixtureManifest::begin(
        $fixtures->run_id(),
        2
    );

    if ($stage === 'fixture-empty') {
        hfr2_checkpoint($stage);
    }

    $journal = new DilitantFixtureRecoveryV2Journal(
        $manifest,
        $stage
    );

    $fixtures->attach_journal($journal);

    $fixtures->create_users();

    hfr2_verify_users($manifest->data());

    $fixtures->create();
    $fixtures->create_query_fixtures();

    throw new RuntimeException(
        "Expected crash checkpoint was not reached: {$stage}"
    );
}