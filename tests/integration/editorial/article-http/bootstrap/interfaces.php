<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

function dilitant_http_require_functions(
    array $functions
): void {
    foreach ($functions as $function) {
        if (!is_string($function) || !function_exists($function)) {
            throw new DilitantHttpInterfaceException(
                'Required function is unavailable: '
                . (
                    is_string($function)
                        ? $function
                        : '[invalid function name]'
                )
            );
        }
    }
}

function dilitant_http_verify_test_interfaces(): void
{
    dilitant_http_require_functions([
        'dilitant_test_assert',
        'dilitant_test_assert_same',
        'dilitant_test_normalize_ids',
        'dilitant_test_query_ids',
    ]);
}

function dilitant_http_verify_article_interfaces(): void
{
    dilitant_http_require_functions([
        'dilitant_land_get_blog_category_id',
        'dilitant_land_get_blog_category_ids',
        'dilitant_land_is_blog_article',
        'dilitant_land_get_blog_articles',
        'dilitant_land_is_public_blog_article',
        'dilitant_land_can_preview_blog_article',
        'dilitant_land_can_render_blog_article',
        'dilitant_land_enforce_blog_article_visibility',
    ]);
}

function dilitant_http_verify_wordpress_interfaces(): void
{
    dilitant_http_require_functions([
        'wp_insert_term',
        'get_term',
        'wp_delete_term',
        'wp_set_post_categories',
        'wp_set_object_terms',

        'wp_insert_post',
        'wp_update_post',
        'get_post',
        'wp_delete_post',

        'wp_insert_user',
        'get_user_by',
        'get_userdata',
        'get_current_user_id',
        'wp_set_current_user',
        'current_user_can',
        'user_can',
        'clean_user_cache',

        'wp_authenticate',
        'wp_generate_auth_cookie',
        'wp_validate_auth_cookie',
        'wp_get_session_token',
        'wp_parse_auth_cookie',

        'wp_create_nonce',
        'wp_verify_nonce',

        'get_post_meta',
        'update_post_meta',
        'get_term_meta',
        'update_term_meta',
        'get_user_meta',
        'update_user_meta',
    ]);

    foreach ([
        'wpdb',
        'WP_Post',
        'WP_Term',
        'WP_User',
        'WP_Query',
        'WP_Session_Tokens',
    ] as $class) {
        if (!class_exists($class)) {
            throw new DilitantHttpInterfaceException(
                "Required WordPress class is unavailable: {$class}"
            );
        }
    }

    if (!is_file(ABSPATH . 'wp-admin/includes/user.php')) {
        throw new DilitantHttpInterfaceException(
            'WordPress user administration API unavailable.'
        );
    }
}

function dilitant_http_verify_fixture_interfaces(): void
{
    if (!class_exists('DilitantArticleFixtures', false)) {
        throw new DilitantHttpInterfaceException(
            'DilitantArticleFixtures is unavailable.'
        );
    }

    $reflection = new ReflectionClass(
        DilitantArticleFixtures::class
    );

    if (!$reflection->isInstantiable()) {
        throw new DilitantHttpInterfaceException(
            'DilitantArticleFixtures is not instantiable.'
        );
    }

    $required_methods = [
        'create',
        'create_users',
        'verify_users',
        'all_user_ids',
        'attach_journal',
        'export_state',

        'create_query_fixtures',
        'verify_base_fixtures',
        'verify_query_fixtures',

        'expected_category_ids',
        'expected_published_post_ids',
        'expected_child_post_ids',
        'all_post_ids',
        'all_category_ids',
        'excluded_post_ids',
        'archive_post_type',
        'fixture_password',
        'run_id',
        'cleanup',
    ];

    foreach ($required_methods as $method) {
        if (!$reflection->hasMethod($method)) {
            throw new DilitantHttpInterfaceException(
                "Required fixture method is missing: {$method}"
            );
        }

        $definition = $reflection->getMethod($method);

        if (!$definition->isPublic() || $definition->isStatic()) {
            throw new DilitantHttpInterfaceException(
                "Unexpected fixture method interface: {$method}"
            );
        }
    }

    foreach ([
        'restore_ready',
        'restore_for_cleanup',
    ] as $method) {
        if (!$reflection->hasMethod($method)) {
            throw new DilitantHttpInterfaceException(
                "Required fixture method is missing: {$method}"
            );
        }

        $definition = $reflection->getMethod($method);

        if (!$definition->isPublic() || !$definition->isStatic()) {
            throw new DilitantHttpInterfaceException(
                "Unexpected fixture method interface: {$method}"
            );
        }
    }
}

function dilitant_http_verify_user_interfaces(): void
{
    if (!interface_exists('DilitantFixtureJournal', false)) {
        throw new DilitantHttpInterfaceException(
            'Fixture journal interface unavailable.'
        );
    }

    if (!trait_exists('DilitantArticleFixtureUsers', false)) {
        throw new DilitantHttpInterfaceException(
            'Fixture users trait unavailable.'
        );
    }

    if (!enum_exists('DilitantUserResolutionStatus', false)) {
        throw new DilitantHttpInterfaceException(
            'User resolution status unavailable.'
        );
    }

    $interfaces = [
        DilitantFixtureUserIdentity::class => [
            'slots',
            'role',
            'create',
            'validate',
            'validate_record',
            'matches',
        ],

        DilitantFixtureUserResolution::class => [
            'guard',
            'query',
            'count',
            'protected_id',
            'resolve',
            'require_absent',
            'require_resolved',
        ],

        DilitantFixtureUserPermissions::class => [
            'verify',
            'verify_post_access',
        ],

        DilitantFixtureUserCreation::class => [
            'create',
        ],

        DilitantFixtureUserDependencies::class => [
            'inspect',
            'require_deletable',
            'require_absent',
        ],

        DilitantFixtureUserCleanup::class => [
            'remove',
        ],
    ];

    foreach ($interfaces as $class => $methods) {
        if (!class_exists($class, false)) {
            throw new DilitantHttpInterfaceException(
                "Required user component unavailable: {$class}"
            );
        }

        $reflection = new ReflectionClass($class);

        foreach ($methods as $method) {
            if (!$reflection->hasMethod($method)) {
                throw new DilitantHttpInterfaceException(
                    "Required user method unavailable: {$class}::{$method}"
                );
            }

            $definition = $reflection->getMethod($method);

            if (
                !$definition->isPublic()
                || !$definition->isStatic()
            ) {
                throw new DilitantHttpInterfaceException(
                    "Invalid user method interface: {$class}::{$method}"
                );
            }
        }
    }

    dilitant_http_require_functions([
        'dilitant_fixture_acquire_lock',
        'dilitant_fixture_assert_lock',
        'dilitant_fixture_release_lock',
    ]);
}

function dilitant_http_verify_fixture_administrator(): void
{
    $administrator = get_user_by('login', 'testadmin');

    if (!$administrator instanceof WP_User) {
        throw new DilitantHttpInterfaceException(
            'Required test administrator is unavailable.'
        );
    }

    if ((int) $administrator->ID <= 0) {
        throw new DilitantHttpInterfaceException(
            'Invalid test administrator identifier.'
        );
    }

    if (!user_can($administrator, 'manage_options')) {
        throw new DilitantHttpInterfaceException(
            'Test administrator has insufficient permissions.'
        );
    }
}

function dilitant_http_verify_bootstrap_interfaces(): void
{
    dilitant_http_verify_test_interfaces();
    dilitant_http_verify_wordpress_interfaces();
    dilitant_http_verify_article_interfaces();
    dilitant_http_verify_fixture_interfaces();
    dilitant_http_verify_user_interfaces();
    dilitant_http_verify_fixture_administrator();
}