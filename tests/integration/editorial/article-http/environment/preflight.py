"""Предварительная проверка HTTP Environment."""

from __future__ import annotations

import errno
import math
import os
import shutil
import socket
import sys
import time

from .config import EnvironmentConfig

from .errors import (
    ConfigurationError,
    DependencyError,
    VerificationError,
)

from .process import (
    ProcessRunner,
    report_pass,
)


class EnvironmentPreflight:
    def __init__(
        self,
        config: EnvironmentConfig,
        process: ProcessRunner,
    ) -> None:
        self.config = config
        self.process = process

    def verify_dependencies(self) -> None:
        if sys.version_info < (3, 10):
            raise DependencyError(
                "Python 3.10 or newer is required."
            )

        if shutil.which("docker") is None:
            raise DependencyError(
                "Docker executable is unavailable."
            )

        self.process.run(
            ["docker", "info"],
        )

        report_pass(
            "HTTP test dependencies"
        )

    def verify_environment(self) -> None:
        for name, expected in (
            self.config.required_environment.items()
        ):
            actual = os.environ.get(name)

            if actual != expected:
                raise ConfigurationError(
                    f"Unexpected environment variable: {name}"
                )

        if not os.environ.get(
            self.config.password_variable
        ):
            raise ConfigurationError(
                "Test database password is missing."
            )

        report_pass(
            "HTTP test environment variables"
        )

    def verify_source(self) -> None:
        source = self.config.wordpress_source

        required_files = [
            source / "index.php",
            source / "wp-load.php",
            source / "wp-settings.php",
            source / "wp-config.php",

            source
            / "wp-content"
            / "themes"
            / self.config.theme_name
            / "functions.php",

            self.config.wordpress_config,
        ]

        for path in required_files:
            if not path.is_file():
                raise ConfigurationError(
                    f"Required file is missing: {path}"
                )

            if path.stat().st_size == 0:
                raise ConfigurationError(
                    f"Required file is empty: {path}"
                )

        if not self.config.tests_source.is_dir():
            raise ConfigurationError(
                "Test source directory is missing."
            )

        config_path = self.config.wordpress_config

        if config_path.is_symlink():
            raise ConfigurationError(
                "Test configuration must not be a symlink."
            )

        if not config_path.stat().st_mode & 0o004:
            raise ConfigurationError(
                "Test wp-config.php is not readable "
                "by other users. "
                "Expected readable test configuration."
            )

        report_pass(
            "HTTP test source files"
        )

    def verify_container_running(
        self,
        container_name: str,
    ) -> None:
        running = self.process.output(
            [
                "docker",
                "inspect",
                "--format",
                "{{.State.Running}}",
                container_name,
            ]
        )

        if running != "true":
            raise VerificationError(
                f"Required container is not running: "
                f"{container_name}"
            )

    def verify_docker_environment(self) -> None:
        self.verify_container_running(
            self.config.source_container,
        )

        self.verify_container_running(
            self.config.database_container,
        )

        self.process.run(
            [
                "docker",
                "network",
                "inspect",
                self.config.network_name,
            ]
        )

        report_pass(
            "HTTP test Docker infrastructure"
        )

    def _bind_port(self) -> None:
        with socket.socket(
            socket.AF_INET,
            socket.SOCK_STREAM,
        ) as server:
            server.bind(
                (
                    self.config.host,
                    self.config.port,
                )
            )

    def verify_port(
        self,
        *,
        wait: bool = False,
    ) -> None:
        timeout = self.config.port_release_timeout
        interval = self.config.port_poll_interval

        if (
            not math.isfinite(timeout)
            or not math.isfinite(interval)
            or timeout <= 0
            or interval <= 0
        ):
            raise ConfigurationError(
                "Invalid port release timing configuration."
            )

        address = (
            f"{self.config.host}:"
            f"{self.config.port}"
        )

        deadline = time.monotonic() + (
            timeout if wait else 0.0
        )

        waiting_reported = False

        while True:
            try:
                self._bind_port()

            except OSError as error:
                if error.errno != errno.EADDRINUSE:
                    raise VerificationError(
                        "HTTP test port verification failed: "
                        f"{address}; "
                        f"errno={error.errno}; "
                        f"{error.strerror}"
                    ) from error

                remaining = (
                    deadline - time.monotonic()
                )

                if not wait or remaining <= 0:
                    raise VerificationError(
                        "HTTP test port is unavailable: "
                        f"{address}; "
                        f"errno={error.errno}; "
                        f"{error.strerror}"
                    ) from error

                if not waiting_reported:
                    print(
                        "Waiting for HTTP test port release: "
                        f"{address}",
                        flush=True,
                    )

                    waiting_reported = True

                time.sleep(
                    min(interval, remaining)
                )

            else:
                report_pass(
                    "HTTP test port is available"
                )

                return

    def run(self) -> None:
        self.verify_dependencies()
        self.verify_environment()
        self.verify_source()
        self.verify_docker_environment()
        self.verify_port()