"""Проверка учётных данных будущего HTTP-контейнера."""

from __future__ import annotations

import os

from ..config import EnvironmentConfig

from ..errors import ConfigurationError


def build_environment(
    config: EnvironmentConfig,
) -> dict[str, str]:
    """Формирует временное нормативное окружение SQL Probe."""

    environment: dict[str, str] = {}

    for name, expected in (
        config.required_environment.items()
    ):

        actual = os.environ.get(name)

        if actual != expected:

            raise ConfigurationError(
                f"Unexpected environment variable: {name}"
            )

        environment[name] = actual

    password_name = config.password_variable

    password = os.environ.get(
        password_name
    )

    if not password:

        raise ConfigurationError(
            "Test database password is missing."
        )

    environment[password_name] = password

    return environment