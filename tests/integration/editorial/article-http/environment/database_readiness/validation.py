"""Проверка результата независимой SQL-аутентификации."""

from __future__ import annotations

import json
import subprocess

from typing import Any

from ..config import EnvironmentConfig

from ..errors import VerificationError

from .config import (
    ALLOWED_ERRORS,
    EXPECTED_RESPONSE_FIELDS,
)


def parse_response(
    result: subprocess.CompletedProcess[str],
) -> dict[str, Any]:
    """Разбирает машинный ответ SQL Probe."""

    try:

        data = json.loads(
            result.stdout
        )

    except json.JSONDecodeError:

        raise VerificationError(
            "Invalid database readiness response."
        ) from None

    if not isinstance(data, dict):

        raise VerificationError(
            "Unexpected database readiness response."
        )

    if result.returncode != 0:

        code = data.get("code")

        if (
            set(data) != {"status", "code"}
            or data.get("status") != "FAILED"
            or not isinstance(code, str)
            or code not in ALLOWED_ERRORS
        ):

            code = "UNCLASSIFIED_PROBE_FAILURE"

        raise VerificationError(
            f"Database readiness rejected: {code}"
        )

    return data


def verify_identity(
    config: EnvironmentConfig,
    data: dict[str, Any],
) -> None:
    """Подтверждает фактическую SQL-идентичность."""

    if set(data) != EXPECTED_RESPONSE_FIELDS:

        raise VerificationError(
            "Unexpected database readiness response fields."
        )

    if data["status"] != "READY":

        raise VerificationError(
            "Database readiness was not confirmed."
        )

    if (
        type(data["connection_ok"]) is not int
        or data["connection_ok"] != 1
    ):

        raise VerificationError(
            "SQL connection was not confirmed."
        )

    if (
        data["database_name"]
        != config.database_name
    ):

        raise VerificationError(
            "Unexpected SQL database identity."
        )

    expected_account = (
        f"{config.database_user}@%"
    )

    if (
        data["authenticated_account"]
        != expected_account
    ):

        raise VerificationError(
            "Unexpected authenticated SQL account."
        )

    connection_account = data[
        "connection_account"
    ]

    if not isinstance(
        connection_account,
        str,
    ):

        raise VerificationError(
            "Invalid SQL connection account."
        )

    username, separator, host = (
        connection_account.rpartition("@")
    )

    if (
        separator != "@"
        or username != config.database_user
        or not host
    ):

        raise VerificationError(
            "Unexpected SQL connection identity."
        )