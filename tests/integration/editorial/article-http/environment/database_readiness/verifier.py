"""Координатор независимой проверки SQL-аутентификации.

Нормативная основа:
    HTTP-D01 — Environment
    HF-U-D05.2 — User Recovery
    ENV-R05 — ENV-R13
"""

from __future__ import annotations

from ..config import EnvironmentConfig

from .credentials import build_environment

from .transport import DatabaseProbeTransport

from .validation import (
    parse_response,
    verify_identity,
)


class DatabaseReadinessVerifier:
    """Независимая проверка готовности тестовой БД."""

    def __init__(
        self,
        config: EnvironmentConfig,
    ) -> None:

        self.config = config

        self._transport = DatabaseProbeTransport(
            config,
        )

    def verify(self) -> None:
        """Подтверждает готовность SQL-аутентификации.

        Успешное завершение означает:
            - нормативное окружение доступно;
            - учётные данные приняты MariaDB;
            - SQL-соединение установлено;
            - идентичность тестовой БД подтверждена;
            - идентичность SQL-пользователя подтверждена;
            - очистка временного контейнера завершена.

        Проверка не изменяет содержимое БД.
        Результат проверки не кешируется.
        """

        environment = build_environment(
            self.config
        )

        try:

            result = self._transport.execute(
                environment
            )

            data = parse_response(
                result
            )

            verify_identity(
                self.config,
                data,
            )

        finally:

            environment.clear()