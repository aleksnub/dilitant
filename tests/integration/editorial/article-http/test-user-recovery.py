from __future__ import annotations

import sys

from pathlib import Path

from recovery_process import (
    RecoveryConfig,
    RecoveryProcess,
)


BASE = Path(__file__).resolve().parent
MODULES = BASE / "user-recovery-tests"

EXPECTED_IDS = tuple(
    f"HF-U-R{number:02d}"
    for number in range(1, 11)
)


REQUIRED_FILES = (
    "worker.php",

    "support.php",
    "support/infrastructure.php",
    "support/manifest.php",
    "support/identity.php",

    "creation.php",
    "creation/journal.php",
    "creation/partial.php",
    "creation/conflicts.php",
    "creation/execution.php",

    "cleanup.php",

    "ambiguity.php",
    "ambiguity/common.php",
    "ambiguity/duplicate.php",
    "ambiguity/substitution.php",

    "sql-failure.php",
    "audit.php",

    "suite/__init__.py",
    "suite/config.py",
    "suite/validation.py",
    "suite/process.py",
    "suite/audit.py",
    "suite/recovery.py",
    "suite/execution.py",

    "scenarios/__init__.py",
    "scenarios/assertions.py",
    "scenarios/creation.py",
    "scenarios/conflicts.py",
    "scenarios/cleanup.py",
    "scenarios/durability.py",
)


def preflight() -> None:
    missing = [
        str(MODULES / filename)
        for filename in REQUIRED_FILES
        if not (MODULES / filename).is_file()
    ]

    if missing:
        raise RuntimeError(
            "Missing User Recovery components:\n"
            + "\n".join(missing)
        )


def main(
    *,
    config: RecoveryConfig | None = None,
    process: RecoveryProcess | None = None,
) -> int:
    total = len(EXPECTED_IDS)
    executed = 0
    passed = 0
    failed = 0
    infrastructure_failed = False

    print(
        "=== HF-U USER RECOVERY ===",
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

        if str(MODULES) not in sys.path:
            sys.path.insert(
                0,
                str(MODULES),
            )

        from suite import UserRecoverySuite
        from scenarios import build_scenarios

        suite = UserRecoverySuite(
            process=process,
        )

        if suite._process is not process:
            raise RuntimeError(
                "User Recovery executor identity changed."
            )

        scenarios = build_scenarios(suite)

        if not isinstance(scenarios, dict):
            raise RuntimeError(
                "Invalid User Recovery scenario registry."
            )

        if tuple(scenarios) != EXPECTED_IDS:
            raise RuntimeError(
                "Unexpected User Recovery scenario manifest."
            )

        for identifier, operation in scenarios.items():
            if not callable(operation):
                raise RuntimeError(
                    f"Scenario is not callable: {identifier}"
                )

        suite.preflight()

        print(
            f"Registered scenarios: {total}",
            flush=True,
        )

        for identifier, operation in scenarios.items():
            print(
                f"\n=== {identifier} ===",
                flush=True,
            )

            executed += 1

            try:
                operation()

            except Exception as error:
                failed += 1

                print(
                    f"FAIL: {identifier}\n{error}",
                    file=sys.stderr,
                    flush=True,
                )

                break

            passed += 1

            print(
                f"PASS: {identifier}",
                flush=True,
            )

        if passed == total and failed == 0:
            suite.finalize()

    except KeyboardInterrupt:
        infrastructure_failed = True

        print(
            "\nFAIL: User Recovery interrupted.",
            file=sys.stderr,
            flush=True,
        )

    except Exception as error:
        infrastructure_failed = True

        print(
            f"FAIL: User Recovery infrastructure\n{error}",
            file=sys.stderr,
            flush=True,
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
        print("FAIL: HF-U USER RECOVERY")
        return 1

    print("PASS: HF-U USER RECOVERY")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())