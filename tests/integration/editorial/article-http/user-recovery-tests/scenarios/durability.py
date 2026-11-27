from __future__ import annotations

from typing import TYPE_CHECKING

from .assertions import (
    require,
    positive_id,
    require_checkpoint,
    pending_user,
    require_probe,
    probe_user,
    require_user_identity,
    require_recovered_user,
    finish,
)


if TYPE_CHECKING:
    from suite import UserRecoverySuite


def scenario_idempotence(
    suite: UserRecoverySuite,
) -> None:
    stage = "user-unconfirmed"

    checkpoint = suite.crash(stage)

    manifest, extra = require_checkpoint(
        checkpoint,
        stage,
        "CREATING",
    )

    run_id = manifest["run_id"]
    pending = pending_user(manifest)
    user_id = positive_id(extra["id"])

    probe = require_probe(
        suite,
        run_id,
        [user_id],
    )

    require_user_identity(
        probe_user(probe, user_id),
        pending["identity"],
        user_id,
    )

    first = finish(suite, run_id)

    require_recovered_user(
        first,
        pending,
        user_id,
    )

    for attempt in range(3):
        repeated = suite.cleanup(run_id)

        require(
            repeated == first,
            f"Idempotence failed at attempt {attempt + 1}.",
        )

        require_probe(suite, run_id, [])

        suite.assert_baseline()

    suite.assert_protected_user()


def scenario_container(
    suite: UserRecoverySuite,
) -> None:
    stage = "user-container"

    previous_container = suite.container_id()

    checkpoint = suite.crash(
        stage,
        container=True,
    )

    manifest, extra = require_checkpoint(
        checkpoint,
        stage,
        "CREATING",
    )

    require(
        suite.container_id() != previous_container,
        "HTTP test container was not recreated.",
    )

    require(
        suite.manifest("status") == manifest,
        "Manifest was not preserved across containers.",
    )

    run_id = manifest["run_id"]
    pending = pending_user(manifest)
    user_id = positive_id(extra["id"])

    probe = require_probe(
        suite,
        run_id,
        [user_id],
    )

    user = probe_user(probe, user_id)

    require_user_identity(
        user,
        pending["identity"],
        user_id,
    )

    require(
        user["dependencies"] == extra["dependencies"],
        "User dependencies changed across containers.",
    )

    cleaned = finish(suite, run_id)

    require_recovered_user(
        cleaned,
        pending,
        user_id,
    )


def scenario_audit(
    suite: UserRecoverySuite,
) -> None:
    manifest = suite.manifest("status")

    require(
        manifest["schema_version"] == 2
        and manifest["state"] == "CLEANED",
        "Final manifest is not CLEANED V2.",
    )

    require_probe(
        suite,
        manifest["run_id"],
        [],
    )

    suite.assert_protected_user()
    suite.assert_baseline()

    print(
        "PASS: Independent User Recovery SQL audit",
        flush=True,
    )