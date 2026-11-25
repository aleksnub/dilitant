"""Подготовка маршрутизации WordPress для HTTP-испытаний.

Нормативная основа:
    ТП-03 — Editorial
    EDC-01 — Article Model
    AM-D03 — Public Visibility
    HTTP-D01 — Environment

Ответственность:
    - проверка идентичности тестовой базы;
    - настройка постоянных ссылок;
    - генерация WordPress rewrite rules;
    - проверка специальных маршрутов Article Model;
    - независимое подтверждение сохранённых правил.

Рабочая база данных не изменяется.
Файлы WordPress не изменяются.
"""

from __future__ import annotations

import json

from .config import EnvironmentConfig

from .docker import DockerEnvironment

from .errors import (
    IsolationError,
    VerificationError,
)

from .process import report_pass


# --------------------------------------------------
# Проверка изоляции WordPress
# --------------------------------------------------

WORDPRESS_ISOLATION_CODE = r"""
global $wpdb;

if (
    !defined("DILITANT_TEST_MODE")
    || DILITANT_TEST_MODE !== 1
) {
    if (getenv("DILITANT_TEST_MODE") !== "1") {
        throw new RuntimeException(
            "Test environment is unavailable."
        );
    }
}

if (getenv("DILITANT_HTTP_TEST_MODE") !== "1") {
    throw new RuntimeException(
        "HTTP test mode is unavailable."
    );
}

if (
    !defined("DB_NAME")
    || DB_NAME !== "dilitant_land_test"
    || !defined("DB_USER")
    || DB_USER !== "dilitant_test"
    || !$wpdb instanceof wpdb
    || $wpdb->prefix !== "wp_test_"
    || $wpdb->base_prefix !== "wp_test_"
    || $wpdb->get_var("SELECT DATABASE()")
        !== "dilitant_land_test"
) {
    throw new RuntimeException(
        "Database isolation verification failed."
    );
}
"""


# --------------------------------------------------
# Инициализация постоянных ссылок
# --------------------------------------------------

WORDPRESS_ROUTING_INITIALIZATION_CODE = (
    r"""
require "/var/www/html/wp-load.php";
"""
    + WORDPRESS_ISOLATION_CODE
    + r"""

global $wp_rewrite;

if (!$wp_rewrite instanceof WP_Rewrite) {
    throw new RuntimeException(
        "WordPress rewrite interface is unavailable."
    );
}

$expected = "/%postname%/";

$previous = get_option("permalink_structure");

if ($previous !== $expected) {
    $wp_rewrite->set_permalink_structure(
        $expected
    );
}

if (
    get_option("permalink_structure")
    !== $expected
) {
    throw new RuntimeException(
        "Unable to configure permalink structure."
    );
}

/*
 * Выполняем soft flush.
 *
 * false означает:
 *     - пересоздание rewrite_rules;
 *     - сохранение правил в базе;
 *     - отсутствие изменений .htaccess.
 */
flush_rewrite_rules(false);

$rules = get_option("rewrite_rules");

if (
    !is_array($rules)
    || $rules === []
) {
    throw new RuntimeException(
        "WordPress rewrite rules are empty."
    );
}

echo json_encode(
    [
        "database" =>
            $wpdb->get_var("SELECT DATABASE()"),

        "prefix" =>
            $wpdb->prefix,

        "previous_permalink_structure" =>
            $previous,

        "permalink_structure" =>
            get_option("permalink_structure"),

        "rule_count" =>
            count($rules),
    ],

    JSON_THROW_ON_ERROR
);
"""
)


# --------------------------------------------------
# Независимая проверка сохранённой маршрутизации
# --------------------------------------------------

WORDPRESS_ROUTING_VERIFICATION_CODE = (
    r"""
require "/var/www/html/wp-load.php";
"""
    + WORDPRESS_ISOLATION_CODE
    + r"""

global $wp_rewrite;

if (!$wp_rewrite instanceof WP_Rewrite) {
    throw new RuntimeException(
        "WordPress rewrite interface is unavailable."
    );
}

$rules = get_option("rewrite_rules");

if (
    !is_array($rules)
    || $rules === []
) {
    throw new RuntimeException(
        "Stored WordPress rewrite rules are empty."
    );
}

echo json_encode(
    [
        "database" =>
            $wpdb->get_var("SELECT DATABASE()"),

        "prefix" =>
            $wpdb->prefix,

        "permalink_structure" =>
            get_option("permalink_structure"),

        "using_permalinks" =>
            $wp_rewrite->using_permalinks(),

        "rule_count" =>
            count($rules),

        "rules" =>
            $rules,
    ],

    JSON_THROW_ON_ERROR
);
"""
)


# --------------------------------------------------
# Управляющий компонент
# --------------------------------------------------

class WordPressRouting:

    """Подготовка и проверка маршрутизации."""

    def __init__(
        self,
        config: EnvironmentConfig,
        docker: DockerEnvironment,
    ) -> None:
        self.config = config
        self.docker = docker

    def _execute(
        self,
        container_id: str,
        code: str,
    ) -> dict:
        """Исполняет PHP-код с полномочиями Apache."""

        result = self.docker.php(
            container_id,
            [
                "-r",
                code,
            ],
        )

        try:
            data = json.loads(
                result.stdout,
            )

        except json.JSONDecodeError as error:
            raise VerificationError(
                "Invalid WordPress routing response."
            ) from error

        if not isinstance(data, dict):
            raise VerificationError(
                "Unexpected WordPress routing response."
            )

        return data

    def _verify_identity(
        self,
        data: dict,
    ) -> None:
        """Проверяет идентичность тестовой базы."""

        if (
            data.get("database")
            != self.config.database_name
        ):
            raise IsolationError(
                "Unexpected routing database identity."
            )

        if (
            data.get("prefix")
            != self.config.table_prefix
        ):
            raise IsolationError(
                "Unexpected routing table prefix."
            )

    def initialize(
        self,
        container_id: str,
    ) -> None:
        """Настраивает постоянные ссылки и rewrite rules."""

        data = self._execute(
            container_id,
            WORDPRESS_ROUTING_INITIALIZATION_CODE,
        )

        self._verify_identity(
            data,
        )

        if (
            data.get("permalink_structure")
            != self.config.permalink_structure
        ):
            raise VerificationError(
                "Unexpected initialized permalink structure."
            )

        rule_count = data.get(
            "rule_count",
        )

        if (
            type(rule_count) is not int
            or rule_count <= 0
        ):
            raise VerificationError(
                "No WordPress rewrite rules were generated."
            )

        report_pass(
            "WordPress permalink initialization"
        )

        report_pass(
            "WordPress rewrite rule generation"
        )

        print(
            "Permalink structure: "
            f"{data['permalink_structure']}",
            flush=True,
        )

        print(
            f"Generated rewrite rules: {rule_count}",
            flush=True,
        )

    def verify(
        self,
        container_id: str,
    ) -> None:
        """Независимо проверяет сохранённые правила."""

        data = self._execute(
            container_id,
            WORDPRESS_ROUTING_VERIFICATION_CODE,
        )

        self._verify_identity(
            data,
        )

        if (
            data.get("permalink_structure")
            != self.config.permalink_structure
        ):
            raise VerificationError(
                "Unexpected stored permalink structure."
            )

        if data.get("using_permalinks") is not True:
            raise VerificationError(
                "WordPress permalinks are disabled."
            )

        rules = data.get(
            "rules",
        )

        if (
            not isinstance(rules, dict)
            or not rules
        ):
            raise VerificationError(
                "Stored rewrite rule mapping is invalid."
            )

        if data.get("rule_count") != len(rules):
            raise VerificationError(
                "Rewrite rule count mismatch."
            )

        for rule, destination in (
            self.config.article_rewrite_rules.items()
        ):
            if rules.get(rule) != destination:
                raise VerificationError(
                    "Required Article Model rewrite "
                    f"rule is missing or invalid: {rule}"
                )

        report_pass(
            "Stored WordPress permalink structure"
        )

        report_pass(
            "Stored WordPress rewrite rules"
        )

        report_pass(
            "Article Model catalog rewrite rule"
        )

        report_pass(
            "Article Model singular rewrite rule"
        )

        print(
            f"Verified rewrite rules: {len(rules)}",
            flush=True,
        )