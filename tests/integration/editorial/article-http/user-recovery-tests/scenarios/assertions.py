from __future__ import annotations

from typing import Any, TYPE_CHECKING


if TYPE_CHECKING:
    from suite import UserRecoverySuite


DEPENDENCIES = (
    "posts",
    "links",
    "comments",
    "usermeta",
    "sessions",
    "application_passwords",
)


def require(condition: bool, message: str) -> None:
    if not condition:
        raise RuntimeError(message)


def positive_id(value: Any) -> int:
    require(
        type(value) is int and value > 0,
        "Invalid recovery object ID.",
    )

    return value


def require_dependencies(
    dependencies: dict[str, Any],
    *,
    metadata: bool,
) -> None:
    require(
        isinstance(dependencies, dict)
        and set(dependencies) == set(DEPENDENCIES),
        "Invalid user dependency structure.",
    )

    for name, count in dependencies.items():
        require(
            type(count) is int and count >= 0,
            f"Invalid dependency count: {name}.",
        )

        if name == "usermeta" and metadata:
            require(
                count > 0,
                "Expected existing user metadata.",
            )
        else:
            require(
                count == 0,
                f"Unexpected user dependency: {name}.",
            )


def require_checkpoint(
    checkpoint: dict[str, Any],
    stage: str,
    state: str,
) -> tuple[dict[str, Any], dict[str, Any]]:
    require(
        checkpoint.get("event") == "CRASH_READY"
        and checkpoint.get("stage") == stage,
        "Unexpected crash checkpoint.",
    )

    manifest = checkpoint.get("manifest")
    extra = checkpoint.get("extra")

    require(
        isinstance(manifest, dict)
        and isinstance(extra, dict),
        "Incomplete crash checkpoint.",
    )

    require(
        manifest.get("schema_version") == 2
        and manifest.get("state") == state
        and isinstance(manifest.get("objects"), list),
        "Unexpected checkpoint manifest.",
    )

    return manifest, extra


def pending_user(
    manifest: dict[str, Any],
) -> dict[str, Any]:
    pending = [
        record
        for record in manifest["objects"]
        if record.get("id") == 0
    ]

    require(
        len(pending) == 1
        and pending[0].get("kind") == "user"
        and pending[0].get("taxonomy_id") == 0,
        "Expected exactly one pending user intent.",
    )

    require(
        manifest["objects"][-1] == pending[0],
        "Unexpected pending user position.",
    )

    return pending[0]


def require_user_identity(
    user: dict[str, Any],
    identity: dict[str, Any],
    user_id: int,
) -> None:
    require(
        user.get("id") == user_id
        and user.get("login") == identity["login"]
        and user.get("email") == identity["email"]
        and user.get("nicename") == identity["nicename"],
        "Independent user identity mismatch.",
    )


def require_probe(
    suite: UserRecoverySuite,
    run_id: str,
    expected_ids: list[int],
) -> dict[str, Any]:
    probe = suite.probe(run_id)

    require(
        probe.get("run_id") == run_id,
        "Unexpected probe run ID.",
    )

    ids = probe.get("user_ids")
    users = probe.get("users")

    require(
        isinstance(ids, list)
        and isinstance(users, list),
        "Invalid user probe.",
    )

    require(
        sorted(ids) == sorted(expected_ids)
        and len(ids) == len(set(ids)),
        "Unexpected fixture user candidates.",
    )

    require(
        sorted(user["id"] for user in users)
        == sorted(expected_ids),
        "Probe user records do not match IDs.",
    )

    return probe


def probe_user(
    probe: dict[str, Any],
    user_id: int,
) -> dict[str, Any]:
    users = [
        user
        for user in probe["users"]
        if user["id"] == user_id
    ]

    require(
        len(users) == 1,
        "Expected exactly one probe user.",
    )

    return users[0]


def require_recovered_user(
    manifest: dict[str, Any],
    pending: dict[str, Any],
    user_id: int,
) -> None:
    expected = dict(pending)
    expected["id"] = user_id

    require(
        expected in manifest["objects"],
        "Recovered user record was not retained.",
    )

    require(
        all(
            record["id"] > 0
            for record in manifest["objects"]
        ),
        "Unresolved intent remains after recovery.",
    )


def finish(
    suite: UserRecoverySuite,
    run_id: str,
) -> dict[str, Any]:
    cleaned = suite.cleanup(run_id)

    require(
        cleaned.get("schema_version") == 2
        and cleaned.get("run_id") == run_id
        and cleaned.get("state") == "CLEANED",
        "Recovery did not reach CLEANED.",
    )

    require(
        all(
            record["id"] > 0
            for record in cleaned["objects"]
        ),
        "Pending intent remains after cleanup.",
    )

    require_probe(suite, run_id, [])

    repeated = suite.cleanup(run_id)

    require(
        repeated == cleaned,
        "Repeated cleanup changed the manifest.",
    )

    require(
        suite.manifest("status") == cleaned,
        "Persistent cleaned manifest mismatch.",
    )

    suite.assert_baseline()

    return cleaned