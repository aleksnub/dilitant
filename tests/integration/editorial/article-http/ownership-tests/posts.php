<?php

declare(strict_types=1);

function ownership_test_post_mutation(
    DilitantArticleFixtures $fixtures,
    DilitantOwnershipTestJournal $journal,
    string $field,
    string $replacement,
    string $expected
): void {
    $id = $fixtures->future_post;
    $record = $journal->record('post', $id);

    $post = ownership_post($id);
    $original = $post->{$field};

    $schedule = wp_next_scheduled(
        'publish_future_post',
        [$id]
    );

    $categories = ownership_post_categories($id);

    dilitant_test_assert(
        is_int($schedule) && $schedule > 0,
        'Expected scheduled publication event.'
    );

    try {
        ownership_update_post($id, [$field => $replacement]);

        dilitant_test_assert_same(
            $replacement,
            ownership_post($id)->{$field},
            'Test mutation was not applied.'
        );

        ownership_expect_rejection(
            static fn() => ownership_cleanup_record($fixtures, $record),
            $expected
        );

        $remaining = ownership_post($id);

        dilitant_test_assert_same(
            $replacement,
            $remaining->{$field},
            'Rejected operation modified the post.'
        );

        dilitant_test_assert_same(
            'future',
            $remaining->post_status,
            'Post status changed.'
        );

        dilitant_test_assert_same(
            $schedule,
            wp_next_scheduled('publish_future_post', [$id]),
            'Rejected operation removed the scheduled event.'
        );

        dilitant_test_assert_same(
            $categories,
            ownership_post_categories($id),
            'Rejected operation modified category relationships.'
        );

    } finally {
        ownership_update_post($id, [$field => $original]);
    }
}

function ownership_test_title(
    DilitantArticleFixtures $fixtures,
    DilitantOwnershipTestJournal $journal
): void {
    ownership_test_post_mutation(
        $fixtures,
        $journal,
        'post_title',
        'HF-M08 Changed Title ' . $fixtures->run_id(),
        'Post ownership mismatch.'
    );
}

function ownership_test_content_marker(
    DilitantArticleFixtures $fixtures,
    DilitantOwnershipTestJournal $journal
): void {
    ownership_test_post_mutation(
        $fixtures,
        $journal,
        'post_content',
        'HF-M08 Content without ownership marker.',
        'Post content marker mismatch.'
    );
}