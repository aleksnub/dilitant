<?php

declare(strict_types=1);

trait DilitantManifestStorage
{
    private static function read_storage(): ?array
    {
        global $wpdb;

        self::assert_environment();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_value
                 FROM {$wpdb->options}
                 WHERE option_name = %s",
                self::OPTION
            ),
            ARRAY_A
        );

        if ($wpdb->last_error !== '' || !is_array($rows)) {
            throw new RuntimeException('Manifest storage read failed.');
        }

        self::assert_environment();

        if ($rows === []) {
            return null;
        }

        if (count($rows) !== 1) {
            throw new RuntimeException('Ambiguous manifest storage.');
        }

        $raw = $rows[0]['option_value'] ?? null;

        if (!is_string($raw) || !is_serialized($raw)) {
            throw new RuntimeException('Invalid manifest serialization.');
        }

        $data = @unserialize(
            $raw,
            ['allowed_classes' => false]
        );

        if (!is_array($data) || serialize($data) !== $raw) {
            throw new RuntimeException('Invalid stored manifest.');
        }

        return $data;
    }

    private function commit(array $next): void
    {
        $this->persist($next, $this->data);
    }

    private function persist(
        array $next,
        ?array $expected
    ): void {
        global $wpdb;

        self::assert_environment();
        self::validate($next);

        if (self::read_storage() !== $expected) {
            throw new RuntimeException('Persistent manifest changed.');
        }

        if ($next === $expected) {
            return;
        }

        $serialized = serialize($next);

        self::assert_environment();

        if ($expected === null) {
            $affected = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$wpdb->options}
                        (option_name, option_value, autoload)
                     VALUES (%s, %s, %s)",
                    self::OPTION,
                    $serialized,
                    'no'
                )
            );
        } else {
            $affected = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->options}
                     SET option_value = %s, autoload = %s
                     WHERE option_name = %s
                       AND BINARY option_value = BINARY %s",
                    $serialized,
                    'no',
                    self::OPTION,
                    serialize($expected)
                )
            );
        }

        if ($wpdb->last_error !== '' || $affected !== 1) {
            throw new RuntimeException('Manifest persistence failed.');
        }

        wp_cache_delete(self::OPTION, 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');

        self::assert_environment();

        if (self::read_storage() !== $next) {
            throw new RuntimeException('Manifest verification failed.');
        }

        $this->data = $next;
    }
}