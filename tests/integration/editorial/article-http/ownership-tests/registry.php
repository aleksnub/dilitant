<?php

declare(strict_types=1);

function ownership_test_registry_substitution(
    DilitantArticleFixtures $fixtures,
    DilitantOwnershipTestJournal $journal
): void {
    $original_id = $fixtures->root_post;
    $original = ownership_post($original_id);

    $record = $journal->record('post', $original_id);

    $title = $original->post_title;
    $category = $fixtures->outside_category;

    $foreign_id = ownership_create_foreign_post(
        $title,
        (int) $original->post_author,
        $category
    );

    try {
        dilitant_test_assert(
            $foreign_id !== $original_id,
            'Foreign post ID must differ.'
        );

        $foreign = ownership_post($foreign_id);

        dilitant_test_assert_same(
            $original->post_title,
            $foreign->post_title,
            'Expected identical title.'
        );

        dilitant_test_assert_same(
            $original->post_type,
            $foreign->post_type,
            'Expected identical post type.'
        );

        dilitant_test_assert_same(
            (int) $original->post_author,
            (int) $foreign->post_author,
            'Expected identical author.'
        );

        $record['id'] = $foreign_id;

        ownership_expect_rejection(
            static fn() => ownership_cleanup_record($fixtures, $record),
            'Post content marker mismatch.'
        );

        dilitant_test_assert_same(
            $title,
            ownership_post($foreign_id)->post_title,
            'Substituted post modified.'
        );

        dilitant_test_assert_same(
            $original->post_content,
            ownership_post($original_id)->post_content,
            'Original fixture modified.'
        );

        dilitant_test_assert_same(
            [$category],
            ownership_post_categories($foreign_id),
            'Substituted post relationships modified.'
        );

    } finally {
        ownership_delete_foreign_post($foreign_id, $title);
    }
}