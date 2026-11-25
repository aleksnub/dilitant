<?php

declare(strict_types=1);

function ownership_test_category_identity(
    DilitantArticleFixtures $fixtures,
    DilitantOwnershipTestJournal $journal
): void {
    $id = $fixtures->root_category;
    $record = $journal->record('category', $id);
    $original = ownership_category($id);

    $replacement = 'HF-M08 Changed Root ' . $fixtures->run_id();

    try {
        ownership_update_category_name($id, $replacement);

        dilitant_test_assert_same(
            $replacement,
            ownership_category($id)->name,
            'Category mutation was not applied.'
        );

        ownership_expect_rejection(
            static fn() => ownership_cleanup_record($fixtures, $record),
            'Category ownership mismatch.'
        );

        $remaining = ownership_category($id);

        dilitant_test_assert_same(
            $replacement,
            $remaining->name,
            'Rejected operation modified category.'
        );

        dilitant_test_assert_same(
            $original->slug,
            $remaining->slug,
            'Category slug changed.'
        );

        dilitant_test_assert_same(
            (int) $original->term_taxonomy_id,
            (int) $remaining->term_taxonomy_id,
            'Taxonomy identity changed.'
        );

        dilitant_test_assert_same(
            $id,
            (int) ownership_category($fixtures->child_category)->parent,
            'Child category relationship changed.'
        );

    } finally {
        ownership_update_category_name($id, $original->name);
    }
}

function ownership_test_foreign_dependency(
    DilitantArticleFixtures $fixtures,
    DilitantOwnershipTestJournal $journal
): void {
    $category = $fixtures->outside_category;
    $record = $journal->record('category', $category);

    ownership_cleanup_record(
        $fixtures,
        $journal->record('post', $fixtures->outside_post)
    );

    $author = (int) ownership_post($fixtures->root_post)->post_author;
    $title = 'HF-M08 Foreign Dependency ' . $fixtures->run_id();

    $id = ownership_create_foreign_post(
        $title,
        $author,
        $category
    );

    try {
        dilitant_test_assert_same(
            [$category],
            ownership_post_categories($id),
            'Foreign post category mismatch.'
        );

        $objects = get_objects_in_term($category, 'category');

        dilitant_test_assert_same(
            [$id],
            dilitant_test_normalize_ids($objects),
            'Unexpected category dependencies.'
        );

        ownership_expect_rejection(
            static fn() => ownership_cleanup_record($fixtures, $record),
            'Category still has dependent objects.'
        );

        ownership_category($category);

        dilitant_test_assert_same(
            $title,
            ownership_post($id)->post_title,
            'Foreign post modified.'
        );

        dilitant_test_assert_same(
            [$category],
            ownership_post_categories($id),
            'Foreign category relationship changed.'
        );

    } finally {
        ownership_delete_foreign_post($id, $title);
    }
}