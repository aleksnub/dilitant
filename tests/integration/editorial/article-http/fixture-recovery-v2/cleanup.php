<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';

function hfr2_arm_cleanup_crash(): void
{
    hfur_guard();

    $manifest = hfur_manifest();

    hfur_require(
        $manifest['state'] === 'READY'
        && count($manifest['objects']) === 18
        && is_array($manifest['snapshot']),
        'Cleanup crash requires READY Manifest V2.'
    );

    $fixtures = DilitantArticleFixtures::restore_ready(
        $manifest
    );

    $target = $fixtures->password_post;

    hfur_require(
        $target > 0
        && $target ===
            $manifest['snapshot']['fields']['password_post'],
        'Invalid cleanup interruption target.'
    );

    $expected = $manifest;
    $expected['state'] = 'CLEANING';

    add_action(
        'after_delete_post',
        static function (int $id) use (
            $target,
            $expected
        ): void {
            global $wpdb;

            if ($id !== $target) {
                return;
            }

            hfur_guard();

            hfur_require(
                hfur_manifest() === $expected,
                'Unexpected persistent CLEANING manifest.'
            );

            hfur_require(
                hfur_count(
                    $wpdb->prepare(
                        "SELECT COUNT(*) AS n
                         FROM {$wpdb->posts}
                         WHERE ID = %d",
                        $id
                    )
                ) === 0,
                'Interrupted cleanup target still exists.'
            );

            $probe = hfr2_probe($expected['run_id']);

            $users = [];
            $posts = [];
            $terms = [];
            $taxonomy = [];

            foreach ($expected['objects'] as $record) {
                switch ($record['kind']) {
                    case 'user':
                        $users[] = $record['id'];
                        break;

                    case 'category':
                        $terms[] = $record['id'];
                        $taxonomy[] = $record['taxonomy_id'];
                        break;

                    case 'post':
                        if ($record['id'] !== $target) {
                            $posts[] = $record['id'];
                        }
                        break;
                }
            }

            sort($users, SORT_NUMERIC);
            sort($posts, SORT_NUMERIC);
            sort($terms, SORT_NUMERIC);
            sort($taxonomy, SORT_NUMERIC);

            hfur_require(
                $probe['user_ids'] === $users
                && $probe['post_ids'] === $posts
                && $probe['term_ids'] === $terms
                && $probe['taxonomy_ids'] === $taxonomy
                && $probe['partial_term_ids'] === [],
                'Unexpected interrupted cleanup state.'
            );

            hfr2_checkpoint('fixture-cleanup-crash', [
                'deleted_id' => $id,
            ]);
        },
        PHP_INT_MAX,
        1
    );

    hfur_guard();
}