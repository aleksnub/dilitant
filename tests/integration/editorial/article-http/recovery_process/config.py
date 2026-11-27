"""Конфигурация RecoveryProcess V2."""

from __future__ import annotations

import os
import re

from dataclasses import dataclass, field
from pathlib import Path
from types import MappingProxyType
from typing import Mapping


BASE = Path(__file__).resolve().parent.parent

ENVIRONMENT = BASE / "environment.py"
MANIFEST = BASE / "manifest.py"

WORKER = (
    "/tests/integration/editorial/"
    "article-http/user-recovery-tests/worker.php"
)

CONTAINER_NAME = "dilitant_land_http_test"

COMMAND_TIMEOUT = 150
CHECKPOINT_TIMEOUT = 45

DATABASE_PASSWORD_VARIABLE = (
    "DILITANT_TEST_DB_PASSWORD"
)

SCHEMA_VERSION = 2
DATABASE = "dilitant_land_test"
TABLE_PREFIX = "wp_test_"

STATES = frozenset({
    "CREATING",
    "READY",
    "TESTING",
    "FAILED",
    "CLEANING",
    "CLEANUP_FAILED",
    "CLEANED",
})

SLOTS = (
    "administrator",
    "subscriber",
    "author_a",
    "author_b",
)

LIMITS = {
    "user": 4,
    "category": 4,
    "post": 10,
}

MANIFEST_COMMANDS = frozenset({
    "create-v2",
    "status",
    "verify",
    "cleanup",
})


@dataclass(frozen=True, slots=True)
class IsolationIdentity:
    database_server_id: str
    http_port: int

    def __post_init__(self) -> None:
        if (
            not isinstance(
                self.database_server_id,
                str,
            )
            or re.fullmatch(
                r"[a-z0-9][a-z0-9._:-]{0,127}",
                self.database_server_id,
            ) is None
        ):
            raise ValueError(
                "Invalid database server identity."
            )

        if (
            type(self.http_port) is not int
            or not 1 <= self.http_port <= 65535
        ):
            raise ValueError(
                "Invalid HTTP port."
            )


@dataclass(frozen=True, slots=True)
class RecoveryConfig:
    executor_id: str = "default"

    container_name: str = CONTAINER_NAME

    worker: str = WORKER

    environment: Path = ENVIRONMENT
    manifest: Path = MANIFEST

    command_timeout: int = COMMAND_TIMEOUT
    checkpoint_timeout: int = CHECKPOINT_TIMEOUT

    worker_timeout: int = 90
    worker_kill_timeout: int = 5
    termination_timeout: int = 20

    isolation: IsolationIdentity | None = None

    isolation_command: tuple[str, ...] = ()
    readiness_command: tuple[str, ...] = ()

    env: Mapping[str, str] = field(
        default_factory=lambda: dict(os.environ),
        repr=False,
        compare=False,
        hash=False,
    )

    def __post_init__(self) -> None:
        self._validate_identifiers()
        self._validate_timeouts()
        self._normalize_paths()
        self._normalize_environment()
        self._validate_isolation()

    def _validate_identifiers(self) -> None:
        if (
            not isinstance(self.executor_id, str)
            or re.fullmatch(
                r"[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}",
                self.executor_id,
            ) is None
        ):
            raise ValueError(
                "Invalid Recovery executor ID."
            )

        if (
            not isinstance(self.container_name, str)
            or re.fullmatch(
                r"dilitant_land_http_test(?:_[a-zA-Z0-9_-]+)?",
                self.container_name,
            ) is None
        ):
            raise ValueError(
                "Invalid test container name."
            )

        if (
            not isinstance(self.worker, str)
            or re.fullmatch(
                r"/tests/integration/editorial/article-http/"
                r"[a-zA-Z0-9_./-]+\.php",
                self.worker,
            ) is None
            or ".." in self.worker.split("/")
        ):
            raise ValueError(
                "Invalid Recovery PHP Worker."
            )

    def _validate_timeouts(self) -> None:
        values = (
            self.command_timeout,
            self.checkpoint_timeout,
            self.worker_timeout,
            self.worker_kill_timeout,
            self.termination_timeout,
        )

        if not all(
            type(value) is int and value > 0
            for value in values
        ):
            raise ValueError(
                "Invalid Recovery timeout."
            )

        if self.checkpoint_timeout >= self.worker_timeout:
            raise ValueError(
                "Checkpoint timeout exceeds Worker lifetime."
            )

    def _normalize_paths(self) -> None:
        object.__setattr__(
            self,
            "environment",
            Path(self.environment).resolve(),
        )

        object.__setattr__(
            self,
            "manifest",
            Path(self.manifest).resolve(),
        )

    def _normalize_environment(self) -> None:
        values = dict(self.env)

        if not all(
            isinstance(key, str)
            and isinstance(value, str)
            and key
            and "=" not in key
            and "\0" not in key
            and "\0" not in value
            for key, value in values.items()
        ):
            raise ValueError(
                "Invalid Recovery process environment."
            )

        object.__setattr__(
            self,
            "env",
            MappingProxyType(values),
        )

    def _validate_isolation(self) -> None:
        for command in (
            self.isolation_command,
            self.readiness_command,
        ):
            if (
                not isinstance(command, tuple)
                or not all(
                    isinstance(value, str)
                    and value
                    and "\0" not in value
                    for value in command
                )
            ):
                raise ValueError(
                    "Invalid Recovery verification command."
                )

        if self.isolation is None:
            if (
                self.container_name != CONTAINER_NAME
                or self.environment != ENVIRONMENT.resolve()
                or self.manifest != MANIFEST.resolve()
                or self.isolation_command
                or self.readiness_command
            ):
                raise ValueError(
                    "Non-default environment requires isolation."
                )

            return

        if not isinstance(
            self.isolation,
            IsolationIdentity,
        ):
            raise ValueError(
                "Invalid Recovery isolation configuration."
            )

        if (
            not self.isolation_command
            or not self.readiness_command
        ):
            raise ValueError(
                "Independent verification commands required."
            )

        if (
            self.container_name == CONTAINER_NAME
            or self.environment == ENVIRONMENT.resolve()
            or self.manifest == MANIFEST.resolve()
        ):
            raise ValueError(
                "Isolated executor requires dedicated endpoints."
            )

    @property
    def is_isolated(self) -> bool:
        return self.isolation is not None