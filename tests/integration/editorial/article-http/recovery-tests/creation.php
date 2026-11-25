<?php

declare(strict_types=1);

final class DilitantRecoveryCrashJournal implements DilitantFixtureJournal
{
    public function __construct(
        private DilitantHttpFixtureManifest $manifest,
        private string $stage
    ) {
    }

    public function intent(string $kind, array $identity): void
    {
        $this->manifest->intent($kind, $identity);

        if ($this->stage === 'intent' && $kind === 'category') {
            hf09_checkpoint('intent');
        }

        if ($this->stage === 'sql-intent' && $kind === 'post') {
            hf09_checkpoint('sql-intent');
        }

        if (
            $this->stage === 'partial-category'
            && $kind === 'category'
        ) {
            $id = hf09_create_partial_category($identity);

            hf09_checkpoint('partial-category', [
                'term_id' => $id,
            ]);
        }
    }

    public function confirm(
        int $id,
        int $taxonomy_id = 0
    ): void {
        $data = $this->manifest->data();
        $object = $data['objects'][array_key_last($data['objects'])];

        hf09_require(
            $object['id'] === 0,
            'Expected pending object before confirmation.'
        );

        $kind = $object['kind'];

        if (
            $this->stage === 'category'
            && $kind === 'category'
        ) {
            hf09_checkpoint('category', [
                'id' => $id,
                'taxonomy_id' => $taxonomy_id,
            ]);
        }

        if ($kind === 'post') {
            if (
                in_array(
                    $this->stage,
                    ['post', 'container'],
                    true
                )
            ) {
                hf09_checkpoint($this->stage, [
                    'id' => $id,
                    'taxonomy_id' => 0,
                ]);
            }

            if ($this->stage === 'ambiguous') {
                $duplicate = hf09_create_duplicate_post(
                    $id,
                    $object['identity']
                );

                hf09_checkpoint('ambiguous', [
                    'id' => $id,
                    'duplicate_id' => $duplicate,
                    'taxonomy_id' => 0,
                ]);
            }
        }

        $this->manifest->confirm($id, $taxonomy_id);

        if ($this->stage === 'confirmed' && $kind === 'post') {
            hf09_checkpoint('confirmed', [
                'id' => $id,
                'taxonomy_id' => 0,
            ]);
        }
    }
}

function hf09_crash_creation(string $stage): never
{
    hf09_assert_clean_start();

    $fixtures = new DilitantArticleFixtures();

    $manifest = DilitantHttpFixtureManifest::begin(
        $fixtures->run_id()
    );

    if ($stage === 'empty') {
        hf09_checkpoint('empty');
    }

    $journal = new DilitantRecoveryCrashJournal(
        $manifest,
        $stage
    );

    $fixtures->attach_journal($journal);

    $fixtures->create();
    $fixtures->create_query_fixtures();

    throw new RuntimeException(
        "Expected crash checkpoint was not reached: {$stage}"
    );
}