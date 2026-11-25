<?php

declare(strict_types=1);

function dilitant_visibility_initialize(
    ?DilitantArticleFixtures &$fixtures,
    ?DilitantVisibilityUsers &$users
): void {
    dilitant_visibility_verify_interfaces();

    $fixtures = new DilitantArticleFixtures();

    $fixtures->create();

    echo "PASS: base fixture creation\n";

    $fixtures->create_query_fixtures();

    echo "PASS: query fixture creation\n";

    $fixtures->verify_query_fixtures();

    echo "PASS: query fixture verification\n";

    $users = new DilitantVisibilityUsers();

    $users->create();

    echo "PASS: visibility user creation\n";
}