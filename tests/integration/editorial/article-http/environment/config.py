"""Типизированная конфигурация HTTP Environment."""

from dataclasses import dataclass
from pathlib import Path


@dataclass(frozen=True)
class EnvironmentConfig:
    http_directory: Path

    container_name: str = "dilitant_land_http_test"
    source_container: str = "dilitant_land_wp"
    database_container: str = "dilitant_land_db"

    network_name: str = (
        "dilitant_land_dilitant_land_network"
    )

    database_name: str = "dilitant_land_test"
    database_user: str = "dilitant_test"
    database_host: str = "dilitant_land_db:3306"
    table_prefix: str = "wp_test_"

    theme_name: str = "dilitant-land"
    permalink_structure: str = "/%postname%/"

    host: str = "127.0.0.1"
    port: int = 8095

    owner_label: str = "org.dilitant.editorial.http-test"
    owner_value: str = "am-d03"

    wordpress_target: str = "/var/www/html"
    tests_target: str = "/tests"

    private_storage_target: str = (
        "/var/www/dilitant-private"
    )

    apache_user: str = "www-data"
    apache_uid: int = 33
    apache_gid: int = 33

    startup_timeout: float = 45.0
    request_timeout: float = 5.0
    command_timeout: float = 60.0

    port_release_timeout: float = 90.0
    port_poll_interval: float = 0.25

    @property
    def project_root(self) -> Path:
        return self.http_directory.parents[3]

    @property
    def wordpress_source(self) -> Path:
        return (
            self.project_root
            / "src"
            / "wordpress"
        )

    @property
    def tests_source(self) -> Path:
        return self.project_root / "tests"

    @property
    def wordpress_config(self) -> Path:
        return self.http_directory / "wp-config.php"

    @property
    def wordpress_config_target(self) -> str:
        return f"{self.wordpress_target}/wp-config.php"

    @property
    def base_url(self) -> str:
        return f"http://{self.host}:{self.port}"

    @property
    def required_environment(self) -> dict[str, str]:
        return {
            "DILITANT_TEST_MODE": "1",
            "DILITANT_TEST_DB_NAME": self.database_name,
            "DILITANT_TEST_DB_USER": self.database_user,
            "DILITANT_TEST_DB_HOST": self.database_host,
        }

    @property
    def password_variable(self) -> str:
        return "DILITANT_TEST_DB_PASSWORD"

    @property
    def expected_mounts(self) -> dict[str, Path]:
        return {
            self.wordpress_target:
                self.wordpress_source,

            self.tests_target:
                self.tests_source,

            self.wordpress_config_target:
                self.wordpress_config,
        }

    @property
    def expected_wordpress_identity(self) -> dict[str, str]:
        return {
            "database": self.database_name,
            "prefix": self.table_prefix,
            "home": self.base_url,
            "siteurl": self.base_url,
            "theme": self.theme_name,
        }

    @property
    def article_rewrite_rules(self) -> dict[str, str]:
        return {
            r"^diletant-armenia/articles/?$":
                "index.php?"
                "category_name=diletant-armenia",

            r"^diletant-armenia/articles/([^/]+)/?$":
                "index.php?"
                "category_name=diletant-armenia"
                "&name=$matches[1]",
        }


def create_config() -> EnvironmentConfig:
    http_directory = (
        Path(__file__)
        .resolve()
        .parent
        .parent
    )

    return EnvironmentConfig(
        http_directory=http_directory,
    )