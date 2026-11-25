<?php

declare(strict_types=1);

function hf09_arm_cleanup_crash(): void
{
    $manifest = DilitantHttpFixtureManifest::load();

    hf09_require(
        $manifest->state() === 'READY',
        'Cleanup crash requires READY manifest.'
    );

    $data = $manifest->data();

    $fixtures = DilitantArticleFixtures::restore_ready($data);

    $target = $fixtures->password_post;

    hf09_require(
        $target > 0,
        'Invalid cleanup interruption target.'
    );

    add_action(
        'after_delete_post',
        static function (int $id) use ($target): void {
            global $wpdb;

            if ($id !== $target) {
                return;
            }

            $manifest = hf09_manifest();

            hf09_require(
                $manifest['state'] === 'CLEANING',
                'Expected persistent CLEANING state.'
            );

            $remaining = hf09_count(
                $wpdb->prepare(
                    "SELECT COUNT(*) AS n
                     FROM {$wpdb->posts}
                     WHERE ID = %d",
                    $id
                )
            );

            hf09_require(
                $remaining === 0,
                'Interrupted cleanup target still exists.'
            );

            hf09_checkpoint('cleanup', [
                'deleted_id' => $id,
            ]);
        },
        PHP_INT_MAX,
        1
    );
}