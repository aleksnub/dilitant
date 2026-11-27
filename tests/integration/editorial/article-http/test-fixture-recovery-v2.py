from __future__ import annotations

import argparse
import importlib
import sys

from pathlib import Path

from recovery_process import (
    RecoveryConfig,
    RecoveryProcess,
)


BASE = Path(__file__).resolve().parent
MODULES = BASE / "fixture-recovery-v2"

REQUIRED_FILES = (
    "__init__.py",
    "config.py",
    "suite.py",
    "audit.py",
    "scenarios.py",
    "support.php",
    "creation.php",
    "cleanup.php",
    "ambiguity.php",
    "sql-failure.php",
)


def preflight() -> None:
    missing = [
        str(MODULES / filename)
        for filename in REQUIRED_FILES
        if not (MODULES / filename).is_file()
    ]

    if missing:
        raise RuntimeError(
            "Missing Fixture Recovery V2 components:\n"
            + "\n".join(missing)
        )


def main(
    *,
    config: RecoveryConfig | None = None,
    process: RecoveryProcess | None = None,
    argv: list[str] | None = None,
) -> int:
    parser = argparse.ArgumentParser()

    parser.add_argument(
        "--case",
        type=int,
        choices=range(1, 11),
    )

    args = parser.parse_args(argv)

    selected = (
        (args.case,)
        if args.case is not None
        else tuple(range(1, 11))
    )

    total = len(selected)
    executed = 0
    passed = 0
    failed = 0
    infrastructure_failed = False

    print(
        "=== HF-M09-V2 FIXTURE RECOVERY ===",
        flush=True,
    )

    try:
        preflight()

        if config is not None and process is not None:
            raise RuntimeError(
                "Specify either RecoveryConfig "
                "or RecoveryProcess."
            )

        if process is None:
            process = RecoveryProcess(
                config=config,
            )

        if type(process) is not RecoveryProcess:
            raise RuntimeError(
                "Unexpected RecoveryProcess class."
            )

        process.assert_container()

        package = importlib.import_module(
            "fixture-recovery-v2"
        )

        suite = package.FixtureRecoverySuite(
            process=process,
        )

        if suite._process is not process:
            raise RuntimeError(
                "Fixture Recovery executor identity changed."
            )

        scenarios = package.build_scenarios(suite)

        if tuple(scenarios) != tuple(range(1, 11)):
            raise RuntimeError(
                "Unexpected Fixture Recovery V2 "
                "scenario registry."
            )

        if not all(
            callable(item)
            for item in scenarios.values()
        ):
            raise RuntimeError(
                "Invalid recovery scenario."
            )

        suite.preflight()

        for number in selected:
            name = f"HF-M09-V2.{number}"

            print(
                f"\n=== {name} "
                f"{package.SCENARIO_NAMES[number]} ===",
                flush=True,
            )

            executed += 1

            try:
                suite.assert_baseline()
                scenarios[number]()
                suite.assert_baseline()

            except Exception as error:
                failed += 1

                print(
                    f"FAIL: {name}\n{error}",
                    file=sys.stderr,
                    flush=True,
                )

                break

            passed += 1

            print(
                f"PASS: {name}",
                flush=True,
            )

        if passed == total and failed == 0:
            suite.finalize()

    except KeyboardInterrupt:
        infrastructure_failed = True

        print(
            "FAIL: Fixture Recovery V2 interrupted.",
            file=sys.stderr,
        )

    except Exception as error:
        infrastructure_failed = True

        print(
            "FAIL: Fixture Recovery V2 "
            f"infrastructure\n{error}",
            file=sys.stderr,
        )

    print("\n=== RESULT ===")
    print(f"Total: {total}")
    print(f"Executed: {executed}")
    print(f"Passed: {passed}")
    print(f"Failed: {failed}")
    print(f"Not executed: {total - executed}")

    accepted = (
        executed == total
        and passed == total
        and failed == 0
        and not infrastructure_failed
    )

    if not accepted:
        print(
            "FAIL: HF-M09-V2. "
            "Inspect persistent manifest before retrying."
        )

        return 1

    if args.case is None:
        print("PASS: HF-M09-V2")
    else:
        print("PASS: Selected HF-M09-V2 scenario")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())