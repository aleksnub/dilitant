"""Типизированные ошибки HTTP Environment."""


class EnvironmentError(RuntimeError):
    """Базовая ошибка испытательного окружения."""


class ConfigurationError(EnvironmentError):
    """Нарушение требований конфигурации."""


class DependencyError(EnvironmentError):
    """Отсутствие необходимой зависимости."""


class ProcessError(EnvironmentError):
    """Ошибка исполнения внешней программы."""

    def __init__(
        self,
        executable: str,
        returncode: int,
        stderr: str = "",
    ) -> None:
        self.executable = executable
        self.returncode = returncode
        self.stderr = stderr

        message = (
            f"{executable} exited with code {returncode}"
        )

        if stderr:
            message += f"\n{stderr.strip()}"

        super().__init__(message)


class ProcessTimeoutError(EnvironmentError):
    """Превышено время исполнения внешней команды."""


class ContainerError(EnvironmentError):
    """Ошибка Docker-контейнера."""


class ContainerOwnershipError(ContainerError):
    """Принадлежность контейнера не подтверждена."""


class IsolationError(EnvironmentError):
    """Нарушение инварианта изоляции."""


class VerificationError(EnvironmentError):
    """Проверка окружения завершилась отказом."""


class StartupError(EnvironmentError):
    """HTTP-стенд не смог запуститься."""


class CleanupError(EnvironmentError):
    """Не удалось подтвердить очистку ресурсов."""