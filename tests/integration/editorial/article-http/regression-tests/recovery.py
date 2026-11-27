"""Испытания аварийного восстановления.

HF-M10-V2.9 — Fixture Recovery V2.
HF-M10-V2.10 — User Recovery.
"""

from __future__ import annotations

from typing import (
    Callable,
    TYPE_CHECKING,
)

from .config import (
    FIXTURE_SCENARIOS,
    USER_SCENARIOS,
)

from .commands import (
    require,
    assert_process_configuration,
)

if TYPE_CHECKING:
    from .context import RegressionContext


def verify_registry(
    scenarios: dict,
    expected: tuple,
) -> None:
    require(
        isinstance(scenarios, dict)
        and tuple(scenarios) == expected,
        "Unexpected Recovery scenario registry.",
    )

    require(
        all(
            callable(operation)
            for operation in scenarios.values()
        ),
        "Invalid Recovery scenario operation.",
    )


def execute_scenario(
    identifier: str,
    operation: Callable[[], None],
    title: str = "",
) -> None:
    heading = (
        f"{identifier} {title}"
    ).strip()

    print(
        f"\n=== {heading} ===",
        flush=True,
    )

    operation()

    print(
        f"PASS: {identifier}",
        flush=True,
    )


def case_9(
    context: RegressionContext,
) -> None:
    context.assert_cleaned()

    suite = context.fixture_suite

    package = context.fixture_package

    scenarios = package.build_scenarios(
        suite
    )

    verify_registry(
        scenarios,
        FIXTURE_SCENARIOS,
    )

    passed = 0

    for number, operation in scenarios.items():
        context.assert_cleaned()

        execute_scenario(
            f"HF-M09-V2.{number}",
            operation,
            package.SCENARIO_NAMES[number],
        )

        context.assert_cleaned()

        passed += 1

    require(
        passed == len(FIXTURE_SCENARIOS),
        "Incomplete Fixture Recovery V2 coverage.",
    )

    suite.finalize()

    context.assert_shared_context()

    context.assert_cleaned()

    print(
        "PASS: HF-M09-V2",
        flush=True,
    )


def case_10(
    context: RegressionContext,
) -> None:
    context.assert_cleaned()

    process = context.process

    assert_process_configuration(
        process,
        context.config,
    )

    previous_container_id = (
        process.container_id
    )

    suite = context.attach_user_suite()

    require(
        suite._process is process,
        "User Recovery executor identity mismatch.",
    )

    scenarios = context.user_scenarios.build_scenarios(
        suite
    )

    verify_registry(
        scenarios,
        USER_SCENARIOS,
    )

    passed = 0

    # Родительская ResourceLease не удерживается.
    #
    # Сценарии Recovery самостоятельно управляют
    # блокированием ресурсов через общий
    # RecoveryProcess.

    for identifier, operation in scenarios.items():
        context.assert_shared_context()

        execute_scenario(
            identifier,
            operation,
        )

        context.assert_shared_context()

        passed += 1

    require(
        passed == len(USER_SCENARIOS),
        "Incomplete User Recovery coverage.",
    )

    suite.finalize()

    context.assert_shared_context()

    require(
        context.process is process,
        "Shared RecoveryProcess identity changed.",
    )

    assert_process_configuration(
        process,
        context.config,
    )

    require(
        process.container_id
        != previous_container_id,
        "HTTP test container was not recreated.",
    )

    process.assert_container()

    process.verify_restart_readiness()

    context.reconnect()

    require(
        context.user_suite is suite,
        "User Recovery Suite changed after reconnect.",
    )

    require(
        suite._process is context.process,
        "User Recovery executor was not synchronized.",
    )

    context.assert_shared_context()

    context.assert_cleaned()

    print(
        "PASS: HF-U USER RECOVERY",
        flush=True,
    )