"""Испытания жизненного цикла Fixture.

HF-M10-V2.7 — Manifest V2 Lifecycle.
HF-M10-V2.8 — Fixture Ownership.
"""

from __future__ import annotations

from collections import Counter
from copy import deepcopy
from typing import TYPE_CHECKING

from .config import (
    HTTP,
    EXPECTED_OBJECTS,
)

from .commands import (
    require,
    require_output,
    require_output_prefix,
)

from .http import verify_http_routes

if TYPE_CHECKING:
    from .context import RegressionContext


def case_7(
    context: RegressionContext,
) -> None:
    previous = context.assert_cleaned()

    suite = context.fixture_suite

    ready = suite.create_ready()

    require(
        ready["schema_version"] == 2
        and ready["state"] == "READY"
        and ready["run_id"] != previous["run_id"],
        "Invalid newly created Manifest V2.",
    )

    objects = ready["objects"]

    counts = dict(Counter(
        record["kind"]
        for record in objects
    ))

    require(
        counts == EXPECTED_OBJECTS
        and len(objects) == 18
        and all(
            record["id"] > 0
            for record in objects
        ),
        "Incomplete Manifest V2 registry.",
    )

    context.assert_manifest(
        ready
    )

    require(
        context.manifest("verify") == ready,
        "Manifest V2 verification changed registry.",
    )

    duplicate = context.process.manifest_command(
        "create-v2",
        check=False,
    )

    require(
        duplicate.returncode == 1
        and "Unfinished fixture run exists."
            in duplicate.stderr,
        "Duplicate fixture creation was not rejected.",
    )

    context.assert_manifest(
        ready
    )

    verify_http_routes(
        context
    )

    context.assert_manifest(
        ready
    )

    previous_process = context.process

    previous_container_id = (
        previous_process.container_id
    )

    # Остановка и запуск выполняются под
    # одной ResourceLease исходного исполнителя.

    with previous_process.session():
        previous_process.assert_container()

        context.environment(
            "stop"
        )

        context.environment(
            "start"
        )

    # Новый RecoveryProcess создаётся только
    # после освобождения предыдущей ResourceLease.

    context.reconnect()

    require(
        context.process is not previous_process,
        "RecoveryProcess was not reconnected.",
    )

    require(
        context.process.container_id
        != previous_container_id,
        "HTTP container was not recreated.",
    )

    require(
        context.fixture_suite is suite,
        "Fixture Recovery Suite changed after reconnect.",
    )

    context.assert_shared_context()

    context.assert_manifest(
        ready
    )

    require(
        context.manifest("verify") == ready,
        "Manifest V2 changed after container restart.",
    )

    verify_http_routes(
        context
    )

    context.assert_manifest(
        ready
    )

    observed = suite.fixture_probe(
        ready["run_id"]
    )

    suite.assert_registry_probe(
        objects,
        observed,
        exact=True,
    )

    suite.assert_protected_user()

    expected = deepcopy(
        ready
    )

    expected["state"] = "CLEANED"

    cleaned = suite.cleanup(
        ready["run_id"]
    )

    require(
        cleaned == expected,
        "Unexpected cleaned Manifest V2.",
    )

    require(
        suite.cleanup(
            ready["run_id"]
        ) == expected,
        "Manifest V2 cleanup is not idempotent.",
    )

    context.assert_manifest(
        expected
    )

    context.assert_cleaned()

    print("PASS: Manifest V2 creation")
    print("PASS: Manifest V2 verification")
    print("PASS: Duplicate creation rejected")
    print("PASS: HTTP routes before and after restart")
    print("PASS: Manifest V2 cleanup")
    print("PASS: Cleanup idempotence")


def case_8(
    context: RegressionContext,
) -> None:
    before = context.assert_cleaned()

    output = context.environment_cli(
        f"{HTTP}/test-fixture-ownership.php",
    )

    context.report(
        output
    )

    for number in range(1, 6):
        require_output_prefix(
            output,
            f"PASS: HF-M08.{number} ",
        )

    for expected in (
        "Total: 5",
        "Passed: 5",
        "Failed: 0",
        "PASS: HF-M08",
        "PASS: Persistent manifest unchanged",
    ):
        require_output(
            output,
            expected,
        )

    context.assert_manifest(
        before
    )

    context.assert_baseline()

    context.assert_shared_context()