"""Точка запуска полной регрессии HF-M10-V2."""

from __future__ import annotations

import importlib
import importlib.util
import sys

from pathlib import Path
from typing import Callable

from recovery_process import (
    RecoveryConfig,
    RecoveryProcess,
)


BASE = Path(__file__).resolve().parent

PACKAGE_NAME = "regression-tests"

PACKAGE_ROOT = BASE / PACKAGE_NAME

PACKAGE_FILE = PACKAGE_ROOT / "__init__.py"


REQUIRED_FILES = (
    "__init__.py",
    "config.py",
    "commands.py",
    "context.py",
    "cli.py",
    "http.py",
    "lifecycle.py",
    "recovery.py",
    "audit.py",
    "scenarios.py",
    "suite.py",
)


ProcessFactory = Callable[
    [],
    RecoveryProcess,
]


def preflight() -> None:
    missing = [
        str(PACKAGE_ROOT / filename)
        for filename in REQUIRED_FILES
        if not (
            PACKAGE_ROOT / filename
        ).is_file()
    ]

    if missing:
        raise RuntimeError(
            "Missing Fixture Regression components:\n"
            + "\n".join(missing)
        )


def load_package():
    preflight()

    expected = PACKAGE_FILE.resolve()

    existing = sys.modules.get(
        PACKAGE_NAME
    )

    if existing is not None:
        origin = getattr(
            existing,
            "__file__",
            None,
        )

        if (
            not isinstance(origin, str)
            or Path(origin).resolve() != expected
        ):
            raise RuntimeError(
                "Unexpected loaded Fixture Regression "
                "package origin."
            )

        return existing

    if str(BASE) not in sys.path:
        sys.path.insert(
            0,
            str(BASE),
        )

    spec = importlib.util.find_spec(
        PACKAGE_NAME
    )

    if (
        spec is None
        or not isinstance(spec.origin, str)
        or Path(spec.origin).resolve() != expected
        or spec.submodule_search_locations is None
    ):
        raise RuntimeError(
            "Unexpected Fixture Regression "
            "package specification."
        )

    package = importlib.import_module(
        PACKAGE_NAME
    )

    origin = getattr(
        package,
        "__file__",
        None,
    )

    if (
        not isinstance(origin, str)
        or Path(origin).resolve() != expected
    ):
        raise RuntimeError(
            "Unexpected Fixture Regression "
            "package origin."
        )

    return package


def main(
    *,
    config: RecoveryConfig | None = None,
    process: RecoveryProcess | None = None,
    process_factory: ProcessFactory | None = None,
) -> int:
    try:
        package = load_package()

        entry = getattr(
            package,
            "main",
            None,
        )

        if not callable(entry):
            raise RuntimeError(
                "Fixture Regression entry point unavailable."
            )

        result = entry(
            config=config,
            process=process,
            process_factory=process_factory,
        )

        if (
            type(result) is not int
            or result not in {0, 1, 130}
        ):
            raise RuntimeError(
                "Invalid Fixture Regression exit status."
            )

        return result

    except KeyboardInterrupt:
        print(
            "Regression interrupted.",
            file=sys.stderr,
            flush=True,
        )

        return 130

    except Exception as error:
        print(
            "FAIL: Fixture Regression infrastructure: "
            f"{type(error).__name__}",
            file=sys.stderr,
            flush=True,
        )

        return 1


if __name__ == "__main__":
    raise SystemExit(
        main()
    )