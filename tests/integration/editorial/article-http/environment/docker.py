"""Управление испытательным Docker-контейнером."""

from __future__ import annotations

import json
import os
import re
import secrets

from pathlib import Path
from typing import Any

from .config import EnvironmentConfig

from .errors import (
    ContainerError,
    ContainerOwnershipError,
)

from .process import ProcessRunner


# Статический сценарий запуска Apache.
#
# Исполняется внутри Linux-контейнера.
# Не содержит пользовательских подстановок,
# паролей или динамически формируемых команд.
#
# Управление жизненным циклом контейнера
# остаётся ответственностью Python.

CONTAINER_STARTUP_SCRIPT = r"""
set -eu

php -l /var/www/html/wp-config.php

cmp \
    /var/www/html/wp-config.php \
    /tests/integration/editorial/article-http/wp-config.php

command -v runuser >/dev/null

runuser -u www-data -- \
    test -r /var/www/html/wp-config.php

echo "PASS: www-data configuration access"

runuser -u www-data -- php -r '
    require "/var/www/html/wp-load.php";
    echo "PASS: www-data WordPress preflight\n";
'

a2enmod rewrite >/dev/null

cat > /etc/apache2/conf-available/dilitant-http-test.conf <<'APACHE'
ServerName 127.0.0.1

<Directory /var/www/html>
    Options FollowSymLinks
    AllowOverride None
    Require all granted

    DirectoryIndex index.php

    RewriteEngine On

    RewriteRule ^index\.php$ - [L]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d

    RewriteRule . /index.php [L]
</Directory>
APACHE

a2enconf dilitant-http-test >/dev/null

apache2ctl -t

exec apache2-foreground
"""


class DockerEnvironment:
    """Интерфейс управления испытательным контейнером."""

    def __init__(
        self,
        config: EnvironmentConfig,
        process: ProcessRunner,
    ) -> None:
        self.config = config
        self.process = process

    def find_container_id(self) -> str | None:
        """Разрешает ID контейнера по точному имени."""

        output = self.process.output(
            [
                "docker",
                "container",
                "ls",
                "--all",
                "--no-trunc",
                "--filter",
                f"name=^/{self.config.container_name}$",
                "--format",
                "{{.ID}}",
            ]
        )

        identifiers = [
            line.strip()
            for line in output.splitlines()
            if line.strip()
        ]

        if not identifiers:
            return None

        if len(identifiers) != 1:
            raise ContainerError(
                "Ambiguous HTTP test container identity."
            )

        container_id = identifiers[0]

        self.validate_id(container_id)

        return container_id

    @staticmethod
    def validate_id(container_id: str) -> None:
        """Проверяет полный Docker container ID."""

        if re.fullmatch(
            r"[0-9a-f]{64}",
            container_id,
        ) is None:
            raise ContainerError(
                "Invalid Docker container identifier."
            )

    def inspect(
        self,
        container_id: str,
    ) -> dict[str, Any]:
        """Получает структурированные сведения Docker."""

        self.validate_id(container_id)

        raw = self.process.output(
            [
                "docker",
                "container",
                "inspect",
                container_id,
            ]
        )

        try:
            records = json.loads(raw)

        except json.JSONDecodeError as error:
            raise ContainerError(
                "Docker returned invalid inspection data."
            ) from error

        if (
            not isinstance(records, list)
            or len(records) != 1
            or not isinstance(records[0], dict)
        ):
            raise ContainerError(
                "Unexpected Docker inspection result."
            )

        record = records[0]

        if record.get("Id") != container_id:
            raise ContainerError(
                "Docker inspection identity mismatch."
            )

        return record

    def verify_owner(
        self,
        container_id: str,
    ) -> dict[str, Any]:
        """Подтверждает принадлежность контейнера стенду."""

        record = self.inspect(container_id)

        container_config = record.get("Config", {})

        labels = container_config.get("Labels") or {}

        if (
            labels.get(self.config.owner_label)
            != self.config.owner_value
        ):
            raise ContainerOwnershipError(
                "Container does not belong "
                "to HTTP test infrastructure."
            )

        expected_name = (
            f"/{self.config.container_name}"
        )

        if record.get("Name") != expected_name:
            raise ContainerOwnershipError(
                "Unexpected HTTP test container name."
            )

        return record

    def require_container(self) -> str:
        """Разрешает существующий доверенный контейнер."""

        container_id = self.find_container_id()

        if container_id is None:
            raise ContainerError(
                "HTTP test container is absent."
            )

        self.verify_owner(container_id)

        return container_id

    def source_image(self) -> str:
        """Возвращает точный ID используемого образа."""

        image = self.process.output(
            [
                "docker",
                "inspect",
                "--format",
                "{{.Image}}",
                self.config.source_container,
            ]
        )

        if re.fullmatch(
            r"sha256:[0-9a-f]{64}",
            image,
        ) is None:
            raise ContainerError(
                "Unexpected source image identifier."
            )

        return image

    def build_environment(self) -> dict[str, str]:
        """Формирует временное окружение нового контейнера."""

        environment = {}

        for name, expected in (
            self.config.required_environment.items()
        ):
            if os.environ.get(name) != expected:
                raise ContainerError(
                    f"Unexpected test environment: {name}"
                )

            environment[name] = expected

        password_name = self.config.password_variable

        password = os.environ.get(password_name)

        if not password:
            raise ContainerError(
                "Test database password is missing."
            )

        environment[password_name] = password

        environment["DILITANT_HTTP_TEST_MODE"] = "1"

        environment["DILITANT_HTTP_TEST_URL"] = (
            self.config.base_url
        )

        environment["DILITANT_HTTP_TEST_SECRET"] = (
            secrets.token_hex(64)
        )

        return environment

    def create(
        self,
        cid_file: Path,
    ) -> str:
        """Создаёт контейнер без запуска Apache."""

        if self.find_container_id() is not None:
            raise ContainerError(
                "HTTP test container already exists."
            )

        if cid_file.exists():
            raise ContainerError(
                "Container ID file already exists."
            )

        image = self.source_image()

        environment = self.build_environment()

        config = self.config

        arguments = [
            "docker",
            "create",

            "--name",
            config.container_name,

            "--cidfile",
            str(cid_file),

            "--label",
            f"{config.owner_label}={config.owner_value}",

            "--network",
            config.network_name,

            "--publish",
            f"{config.host}:{config.port}:80",
        ]

        for destination, source in (
            config.expected_mounts.items()
        ):
            arguments.extend(
                [
                    "--mount",

                    (
                        "type=bind,"
                        f"source={source},"
                        f"target={destination},"
                        "readonly"
                    ),
                ]
            )

        arguments.extend(
            [
                "--tmpfs",

                (
                    f"{config.private_storage_target}:"
                    "rw,nosuid,nodev,noexec,"
                    "mode=0700,"
                    f"uid={config.apache_uid},"
                    f"gid={config.apache_gid}"
                ),
            ]
        )

        # В командную строку передаются
        # только имена переменных.
        #
        # Значения секретов Docker получает
        # из окружения subprocess.

        for variable in environment:
            arguments.extend(
                [
                    "--env",
                    variable,
                ]
            )

        arguments.extend(
            [
                "--entrypoint",
                "/bin/sh",

                image,

                "-ec",
                CONTAINER_STARTUP_SCRIPT,
            ]
        )

        result = self.process.run(
            arguments,
            environment=environment,
        )

        container_id = result.stdout.strip()

        self.validate_id(container_id)

        recorded_id = (
            cid_file
            .read_text(encoding="utf-8")
            .strip()
        )

        if recorded_id != container_id:
            raise ContainerError(
                "Created container ID does not match CID file."
            )

        self.verify_owner(container_id)

        return container_id

    def start(
        self,
        container_id: str,
    ) -> None:
        """Запускает ранее созданный контейнер."""

        self.verify_owner(container_id)

        self.process.run(
            [
                "docker",
                "start",
                container_id,
            ]
        )

    def is_running(
        self,
        container_id: str,
    ) -> bool:
        """Проверяет текущее состояние контейнера."""

        record = self.verify_owner(container_id)

        return (
            record
            .get("State", {})
            .get("Running")
            is True
        )

    def execute(
        self,
        container_id: str,
        arguments: list[str],
        *,
        user: str | None = None,
        capture: bool = True,
        check: bool = True,
    ):
        """Исполняет команду внутри доверенного стенда."""

        self.verify_owner(container_id)

        command = [
            "docker",
            "exec",
        ]

        if user is not None:
            command.extend(
                [
                    "--user",
                    user,
                ]
            )

        command.append(container_id)

        command.extend(arguments)

        return self.process.run(
            command,
            capture=capture,
            check=check,
        )

    def php(
        self,
        container_id: str,
        arguments: list[str],
        *,
        capture: bool = True,
        check: bool = True,
    ):
        """Исполняет PHP с полномочиями Apache."""

        return self.execute(
            container_id,

            [
                "php",
                *arguments,
            ],

            user=(
                f"{self.config.apache_uid}:"
                f"{self.config.apache_gid}"
            ),

            capture=capture,

            check=check,
        )

    def logs(
        self,
        container_id: str,
        tail: int = 200,
    ) -> str:
        """Получает диагностические журналы контейнера."""

        self.verify_owner(container_id)

        result = self.process.run(
            [
                "docker",
                "logs",
                "--tail",
                str(tail),
                container_id,
            ]
        )

        return (
            (result.stdout or "")
            + (result.stderr or "")
        ).strip()

    def remove(
        self,
        container_id: str,
    ) -> None:
        """Удаляет только подтверждённый тестовый контейнер."""

        self.verify_owner(container_id)

        self.process.run(
            [
                "docker",
                "rm",
                "--force",
                container_id,
            ]
        )

        remaining_id = self.find_container_id()

        if remaining_id == container_id:
            raise ContainerError(
                "HTTP test container was not removed."
            )