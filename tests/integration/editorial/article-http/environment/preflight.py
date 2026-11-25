"""Предварительная проверка HTTP Environment."""

from __future__ import annotations

import os
import shutil
import socket
import sys

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
    """Проверяет предусловия создания HTTP-стенда."""

    def __init__(
        self,
        config: EnvironmentConfig,
        process: ProcessRunner,
    ) -> None:
        self.config = config
        self.process = process

    def verify_dependencies(self) -> None:
        """Проверяет Python и Docker."""

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
        """Проверяет обязательные параметры тестовой БД."""

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
        """Проверяет исходные файлы WordPress и стенда."""

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

        # Ранее обнаруженный дефект:
        # wp-config.php имел режим 0600
        # и был недоступен www-data.
        #
        # Тестовая конфигурация не содержит
        # сохранённых секретов. Требуем
        # возможность чтения обычным пользователем.
        #
        # Фактический доступ дополнительно
        # проверяется внутри контейнера.

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
        """Проверяет состояние необходимого контейнера."""

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
        """Проверяет существующую Docker-инфраструктуру."""

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

    def verify_port(self) -> None:
        """Проверяет возможность занятия локального порта."""

        with socket.socket(
            socket.AF_INET,
            socket.SOCK_STREAM,
        ) as server:
            try:
                server.bind(
                    (
                        self.config.host,
                        self.config.port,
                    )
                )

            except OSError as error:
                raise VerificationError(
                    "HTTP test port is unavailable: "
                    f"{self.config.host}:{self.config.port}"
                ) from error

        report_pass(
            "HTTP test port is available"
        )

    def run(self) -> None:
        """Исполняет предварительные проверки."""

        self.verify_dependencies()

        self.verify_environment()

        self.verify_source()

        self.verify_docker_environment()

        self.verify_port()