"""Изолированный Docker-транспорт SQL Probe."""

from __future__ import annotations

import os
import re
import secrets
import subprocess

from ..config import EnvironmentConfig

from ..errors import VerificationError

from .config import (
    DATABASE_READINESS_TIMEOUT,
    CLEANUP_TIMEOUT,
    PROBE_OWNER_LABEL,
)

from .probe_code import (
    DATABASE_READINESS_CODE,
)


class DatabaseProbeTransport:
    """Управляет временным проверочным контейнером."""

    def __init__(
        self,
        config: EnvironmentConfig,
    ) -> None:

        self.config = config

    @staticmethod
    def _invoke(
        arguments: list[str],
        *,
        environment: dict[str, str] | None = None,
        timeout: int = DATABASE_READINESS_TIMEOUT,
    ) -> subprocess.CompletedProcess[str]:
        """Исполняет Docker-команду без вывода секретов."""

        child_environment = os.environ.copy()

        child_environment.pop(
            "DILITANT_TEST_DB_PASSWORD",
            None,
        )

        if environment is not None:

            child_environment.update(
                environment
            )

        try:

            return subprocess.run(
                arguments,
                env=child_environment,
                capture_output=True,
                text=True,
                encoding="utf-8",
                errors="replace",
                timeout=timeout,
                check=False,
            )

        except subprocess.TimeoutExpired:

            raise VerificationError(
                "Database readiness operation timed out."
            ) from None

        except OSError:

            raise VerificationError(
                "Unable to execute database readiness operation."
            ) from None

        finally:

            child_environment.clear()

    def _resolve_source_image(self) -> str:
        """Разрешает точный образ будущего контейнера."""

        result = self._invoke([
            "docker",
            "inspect",
            "--format",
            "{{.Image}}",
            self.config.source_container,
        ])

        if result.returncode != 0:

            raise VerificationError(
                "Database readiness source image is unavailable."
            )

        image = result.stdout.strip()

        if re.fullmatch(
            r"sha256:[0-9a-f]{64}",
            image,
        ) is None:

            raise VerificationError(
                "Invalid database readiness image identity."
            )

        return image

    def _probe_name(self) -> str:
        """Формирует уникальное имя SQL Probe."""

        return (
            f"{self.config.container_name}"
            "-db-readiness-"
            f"{secrets.token_hex(12)}"
        )

    def _lookup_probe(
        self,
        name: str,
    ) -> str | None:
        """Разрешает временный контейнер по точному имени."""

        result = self._invoke(
            [
                "docker",
                "container",
                "ls",
                "--all",
                "--no-trunc",
                "--filter",
                f"name=^/{name}$",
                "--format",
                "{{.ID}}",
            ],
            timeout=CLEANUP_TIMEOUT,
        )

        if result.returncode != 0:

            raise VerificationError(
                "Unable to verify database readiness cleanup."
            )

        identifiers = [
            line.strip()
            for line in result.stdout.splitlines()
            if line.strip()
        ]

        if not identifiers:

            return None

        if len(identifiers) != 1:

            raise VerificationError(
                "Ambiguous database readiness container."
            )

        container_id = identifiers[0]

        if re.fullmatch(
            r"[0-9a-f]{64}",
            container_id,
        ) is None:

            raise VerificationError(
                "Invalid database readiness container ID."
            )

        return container_id

    def _verify_probe_owner(
        self,
        container_id: str,
        owner_token: str,
    ) -> None:
        """Подтверждает принадлежность контейнера."""

        result = self._invoke(
            [
                "docker",
                "inspect",
                "--format",
                (
                    '{{index .Config.Labels "'
                    + PROBE_OWNER_LABEL
                    + '"}}'
                ),
                container_id,
            ],
            timeout=CLEANUP_TIMEOUT,
        )

        if (
            result.returncode != 0
            or result.stdout.strip()
            != owner_token
        ):

            raise VerificationError(
                "Database readiness container ownership "
                "could not be confirmed."
            )

    def _verify_probe_absent(
        self,
        container_id: str,
    ) -> None:
        """Подтверждает отсутствие временного контейнера."""

        result = self._invoke(
            [
                "docker",
                "container",
                "ls",
                "--all",
                "--no-trunc",
                "--filter",
                f"id={container_id}",
                "--format",
                "{{.ID}}",
            ],
            timeout=CLEANUP_TIMEOUT,
        )

        if (
            result.returncode != 0
            or result.stdout.strip()
        ):

            raise VerificationError(
                "Database readiness container cleanup "
                "could not be confirmed."
            )

    def _cleanup_probe(
        self,
        name: str,
        owner_token: str,
    ) -> None:
        """Удаляет только подтверждённый SQL Probe."""

        container_id = self._lookup_probe(
            name
        )

        if container_id is None:

            return

        self._verify_probe_owner(
            container_id,
            owner_token,
        )

        self._invoke(
            [
                "docker",
                "rm",
                "--force",
                container_id,
            ],
            timeout=CLEANUP_TIMEOUT,
        )

        # --rm может удалить контейнер раньше
        # явной операции удаления.
        #
        # Авторитетная проверка —
        # подтверждённое отсутствие ID.

        self._verify_probe_absent(
            container_id
        )

    def _build_arguments(
        self,
        *,
        name: str,
        owner_token: str,
        image: str,
        environment: dict[str, str],
    ) -> list[str]:
        """Формирует изолированный вызов SQL Probe."""

        arguments = [
            "docker",
            "run",

            "--rm",

            "--name",
            name,

            "--label",
            f"{PROBE_OWNER_LABEL}={owner_token}",

            "--network",
            self.config.network_name,

            "--read-only",

            "--cap-drop",
            "ALL",

            "--security-opt",
            "no-new-privileges",

            "--user",
            (
                f"{self.config.apache_uid}:"
                f"{self.config.apache_gid}"
            ),
        ]

        for variable in environment:

            arguments.extend([
                "--env",
                variable,
            ])

        arguments.extend([
            "--entrypoint",
            "php",

            image,

            "-d",
            "display_errors=0",

            "-d",
            "display_startup_errors=0",

            "-d",
            "log_errors=0",

            "-r",
            DATABASE_READINESS_CODE,
        ])

        return arguments

    def execute(
        self,
        environment: dict[str, str],
    ) -> subprocess.CompletedProcess[str]:
        """Исполняет независимый SQL Probe."""

        image = self._resolve_source_image()

        name = self._probe_name()

        owner_token = secrets.token_hex(32)

        arguments = self._build_arguments(
            name=name,
            owner_token=owner_token,
            image=image,
            environment=environment,
        )

        try:

            return self._invoke(
                arguments,
                environment=environment,
            )

        finally:

            self._cleanup_probe(
                name,
                owner_token,
            )