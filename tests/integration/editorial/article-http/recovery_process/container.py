"""Управление контейнером, Manifest и PHP Worker."""

from __future__ import annotations

import re
import subprocess
import sys

from contextlib import contextmanager
from functools import wraps
from typing import Any, Iterator

from .config import (
    MANIFEST_COMMANDS,
    RecoveryConfig,
)

from .commands import (
    invoke,
    require,
)

from .isolation import (
    ResourceLease,
    verify_isolation,
)

from .validation import (
    parse_json,
    validate_manifest,
)

from .readiness import (
    verify_restart_environment,
    verify_restart_database,
)


def protected_operation(operation):
    @wraps(operation)
    def execute(self, *args, **kwargs):
        with self.session():
            return operation(
                self,
                *args,
                **kwargs,
            )

    return execute


class ContainerMixin:
    """Операции над индивидуальным окружением."""

    def __init__(
        self,
        config: RecoveryConfig | None = None,
    ) -> None:
        if config is None:
            config = RecoveryConfig()

        require(
            isinstance(config, RecoveryConfig),
            "Invalid Recovery configuration.",
        )

        self._config = config

        self._lease = ResourceLease(
            config
        )

        with self.session():
            self.environment("check")

            self.container_id = (
                self.resolve_container()
            )

            self.assert_container()

    @property
    def config(self) -> RecoveryConfig:
        return self._config

    @contextmanager
    def session(self) -> Iterator[ContainerMixin]:
        with self._lease.hold():
            yield self

    def invoke(
        self,
        args: list[str],
        *,
        check: bool = True,
        timeout: int | None = None,
    ) -> subprocess.CompletedProcess[str]:
        return invoke(
            args,
            check=check,
            timeout=(
                self.config.command_timeout
                if timeout is None
                else timeout
            ),
            env=self.config.env,
        )

    @protected_operation
    def environment(
        self,
        command: str,
    ) -> str:
        require(
            command in {
                "start",
                "stop",
                "check",
            },
            "Unsupported Recovery environment command.",
        )

        require(
            self.config.environment.is_file(),
            "Recovery Environment entry point unavailable.",
        )

        return self.invoke([
            sys.executable,
            "-B",
            str(self.config.environment),
            command,
        ]).stdout

    @protected_operation
    def resolve_container(self) -> str:
        result = self.invoke([
            "docker",
            "inspect",
            "--format",
            "{{.Id}} {{.State.Running}}",
            self.config.container_name,
        ])

        fields = result.stdout.strip().split()

        require(
            len(fields) == 2
            and re.fullmatch(
                r"[0-9a-f]{64}",
                fields[0],
            ) is not None
            and fields[1] == "true",
            "HTTP test container is not running.",
        )

        return fields[0]

    @protected_operation
    def assert_container(self) -> None:
        require(
            self.resolve_container()
            == self.container_id,
            "HTTP test container identity changed.",
        )

        verify_isolation(
            self.config,
            self.container_id,
        )

    @protected_operation
    def verify_restart_readiness(self) -> None:
        self.assert_container()

        verify_restart_environment(
            self.config
        )

        verify_restart_database(
            self.config
        )

        self.assert_container()

    @protected_operation
    def manifest_command(
        self,
        command: str,
        *,
        check: bool = True,
    ) -> subprocess.CompletedProcess[str]:
        require(
            command in MANIFEST_COMMANDS
            or (
                command == "create"
                and not check
            ),
            "Unsupported Manifest V2 command.",
        )

        require(
            self.config.manifest.is_file(),
            "Recovery Manifest entry point unavailable.",
        )

        self.assert_container()

        result = self.invoke(
            [
                sys.executable,
                "-B",
                str(self.config.manifest),
                command,
            ],
            check=check,
        )

        self.assert_container()

        return result

    @protected_operation
    def manifest(
        self,
        command: str,
    ) -> dict[str, Any]:
        result = self.manifest_command(
            command
        )

        data = parse_json(
            result.stdout
        )

        validate_manifest(data)

        return data

    def worker_args(
        self,
        action: str,
        *args: Any,
    ) -> list[str]:
        require(
            isinstance(action, str)
            and bool(action)
            and "\0" not in action,
            "Invalid Recovery Worker action.",
        )

        return [
            "docker",
            "exec",
            "--user",
            "33:33",
            self.container_id,
            "php",
            self.config.worker,
            action,
            *(str(arg) for arg in args),
        ]

    @protected_operation
    def worker_command(
        self,
        action: str,
        *args: Any,
        check: bool = True,
    ) -> subprocess.CompletedProcess[str]:
        self.assert_container()

        result = self.invoke(
            self.worker_args(
                action,
                *args,
            ),
            check=check,
        )

        self.assert_container()

        return result

    @protected_operation
    def worker(
        self,
        action: str,
        *args: Any,
    ) -> dict[str, Any]:
        result = self.worker_command(
            action,
            *args,
        )

        return parse_json(
            result.stdout
        )

    @protected_operation
    def kill_worker(
        self,
        pid: int,
        action: str,
    ) -> None:
        require(
            type(pid) is int
            and pid > 1,
            "Invalid crash worker PID.",
        )

        self.assert_container()

        self.invoke([
            "docker",
            "exec",
            "--user",
            "33:33",
            self.container_id,
            "sh",
            "-ec",
            (
                'test -r "/proc/$1/cmdline"\n'
                'cmd=$(tr "\\000" "\\n" '
                '< "/proc/$1/cmdline")\n'
                'expected=$(printf "%s\\n" php "$2" "$3")\n'
                'test "$cmd" = "$expected"\n'
                'kill -KILL "$1"'
            ),
            "sh",
            str(pid),
            self.config.worker,
            action,
        ])

    @protected_operation
    def kill_container(self) -> None:
        self.verify_restart_readiness()

        self.invoke([
            "docker",
            "kill",
            "--signal",
            "KILL",
            self.container_id,
        ])

        state = self.invoke([
            "docker",
            "inspect",
            "--format",
            "{{.State.Running}} {{.State.ExitCode}}",
            self.container_id,
        ]).stdout.strip()

        require(
            state == "false 137",
            f"Unexpected container crash state: {state}",
        )

    @protected_operation
    def recreate_container(self) -> None:
        previous = self.container_id

        verify_restart_environment(
            self.config
        )

        verify_restart_database(
            self.config
        )

        state = self.invoke([
            "docker",
            "inspect",
            "--format",
            "{{.Id}} {{.State.Running}} {{.State.ExitCode}}",
            self.config.container_name,
        ]).stdout.strip()

        require(
            state == f"{previous} false 137",
            "Unexpected stopped container identity or state.",
        )

        self.environment("stop")
        self.environment("start")
        self.environment("check")

        current = self.resolve_container()

        require(
            current != previous,
            "Container was not recreated.",
        )

        verify_isolation(
            self.config,
            current,
        )

        self.container_id = current

        self.assert_container()

        print(
            "PASS: HTTP test container recreated",
            flush=True,
        )