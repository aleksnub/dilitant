"""Проверка изоляции и работоспособности HTTP-стенда."""

from __future__ import annotations

import http.client
import json
import time

from pathlib import Path

from .config import EnvironmentConfig

from .docker import DockerEnvironment

from .errors import (
    IsolationError,
    StartupError,
    VerificationError,
)

from .process import report_pass


WORDPRESS_VERIFICATION_CODE = r"""
require "/var/www/html/wp-load.php";

global $wpdb;

if (!$wpdb instanceof wpdb) {
    throw new RuntimeException(
        "WordPress database interface is missing."
    );
}

if (get_option("active_plugins") !== []) {
    throw new RuntimeException(
        "Unexpected active plugins."
    );
}

$functions = [
    "dilitant_land_is_blog_article",
    "dilitant_land_is_public_blog_article",
    "dilitant_land_can_preview_blog_article",
    "dilitant_land_can_render_blog_article",
    "dilitant_land_enforce_blog_article_visibility",
];

foreach ($functions as $function) {
    if (!function_exists($function)) {
        throw new RuntimeException(
            "Required interface missing: " . $function
        );
    }
}

echo json_encode(
    [
        "database" =>
            $wpdb->get_var("SELECT DATABASE()"),

        "prefix" =>
            $wpdb->prefix,

        "home" =>
            home_url(),

        "siteurl" =>
            site_url(),

        "theme" =>
            get_stylesheet(),
    ],
    JSON_THROW_ON_ERROR
);
"""


class EnvironmentVerifier:
    """Проверяет фактическое состояние HTTP Environment."""

    def __init__(
        self,
        config: EnvironmentConfig,
        docker: DockerEnvironment,
    ) -> None:
        self.config = config
        self.docker = docker

    def verify_mounts(
        self,
        container_id: str,
    ) -> None:
        """Проверяет обязательные read-only bind mounts."""

        record = self.docker.verify_owner(
            container_id,
        )

        mounts = record.get("Mounts")

        if not isinstance(mounts, list):
            raise IsolationError(
                "Docker mount information is unavailable."
            )

        indexed = {
            mount["Destination"]: mount

            for mount in mounts
        }

        if len(indexed) != len(mounts):
            raise IsolationError(
                "Duplicate Docker mount destinations."
            )

        for destination, source in (
            self.config.expected_mounts.items()
        ):
            mount = indexed.get(destination)

            if mount is None:
                raise IsolationError(
                    f"Required mount is missing: {destination}"
                )

            if mount.get("Type") != "bind":
                raise IsolationError(
                    f"Unexpected mount type: {destination}"
                )

            if mount.get("RW") is not False:
                raise IsolationError(
                    f"Mount is not read-only: {destination}"
                )

            actual_source = Path(
                mount.get("Source", "")
            ).resolve()

            if actual_source != source.resolve():
                raise IsolationError(
                    f"Unexpected mount source: {destination}"
                )

            report_pass(
                f"Read-only mount: {destination}"
            )

        # Дополнительные writable bind mounts
        # не допускаются.

        for mount in mounts:
            if (
                mount.get("Type") == "bind"

                and mount.get("RW") is not False
            ):
                raise IsolationError(
                    "Unexpected writable bind mount."
                )

    def verify_private_storage(
        self,
        container_id: str,
    ) -> None:
        """Подтверждает фактическое временное хранилище."""

        result = self.docker.execute(
            container_id,
            [
                "cat",
                "/proc/mounts",
            ],
        )

        target = self.config.private_storage_target

        matching = []

        for line in result.stdout.splitlines():
            fields = line.split()

            if len(fields) < 4:
                continue

            if fields[1] == target:
                matching.append(fields)

        if len(matching) != 1:
            raise IsolationError(
                "Private storage mount is missing "
                "or ambiguous."
            )

        mount = matching[0]

        if mount[2] != "tmpfs":
            raise IsolationError(
                "Private storage is not tmpfs."
            )

        options = set(
            mount[3].split(",")
        )

        required_options = {
            "rw",
            "nosuid",
            "nodev",
            "noexec",
        }

        if not required_options.issubset(options):
            raise IsolationError(
                "Unexpected private storage mount options."
            )

        # Проверяем доступ с полномочиями Apache.

        self.docker.execute(
            container_id,

            [
                "test",
                "-w",
                target,
            ],

            user=(
                f"{self.config.apache_uid}:"
                f"{self.config.apache_gid}"
            ),
        )

        report_pass(
            "Temporary private storage"
        )

    def verify_port_binding(
        self,
        container_id: str,
    ) -> None:
        """Проверяет публикацию HTTP-порта."""

        record = self.docker.verify_owner(
            container_id,
        )

        ports = (
            record
            .get("NetworkSettings", {})
            .get("Ports")
        )

        if not isinstance(ports, dict):
            raise IsolationError(
                "Docker port bindings are unavailable."
            )

        expected = [
            {
                "HostIp": self.config.host,
                "HostPort": str(self.config.port),
            }
        ]

        if ports.get("80/tcp") != expected:
            raise IsolationError(
                "Unexpected HTTP port binding."
            )

        for name, bindings in ports.items():
            if name != "80/tcp" and bindings:
                raise IsolationError(
                    f"Unexpected additional port: {name}"
                )

        report_pass(
            "HTTP port is bound to localhost"
        )

    def verify_network(
        self,
        container_id: str,
    ) -> None:
        """Проверяет принадлежность ожидаемой Docker Network."""

        record = self.docker.verify_owner(
            container_id,
        )

        networks = (
            record
            .get("NetworkSettings", {})
            .get("Networks")
        )

        if not isinstance(networks, dict):
            raise IsolationError(
                "Docker network information is unavailable."
            )

        if set(networks) != {
            self.config.network_name,
        }:
            raise IsolationError(
                "Unexpected HTTP test container networks."
            )

        report_pass(
            "HTTP test container network"
        )

    def verify_apache(
        self,
        container_id: str,
    ) -> None:
        """Проверяет Apache и механизм rewrite."""

        self.docker.execute(
            container_id,
            [
                "apache2ctl",
                "-t",
            ],
        )

        modules = self.docker.execute(
            container_id,
            [
                "apache2ctl",
                "-M",
            ],
        )

        if "rewrite_module" not in modules.stdout:
            raise VerificationError(
                "Apache rewrite module is unavailable."
            )

        report_pass(
            "Apache configuration and rewrite module"
        )

    def verify_wordpress(
        self,
        container_id: str,
    ) -> None:
        """Проверяет WordPress с полномочиями www-data."""

        result = self.docker.php(
            container_id,

            [
                "-r",
                WORDPRESS_VERIFICATION_CODE,
            ],
        )

        try:
            actual = json.loads(
                result.stdout,
            )

        except json.JSONDecodeError as error:
            raise VerificationError(
                "WordPress returned invalid "
                "verification data."
            ) from error

        if actual != (
            self.config.expected_wordpress_identity
        ):
            raise IsolationError(
                "Unexpected loaded WordPress identity."
            )

        report_pass(
            "Loaded WordPress database isolation"
        )

        report_pass(
            "Loaded WordPress configuration"
        )

        report_pass(
            "Loaded Article Model interfaces"
        )

        report_pass(
            "WordPress execution as www-data"
        )

        for name, value in actual.items():
            print(
                f"{name}: {value}",
                flush=True,
            )

    def http_status(
        self,
        path: str = "/",
    ) -> int:
        """Возвращает статус непосредственного HTTP-ответа.

        Не использует proxy.
        Не следует перенаправлениям.
        Не выполняет DNS-разрешение сторонних адресов.
        """

        if (
            not path.startswith("/")
            or path.startswith("//")
        ):
            raise VerificationError(
                "Invalid local HTTP request path."
            )

        connection = http.client.HTTPConnection(
            host=self.config.host,

            port=self.config.port,

            timeout=self.config.request_timeout,
        )

        try:
            connection.request(
                "GET",

                path,

                headers={
                    "Connection": "close",

                    "User-Agent":
                        "Dilitant-HTTP-Environment/1.0",
                },
            )

            response = connection.getresponse()

            return response.status

        finally:
            connection.close()

    def wait_ready(
        self,
        container_id: str,
    ) -> None:
        """Ожидает действительной HTTP-готовности."""

        deadline = (
            time.monotonic()
            + self.config.startup_timeout
        )

        last_status = None

        last_error = None

        while time.monotonic() < deadline:
            if not self.docker.is_running(
                container_id,
            ):
                raise StartupError(
                    "HTTP test container stopped "
                    "during startup."
                )

            try:
                last_status = self.http_status("/")

                last_error = None

            except (
                OSError,
                http.client.HTTPException,
            ) as error:
                last_error = str(error)

            else:
                if last_status == 200:
                    report_pass(
                        "HTTP endpoint returned 200"
                    )

                    return

                # HTTP 500 и 503 означают,
                # что сервер уже принимает запросы,
                # но приложение сообщает об ошибке.
                #
                # Не повторяем заведомо неуспешный
                # запрос десятки раз.

                if last_status in {
                    500,
                    503,
                }:
                    raise StartupError(
                        "HTTP application startup failed: "
                        f"status {last_status}"
                    )

            remaining = (
                deadline - time.monotonic()
            )

            if remaining > 0:
                time.sleep(
                    min(1.0, remaining)
                )

        raise StartupError(
            "HTTP endpoint is unavailable; "
            f"last status: {last_status}; "
            f"last error: {last_error}"
        )

    def verify_not_found(self) -> None:
        """Проверяет сетевой ответ отсутствующего маршрута."""

        status = self.http_status(
            "/am-http-nonexistent-test-route/"
        )

        if status != 404:
            raise VerificationError(
                "Unexpected missing route status: "
                f"{status}"
            )

        report_pass(
            "Missing HTTP route returned 404"
        )

    def verify(
        self,
        container_id: str,
    ) -> None:
        """Исполняет полный набор проверок HTTP-D01."""

        self.docker.verify_owner(
            container_id,
        )

        self.verify_mounts(
            container_id,
        )

        self.verify_private_storage(
            container_id,
        )

        self.verify_port_binding(
            container_id,
        )

        self.verify_network(
            container_id,
        )

        self.verify_apache(
            container_id,
        )

        self.verify_wordpress(
            container_id,
        )

        self.wait_ready(
            container_id,
        )

        self.verify_not_found()

        report_pass(
            "HTTP-D01 environment verification"
        )