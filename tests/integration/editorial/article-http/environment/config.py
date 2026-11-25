"""Типизированная конфигурация HTTP Environment.

Нормативная основа:
    ТП-03 — Editorial
    EDC-01 — Article Model
    AM-D03 — Public Visibility
    HTTP-D01 — Environment

Ответственность:
    - определение параметров Docker-окружения;
    - определение параметров тестовой базы;
    - определение файловых путей;
    - определение сетевых параметров;
    - определение требований изоляции;
    - определение нормативной маршрутизации;
    - определение ожидаемой идентичности WordPress.

Конфигурация является неизменяемой.

Компонент не выполняет операций с Docker,
WordPress или базой данных.
"""

from dataclasses import dataclass
from pathlib import Path


@dataclass(frozen=True)
class EnvironmentConfig:
    """Нормативные параметры испытательного окружения."""

    # --------------------------------------------------
    # 1. Расположение испытательной инфраструктуры
    # --------------------------------------------------

    http_directory: Path

    # --------------------------------------------------
    # 2. Docker Environment
    # --------------------------------------------------

    container_name: str = "dilitant_land_http_test"

    source_container: str = "dilitant_land_wp"

    database_container: str = "dilitant_land_db"

    network_name: str = (
        "dilitant_land_dilitant_land_network"
    )

    # --------------------------------------------------
    # 3. Изолированная тестовая база
    # --------------------------------------------------

    database_name: str = "dilitant_land_test"

    database_user: str = "dilitant_test"

    database_host: str = "dilitant_land_db:3306"

    table_prefix: str = "wp_test_"

    # --------------------------------------------------
    # 4. Конфигурация WordPress
    # --------------------------------------------------

    theme_name: str = "dilitant-land"

    # Нормативная структура постоянных ссылок.
    #
    # Соответствует структуре рабочего WordPress.
    #
    # Правила rewrite должны генерироваться
    # действительным WordPress в тестовой БД.
    #
    # Копирование сериализованных правил
    # из рабочей базы не допускается.

    permalink_structure: str = "/%postname%/"

    # --------------------------------------------------
    # 5. Сетевые параметры
    # --------------------------------------------------

    host: str = "127.0.0.1"

    port: int = 8095

    # --------------------------------------------------
    # 6. Идентификация испытательного контейнера
    # --------------------------------------------------

    owner_label: str = (
        "org.dilitant.editorial.http-test"
    )

    owner_value: str = "am-d03"

    # --------------------------------------------------
    # 7. Файловая система контейнера
    # --------------------------------------------------

    wordpress_target: str = "/var/www/html"

    tests_target: str = "/tests"

    private_storage_target: str = (
        "/var/www/dilitant-private"
    )

    # --------------------------------------------------
    # 8. Пользователь Apache
    # --------------------------------------------------

    apache_user: str = "www-data"

    apache_uid: int = 33

    apache_gid: int = 33

    # --------------------------------------------------
    # 9. Ограничения времени исполнения
    # --------------------------------------------------

    startup_timeout: float = 45.0

    request_timeout: float = 5.0

    command_timeout: float = 60.0

    # --------------------------------------------------
    # 10. Разрешение файловых путей
    # --------------------------------------------------

    @property
    def project_root(self) -> Path:
        """Возвращает корневой каталог проекта."""

        return self.http_directory.parents[3]

    @property
    def wordpress_source(self) -> Path:
        """Возвращает исходный каталог WordPress."""

        return (
            self.project_root
            / "src"
            / "wordpress"
        )

    @property
    def tests_source(self) -> Path:
        """Возвращает каталог испытательной инфраструктуры."""

        return (
            self.project_root
            / "tests"
        )

    @property
    def wordpress_config(self) -> Path:
        """Возвращает тестовый wp-config.php."""

        return (
            self.http_directory
            / "wp-config.php"
        )

    @property
    def wordpress_config_target(self) -> str:
        """Возвращает путь конфигурации внутри контейнера."""

        return (
            f"{self.wordpress_target}/wp-config.php"
        )

    # --------------------------------------------------
    # 11. HTTP-адрес
    # --------------------------------------------------

    @property
    def base_url(self) -> str:
        """Возвращает базовый HTTP-адрес стенда."""

        return (
            f"http://{self.host}:{self.port}"
        )

    # --------------------------------------------------
    # 12. Переменные окружения
    # --------------------------------------------------

    @property
    def required_environment(self) -> dict[str, str]:
        """Обязательные значения тестового окружения."""

        return {
            "DILITANT_TEST_MODE":
                "1",

            "DILITANT_TEST_DB_NAME":
                self.database_name,

            "DILITANT_TEST_DB_USER":
                self.database_user,

            "DILITANT_TEST_DB_HOST":
                self.database_host,
        }

    @property
    def password_variable(self) -> str:
        """Имя переменной с паролем тестовой базы.

        Значение пароля не является частью
        сохраняемой конфигурации.
        """

        return "DILITANT_TEST_DB_PASSWORD"

    # --------------------------------------------------
    # 13. Нормативные монтирования
    # --------------------------------------------------

    @property
    def expected_mounts(self) -> dict[str, Path]:
        """Возвращает обязательные bind mounts.

        Ключ:
            Путь внутри Docker-контейнера.

        Значение:
            Путь исходного объекта на хосте.

        Все перечисленные монтирования
        должны иметь режим read-only.

        Приватное хранилище подключается
        отдельно в режиме tmpfs.
        """

        return {
            self.wordpress_target:
                self.wordpress_source,

            self.tests_target:
                self.tests_source,

            self.wordpress_config_target:
                self.wordpress_config,
        }

    # --------------------------------------------------
    # 14. Ожидаемая идентичность WordPress
    # --------------------------------------------------

    @property
    def expected_wordpress_identity(
        self,
    ) -> dict[str, str]:
        """Возвращает нормативную идентичность WordPress.

        Используется для независимой проверки
        фактически загруженного окружения.
        """

        return {
            "database":
                self.database_name,

            "prefix":
                self.table_prefix,

            "home":
                self.base_url,

            "siteurl":
                self.base_url,

            "theme":
                self.theme_name,
        }

    # --------------------------------------------------
    # 15. Нормативная маршрутизация Article Model
    # --------------------------------------------------

    @property
    def article_rewrite_rules(self) -> dict[str, str]:
        """Возвращает обязательные правила Article Model.

        Нормативные маршруты:

            /diletant-armenia/articles/

                Каталог статей.

            /diletant-armenia/articles/{slug}/

                Отдельная статья.

        Ключ:
            Регулярное выражение WordPress rewrite rule.

        Значение:
            Внутренний запрос WordPress.

        Ожидаемые правила определяются независимо
        от фактически сформированных rewrite rules.

        Это позволяет обнаружить:

            - отсутствие обязательного правила;
            - изменение регулярного выражения;
            - изменение внутреннего маршрута;
            - ошибку регистрации Article Model.

        Полный перечень правил WordPress
        здесь не фиксируется.

        Дополнительные штатные правила допускаются.
        """

        return {
            # Каталог статей.
            r"^diletant-armenia/articles/?$":
                "index.php?"
                "category_name=diletant-armenia",

            # Отдельная статья.
            r"^diletant-armenia/articles/([^/]+)/?$":
                "index.php?"
                "category_name=diletant-armenia"
                "&name=$matches[1]",
        }


# --------------------------------------------------
# 16. Фабрика конфигурации
# --------------------------------------------------

def create_config() -> EnvironmentConfig:
    """Создаёт конфигурацию относительно расположения пакета.

    Не зависит от текущего рабочего каталога
    пользователя.

    Не создаёт Docker-контейнеры.

    Не подключается к базе данных.

    Не изменяет файловую систему.
    """

    http_directory = (
        Path(__file__)
        .resolve()
        .parent
        .parent
    )

    return EnvironmentConfig(
        http_directory=http_directory,
    )