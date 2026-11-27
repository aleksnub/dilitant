"""Общий контекст полной регрессии HF-M10-V2."""

from __future__ import annotations

import importlib
import importlib.util
import re
import runpy
import sys

from pathlib import Path
from typing import Any, Callable

from recovery_process import (
    RecoveryConfig,
    RecoveryProcess,
)

from .config import (
    BASE,
    RECOVERY_PROCESS,
    FIXTURE_RECOVERY,
    USER_RECOVERY,
    FIXTURE_ENTRY,
    USER_ENTRY,
    REQUIRED_PATHS,
    REQUIRED_ENVIRONMENT,
    EXPECTED_ENVIRONMENT,
    HTTP_URL,
    TIMEOUT,
)

from .commands import (
    require,
    report,
    execute,
    environment,
    python_command,
    assert_process_configuration,
    require_default_environment,
)

from .cli import resolve_production_image


ProcessFactory = Callable[
    [],
    RecoveryProcess,
]


def verify_environment(
    config: RecoveryConfig,
) -> None:
    require(
        isinstance(config, RecoveryConfig),
        "Invalid Recovery configuration.",
    )

    require_default_environment(
        config
    )

    env = config.env

    for name in REQUIRED_ENVIRONMENT:
        require(
            bool(env.get(name)),
            f"Required environment variable "
            f"missing: {name}",
        )

    for name, expected in EXPECTED_ENVIRONMENT.items():
        require(
            env.get(name) == expected,
            f"Unexpected environment value: {name}",
        )

    for source in REQUIRED_PATHS:
        require(
            source.exists(),
            f"Required path unavailable: {source}",
        )

    for source in (
        config.environment,
        config.manifest,
    ):
        require(
            source.is_file(),
            f"Required executable unavailable: {source}",
        )


def verify_entry_components(
    entry: Path,
) -> None:
    require(
        entry.is_file(),
        f"Entry point unavailable: {entry}",
    )

    namespace = runpy.run_path(
        str(entry)
    )

    preflight = namespace.get(
        "preflight"
    )

    require(
        callable(preflight),
        f"Missing component preflight: {entry.name}",
    )

    preflight()


def load_package(
    name: str,
    directory: Path,
):
    expected = (
        directory / "__init__.py"
    ).resolve()

    require(
        expected.is_file(),
        f"Package entry point unavailable: {name}",
    )

    existing = sys.modules.get(name)

    if existing is not None:
        origin = getattr(
            existing,
            "__file__",
            None,
        )

        require(
            isinstance(origin, str)
            and Path(origin).resolve() == expected,
            f"Unexpected loaded package origin: {name}",
        )

        return existing

    spec = importlib.util.find_spec(name)

    require(
        spec is not None
        and isinstance(spec.origin, str)
        and Path(spec.origin).resolve() == expected
        and spec.submodule_search_locations is not None,
        f"Unexpected package specification: {name}",
    )

    package = importlib.import_module(name)

    origin = getattr(
        package,
        "__file__",
        None,
    )

    require(
        isinstance(origin, str)
        and Path(origin).resolve() == expected,
        f"Unexpected package origin: {name}",
    )

    return package


def assert_no_historical_import_path() -> None:
    historical_root = (
        BASE / "recovery-tests"
    ).resolve()

    require(
        not any(
            Path(entry).resolve() == historical_root
            for entry in sys.path
        ),
        "Historical Recovery directory in "
        "Python search path.",
    )


class RegressionContext:
    """Владелец единого контекста регрессии.

    Fixture Recovery и User Recovery используют:

        - один RecoveryProcess;
        - одну RecoveryConfig;
        - общий SQL baseline;
        - общий baseline администратора;
        - общие множества идентификаторов.
    """

    def __init__(
        self,
        *,
        config: RecoveryConfig | None = None,
        process: RecoveryProcess | None = None,
        process_factory: ProcessFactory | None = None,
    ) -> None:
        require(
            sum(
                value is not None
                for value in (
                    config,
                    process,
                    process_factory,
                )
            ) <= 1,
            "Specify either RecoveryConfig, "
            "RecoveryProcess or process factory.",
        )

        require(
            config is None
            or isinstance(config, RecoveryConfig),
            "Invalid Recovery configuration.",
        )

        require(
            process is None
            or type(process) is RecoveryProcess,
            "Unexpected RecoveryProcess class.",
        )

        require(
            process_factory is None
            or callable(process_factory),
            "Invalid RecoveryProcess factory.",
        )

        assert_no_historical_import_path()

        if str(BASE) not in sys.path:
            sys.path.insert(
                0,
                str(BASE),
            )

        self._recovery_package = load_package(
            "recovery_process",
            RECOVERY_PROCESS,
        )

        require(
            self._recovery_package.RecoveryProcess
            is RecoveryProcess,
            "Native RecoveryProcess class mismatch.",
        )

        require(
            self._recovery_package.RecoveryConfig
            is RecoveryConfig,
            "Native RecoveryConfig class mismatch.",
        )

        if process_factory is not None:
            process = process_factory()

            require(
                type(process) is RecoveryProcess,
                "Unexpected RecoveryProcess factory result.",
            )

        if process is None:
            if config is None:
                config = RecoveryConfig()

            verify_environment(
                config
            )

            process = RecoveryProcess(
                config=config,
            )

        else:
            config = process.config

            verify_environment(
                config
            )

        self._config = config

        assert_process_configuration(
            process,
            self._config,
        )

        verify_entry_components(
            FIXTURE_ENTRY
        )

        verify_entry_components(
            USER_ENTRY
        )

        if str(USER_RECOVERY) not in sys.path:
            sys.path.insert(
                0,
                str(USER_RECOVERY),
            )

        self.user_package = load_package(
            "suite",
            USER_RECOVERY / "suite",
        )

        self.user_scenarios = load_package(
            "scenarios",
            USER_RECOVERY / "scenarios",
        )

        self.fixture_package = load_package(
            "fixture-recovery-v2",
            FIXTURE_RECOVERY,
        )

        assert_no_historical_import_path()

        self.fixture_suite = (
            self.fixture_package.FixtureRecoverySuite(
                process=process,
            )
        )

        require(
            self.fixture_suite._process is process,
            "Fixture Recovery executor identity changed.",
        )

        require(
            self.fixture_suite._module
            is self._recovery_package,
            "Fixture Recovery module identity changed.",
        )

        self.user_suite = None

        self.fixture_suite.preflight()

        self.initial_manifest = self.manifest(
            "status"
        )

        require(
            self.initial_manifest["schema_version"] == 2
            and self.initial_manifest["state"] == "CLEANED",
            "Cleaned Manifest V2 required.",
        )

        self.production_image = resolve_production_image(
            self
        )

        self.assert_cleaned()

        print(
            "PASS: Regression V2 preflight",
            flush=True,
        )

    @property
    def config(self) -> RecoveryConfig:
        return self._config

    @property
    def process(self) -> RecoveryProcess:
        return self.fixture_suite._process

    @property
    def http_url(self) -> str:
        identity = self.config.isolation

        if identity is None:
            return HTTP_URL

        return (
            f"http://127.0.0.1:"
            f"{identity.http_port}"
        )

    @property
    def user_ids(self) -> set[int]:
        return self.fixture_suite._user_ids

    @property
    def post_ids(self) -> set[int]:
        return self.fixture_suite._post_ids

    @property
    def run_ids(self) -> set[str]:
        return self.fixture_suite._run_ids

    def assert_configuration(self) -> None:
        assert_process_configuration(
            self.process,
            self.config,
        )

    def assert_shared_context(self) -> None:
        self.assert_configuration()

        require(
            self.fixture_suite._module
            is self._recovery_package,
            "Fixture Recovery module identity changed.",
        )

        assert_no_historical_import_path()

        if self.user_suite is None:
            return

        require(
            self.user_suite._process is self.process,
            "Shared RecoveryProcess identity changed.",
        )

        require(
            self.user_suite._module
            is self._recovery_package,
            "User Recovery module identity changed.",
        )

        require(
            self.user_suite._baseline
            is self.fixture_suite._baseline,
            "Shared database baseline changed.",
        )

        require(
            self.user_suite._protected
            is self.fixture_suite._protected,
            "Shared administrator baseline changed.",
        )

        require(
            self.user_suite._user_ids is self.user_ids
            and self.user_suite._post_ids is self.post_ids
            and self.user_suite._run_ids is self.run_ids,
            "Shared Recovery audit registry changed.",
        )

    def report(
        self,
        output: str,
    ) -> None:
        report(
            output,
            self.config,
        )

    def execute(
        self,
        args: list[str],
        *,
        timeout: int = TIMEOUT,
        require_container: bool = True,
    ) -> str:
        self.assert_shared_context()

        return execute(
            self.process,
            self.config,
            args,
            timeout=timeout,
            require_container=require_container,
        )

    def environment(
        self,
        command: str,
    ) -> None:
        self.assert_shared_context()

        environment(
            self.process,
            self.config,
            command,
        )

    def environment_cli(
        self,
        *args: str,
    ) -> str:
        self.assert_shared_context()

        require(
            bool(args),
            "Environment CLI arguments required.",
        )

        return python_command(
            self.process,
            self.config,
            self.config.environment,
            "cli",
            *args,
        )

    def reconnect(self) -> None:
        """Переподключить единый RecoveryProcess.

        Сохраняются конфигурация, окружение,
        baseline и накопленные идентификаторы.

        Все подключённые потребители получают
        один новый экземпляр RecoveryProcess.
        """

        self.assert_shared_context()

        previous = self.process

        process = RecoveryProcess(
            config=self.config,
        )

        assert_process_configuration(
            process,
            self.config,
        )

        require(
            process is not previous,
            "RecoveryProcess was not recreated.",
        )

        process.environment(
            "check"
        )

        process.verify_restart_readiness()

        self.fixture_suite._process = process

        if self.user_suite is not None:
            self.user_suite._process = process

        self.assert_shared_context()

        print(
            "PASS: RecoveryProcess reconnected",
            flush=True,
        )

    def attach_user_suite(self):
        """Подключить User Recovery к общему контексту."""

        require(
            self.user_suite is None,
            "User Recovery Suite already attached.",
        )

        self.assert_cleaned()

        process = self.process

        suite = self.user_package.UserRecoverySuite(
            process=process,
        )

        require(
            suite._process is process,
            "User Recovery did not retain shared executor.",
        )

        require(
            suite._module is self._recovery_package,
            "User Recovery module identity mismatch.",
        )

        assert_process_configuration(
            suite._process,
            self.config,
        )

        suite.preflight()

        require(
            suite._process is process,
            "User Recovery changed executor during preflight.",
        )

        require(
            suite._baseline
            == self.fixture_suite._baseline,
            "User Recovery database baseline mismatch.",
        )

        require(
            suite._protected
            == self.fixture_suite._protected,
            "Protected administrator baseline mismatch.",
        )

        self.user_ids.update(
            suite._user_ids
        )

        self.post_ids.update(
            suite._post_ids
        )

        self.run_ids.update(
            suite._run_ids
        )

        suite._user_ids = self.user_ids
        suite._post_ids = self.post_ids
        suite._run_ids = self.run_ids

        suite._baseline = self.fixture_suite._baseline

        suite._protected = self.fixture_suite._protected

        self.user_suite = suite

        self.assert_shared_context()

        print(
            "PASS: User Recovery shared audit context",
            flush=True,
        )

        return suite

    def manifest(
        self,
        command: str,
    ) -> dict[str, Any]:
        self.assert_shared_context()

        return self.fixture_suite.manifest(
            command
        )

    def assert_manifest(
        self,
        expected: dict[str, Any],
    ) -> None:
        require(
            self.manifest("status") == expected,
            "Persistent manifest changed unexpectedly.",
        )

        require(
            self.fixture_suite.worker("manifest")
            == expected,
            "Independent manifest reading mismatch.",
        )

    def assert_baseline(self) -> None:
        self.assert_shared_context()

        self.fixture_suite.assert_baseline()

    def assert_cleaned(self) -> dict[str, Any]:
        manifest = self.manifest(
            "status"
        )

        require(
            manifest["schema_version"] == 2
            and manifest["state"] == "CLEANED"
            and all(
                record["id"] > 0
                for record in manifest["objects"]
            ),
            "Expected CLEANED Manifest V2.",
        )

        self.fixture_suite._remember_manifest(
            manifest
        )

        self.fixture_suite.assert_empty_fixture_probe(
            self.fixture_suite.fixture_probe(
                manifest["run_id"]
            )
        )

        self.assert_manifest(
            manifest
        )

        self.assert_baseline()

        return manifest