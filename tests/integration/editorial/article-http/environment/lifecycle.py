"""Управление жизненным циклом HTTP Environment.

Нормативная основа:
    ТП-03 — Editorial
    EDC-01 — Article Model
    AM-D03 — Public Visibility
    HTTP-D01 — Environment

Ответственность:
    - предварительная проверка окружения;
    - создание испытательного контейнера;
    - запуск WordPress и Apache;
    - проверка изоляции окружения;
    - инициализация маршрутизации WordPress;
    - независимая проверка маршрутизации;
    - окончательная приёмка HTTP-стенда;
    - диагностическая отчётность;
    - управление жизненным циклом;
    - очистка ресурсов при отказе.

Компонент координирует специализированные
исполнители и не реализует самостоятельно
низкоуровневые операции Docker и WordPress.

Производственное окружение не изменяется.
"""

from __future__ import annotations

import sys
import tempfile

from pathlib import Path

from .config import EnvironmentConfig

from .docker import DockerEnvironment

from .errors import (
    CleanupError,
    ContainerError,
    EnvironmentError,
)

from .preflight import EnvironmentPreflight

from .process import (
    ProcessRunner,
    report_failure,
    report_pass,
)

from .routing import WordPressRouting

from .verification import EnvironmentVerifier


class EnvironmentLifecycle:
    """Координатор жизненного цикла HTTP-стенда."""

    # --------------------------------------------------
    # 1. Инициализация компонентов
    # --------------------------------------------------

    def __init__(
        self,
        config: EnvironmentConfig,
    ) -> None:
        self.config = config

        self.process = ProcessRunner(
            default_timeout=config.command_timeout,
        )

        self.preflight = EnvironmentPreflight(
            config=config,
            process=self.process,
        )

        self.docker = DockerEnvironment(
            config=config,
            process=self.process,
        )

        self.verifier = EnvironmentVerifier(
            config=config,
            docker=self.docker,
        )

        self.routing = WordPressRouting(
            config=config,
            docker=self.docker,
        )

    # --------------------------------------------------
    # 2. Разрешение созданного контейнера
    # --------------------------------------------------

    def _read_created_container(
        self,
        cid_file: Path,
    ) -> str | None:
        """Разрешает контейнер, созданный данным запуском.

        Идентификатор извлекается исключительно
        из CID-файла текущей операции создания.

        Принадлежность контейнера должна быть
        подтверждена независимо.
        """

        if not cid_file.exists():
            return None

        container_id = (
            cid_file
            .read_text(encoding="utf-8")
            .strip()
        )

        self.docker.validate_id(
            container_id,
        )

        self.docker.verify_owner(
            container_id,
        )

        return container_id

    # --------------------------------------------------
    # 3. Диагностические журналы
    # --------------------------------------------------

    def _show_startup_logs(
        self,
        container_id: str,
    ) -> None:
        """Выводит журналы перед удалением контейнера.

        Ошибка получения журналов не должна
        препятствовать очистке контейнера.
        """

        print(
            "\n=== HTTP TEST CONTAINER LOGS ===",
            file=sys.stderr,
            flush=True,
        )

        try:
            logs = self.docker.logs(
                container_id,
                tail=200,
            )

        except Exception as error:
            report_failure(
                f"Unable to read container logs: {error}"
            )

            return

        if logs:
            print(
                logs,
                file=sys.stderr,
                flush=True,
            )

        else:
            print(
                "Container logs are empty.",
                file=sys.stderr,
                flush=True,
            )

    # --------------------------------------------------
    # 4. Очистка ресурсов неудачного запуска
    # --------------------------------------------------

    def _cleanup_failed_start(
        self,
        container_id: str | None,
        cid_file: Path,
    ) -> None:
        """Удаляет ресурсы неудачного запуска.

        Допускается удаление исключительно
        контейнера, созданного текущей операцией
        запуска и имеющего подтверждённую
        принадлежность HTTP-стенду.
        """

        if container_id is None:
            container_id = self._read_created_container(
                cid_file,
            )

        if container_id is None:
            # Без подтверждённого идентификатора
            # не удаляем контейнер только по имени.
            #
            # Если контейнер с зарезервированным
            # именем появился, явно сообщаем о
            # неопределённом результате создания.

            if self.docker.find_container_id() is not None:
                raise CleanupError(
                    "A container exists, but ownership "
                    "by the current startup is unconfirmed."
                )

            return

        self._show_startup_logs(
            container_id,
        )

        print(
            "\n=== CONTAINER CLEANUP ===",
            file=sys.stderr,
            flush=True,
        )

        self.docker.remove(
            container_id,
        )

        report_pass(
            "Failed HTTP startup container removed"
        )

    # --------------------------------------------------
    # 5. Проверка изоляции перед инициализацией
    # --------------------------------------------------

    def _verify_initialization_environment(
        self,
        container_id: str,
    ) -> None:
        """Проверяет окружение перед изменением тестовой БД.

        Инициализация маршрутизации допускается
        только после подтверждения:

            - принадлежности контейнера;
            - работоспособности контейнера;
            - read-only монтирования WordPress;
            - read-only монтирования испытаний;
            - изоляции wp-config.php;
            - временного приватного хранилища;
            - локальной публикации HTTP-порта;
            - ожидаемой Docker Network;
            - работоспособности Apache;
            - идентичности загруженного WordPress;
            - использования тестовой базы.

        Метод не изменяет конфигурацию WordPress.
        """

        self.docker.verify_owner(
            container_id,
        )

        if not self.docker.is_running(
            container_id,
        ):
            raise ContainerError(
                "HTTP test container is not running."
            )

        self.verifier.verify_mounts(
            container_id,
        )

        self.verifier.verify_private_storage(
            container_id,
        )

        self.verifier.verify_port_binding(
            container_id,
        )

        self.verifier.verify_network(
            container_id,
        )

        self.verifier.verify_apache(
            container_id,
        )

        self.verifier.verify_wordpress(
            container_id,
        )

        report_pass(
            "HTTP initialization environment isolation"
        )

    # --------------------------------------------------
    # 6. Инициализация маршрутизации WordPress
    # --------------------------------------------------

    def _initialize_routing(
        self,
        container_id: str,
    ) -> None:
        """Настраивает и независимо проверяет маршрутизацию.

        Предусловие:
            Изоляция испытательного окружения
            подтверждена.

        Последовательность:

            1. Настройка постоянных ссылок.
            2. Генерация действительных rewrite rules.
            3. Сохранение правил в тестовой БД.
            4. Независимое чтение сохранённых правил.
            5. Проверка обязательных маршрутов Article Model.

        Инициализация не изменяет .htaccess
        и производственные файлы WordPress.
        """

        print(
            "\nInitializing WordPress routing...",
            flush=True,
        )

        self.routing.initialize(
            container_id,
        )

        self.routing.verify(
            container_id,
        )

        report_pass(
            "HTTP test WordPress routing initialized"
        )

    # --------------------------------------------------
    # 7. Создание и запуск HTTP-стенда
    # --------------------------------------------------

    def start(self) -> None:
        """Создаёт, запускает и проверяет HTTP-стенд.

        Нормативная последовательность:

            1. Проверка зависимостей.
            2. Проверка переменных окружения.
            3. Проверка исходных файлов.
            4. Проверка Docker-инфраструктуры.
            5. Проверка отсутствия контейнера.
            6. Проверка доступности порта.
            7. Создание контейнера.
            8. Запуск контейнера.
            9. Проверка готовности WordPress.
           10. Проверка изоляции окружения.
           11. Инициализация маршрутизации.
           12. Независимая проверка правил.
           13. Окончательная HTTP-приёмка.

        При отказе на любом этапе после создания
        контейнера выполняются диагностика
        и автоматическая очистка ресурсов.
        """

        # ----------------------------------------------
        # 7.1. Предварительные проверки
        # ----------------------------------------------

        self.preflight.verify_dependencies()

        self.preflight.verify_environment()

        self.preflight.verify_source()

        self.preflight.verify_docker_environment()

        # ----------------------------------------------
        # 7.2. Проверка отсутствия контейнера
        # ----------------------------------------------

        if self.docker.find_container_id() is not None:
            raise ContainerError(
                "HTTP test container already exists. "
                "Use status, check or stop."
            )

        # ----------------------------------------------
        # 7.3. Проверка доступности порта
        # ----------------------------------------------

        self.preflight.verify_port()

        print(
            "\nStarting HTTP test container...",
            flush=True,
        )

        # ----------------------------------------------
        # 7.4. Управление ресурсами запуска
        # ----------------------------------------------

        with tempfile.TemporaryDirectory(
            prefix="dilitant-http-",
        ) as directory:

            cid_file = (
                Path(directory)
                / "container.id"
            )

            container_id = None

            try:
                # --------------------------------------
                # 7.5. Создание контейнера
                # --------------------------------------

                container_id = self.docker.create(
                    cid_file,
                )

                # --------------------------------------
                # 7.6. Запуск контейнера
                # --------------------------------------

                self.docker.start(
                    container_id,
                )

                # --------------------------------------
                # 7.7. Проверка готовности WordPress
                # --------------------------------------
                #
                # На данном этапе проверяется
                # доступность главной страницы.
                #
                # Корректная обработка отсутствующих
                # маршрутов ещё не требуется:
                # постоянные ссылки могут быть
                # не инициализированы.

                self.verifier.wait_ready(
                    container_id,
                )

                # --------------------------------------
                # 7.8. Проверка изоляции окружения
                # --------------------------------------
                #
                # Выполняется до любых изменений
                # параметров тестовой базы.

                self._verify_initialization_environment(
                    container_id,
                )

                # --------------------------------------
                # 7.9. Инициализация маршрутизации
                # --------------------------------------
                #
                # Постоянные ссылки и rewrite rules
                # формируются действительным WordPress.
                #
                # Правила сохраняются исключительно
                # в тестовой базе данных.
                #
                # После сохранения выполняется
                # независимая проверка.

                self._initialize_routing(
                    container_id,
                )

                # --------------------------------------
                # 7.10. Окончательная приёмка
                # --------------------------------------
                #
                # Проверяются:
                #
                #     - изоляция файловой системы;
                #     - изоляция базы данных;
                #     - параметры сети;
                #     - Apache;
                #     - Article Model;
                #     - HTTP 200;
                #     - HTTP 404.
                #
                # HTTP 404 проверяется только после
                # успешной инициализации маршрутизации.

                self.verifier.verify(
                    container_id,
                )

            except BaseException as startup_error:
                # --------------------------------------
                # 7.11. Обработка отказа запуска
                # --------------------------------------
                #
                # KeyboardInterrupt и другие
                # прерывания также требуют
                # очистки созданного контейнера.

                try:
                    self._cleanup_failed_start(
                        container_id,
                        cid_file,
                    )

                except BaseException as cleanup_error:
                    # Ошибка очистки не скрывается.
                    #
                    # Сохраняются сведения
                    # об исходной причине отказа
                    # и причине невозможности очистки.

                    raise CleanupError(
                        "HTTP startup failed and cleanup "
                        "could not be confirmed.\n"
                        f"Startup error: {startup_error}\n"
                        f"Cleanup error: {cleanup_error}"
                    ) from cleanup_error

                raise

        # ----------------------------------------------
        # 7.12. Успешное завершение запуска
        # ----------------------------------------------

        report_pass(
            "HTTP-D01 environment started"
        )

        print(
            f"Container: {self.config.container_name}",
            flush=True,
        )

        print(
            f"URL: {self.config.base_url}",
            flush=True,
        )

    # --------------------------------------------------
    # 8. Независимая проверка существующего стенда
    # --------------------------------------------------

    def check(self) -> None:
        """Проверяет существующий HTTP-стенд.

        Команда выполняет исключительно проверки.

        Принудительная настройка permalink,
        пересоздание rewrite rules и исправление
        конфигурации не выполняются.

        Нарушение нормативной конфигурации
        должно приводить к явному отказу.
        """

        container_id = (
            self.docker.require_container()
        )

        if not self.docker.is_running(
            container_id,
        ):
            raise ContainerError(
                "HTTP test container is not running."
            )

        # ----------------------------------------------
        # 8.1. Проверка изоляции
        # ----------------------------------------------

        self._verify_initialization_environment(
            container_id,
        )

        # ----------------------------------------------
        # 8.2. Проверка сохранённой маршрутизации
        # ----------------------------------------------
        #
        # Не вызываем routing.initialize().
        #
        # Проверяем действительное состояние,
        # а не исправляем его перед проверкой.

        self.routing.verify(
            container_id,
        )

        # ----------------------------------------------
        # 8.3. Полная проверка HTTP-окружения
        # ----------------------------------------------

        self.verifier.verify(
            container_id,
        )

        report_pass(
            "HTTP test environment check completed"
        )

    # --------------------------------------------------
    # 9. Получение состояния контейнера
    # --------------------------------------------------

    def status(self) -> None:
        """Выводит состояние HTTP-контейнера.

        Не создаёт и не удаляет контейнеры.

        Не изменяет конфигурацию WordPress.
        """

        container_id = (
            self.docker.find_container_id()
        )

        if container_id is None:
            print(
                "HTTP test container: absent"
            )

            return

        record = self.docker.verify_owner(
            container_id,
        )

        state = record.get(
            "State",
            {},
        )

        print(
            f"Container: {record.get('Name')}"
        )

        print(
            f"ID: {container_id}"
        )

        print(
            f"Running: {state.get('Running')}"
        )

        print(
            f"Status: {state.get('Status')}"
        )

        print(
            f"Image: {record.get('Image')}"
        )

        print(
            f"URL: {self.config.base_url}"
        )

    # --------------------------------------------------
    # 10. Остановка и удаление контейнера
    # --------------------------------------------------

    def stop(self) -> None:
        """Удаляет существующий испытательный контейнер.

        Перед удалением обязательно проверяется
        принадлежность контейнера HTTP-стенду.

        После удаления независимо проверяется
        отсутствие контейнера.

        Тестовая база данных не удаляется.

        Параметры permalink и rewrite rules,
        сохранённые в тестовой базе,
        остаются доступными при следующем запуске.
        """

        container_id = (
            self.docker.find_container_id()
        )

        if container_id is None:
            report_pass(
                "HTTP test container is already absent"
            )

            return

        self.docker.verify_owner(
            container_id,
        )

        self.docker.remove(
            container_id,
        )

        if self.docker.find_container_id() is not None:
            raise CleanupError(
                "HTTP test container cleanup "
                "could not be confirmed."
            )

        report_pass(
            "HTTP test container removed"
        )

    # --------------------------------------------------
    # 11. Исполнение PHP CLI
    # --------------------------------------------------

    def cli(
        self,
        arguments: list[str],
    ) -> int:
        """Исполняет PHP-команду внутри HTTP-стенда.

        Использует пользователя www-data
        и конфигурацию испытательного WordPress.

        Код завершения PHP передаётся
        вызывающей стороне без подмены.

        В дальнейшем интерфейс используется
        для HTTP-D02:

            - подготовки Fixtures;
            - создания пользователей;
            - формирования сессий;
            - генерации nonce;
            - создания autosave;
            - очистки тестовых объектов.
        """

        if not arguments:
            raise EnvironmentError(
                "PHP arguments are required."
            )

        container_id = (
            self.docker.require_container()
        )

        result = self.docker.php(
            container_id,

            arguments,

            capture=False,

            check=False,
        )

        return result.returncode

    # --------------------------------------------------
    # 12. Получение журналов
    # --------------------------------------------------

    def logs(self) -> None:
        """Выводит журналы существующего HTTP-стенда.

        Принадлежность контейнера проверяется
        до получения диагностической информации.
        """

        container_id = (
            self.docker.require_container()
        )

        logs = self.docker.logs(
            container_id,
            tail=200,
        )

        if logs:
            print(logs)

        else:
            print(
                "Container logs are empty."
            )