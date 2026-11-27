"""Структурная валидация Manifest V2."""

from __future__ import annotations

import json
import re

from datetime import datetime
from typing import Any

from .commands import (
    RecoveryTestError,
    require,
)

from .config import (
    SCHEMA_VERSION,
    DATABASE,
    TABLE_PREFIX,
    STATES,
    SLOTS,
    LIMITS,
)


CATEGORY_FIELDS = (
    "root_category",
    "child_category",
    "grandchild_category",
    "outside_category",
)

POST_FIELDS = (
    "root_post",
    "child_post",
    "grandchild_post",
    "outside_post",
    "draft_post",
    "page_post",
    "archive_post",
    "private_post",
    "future_post",
    "password_post",
)

CATEGORY_NAMES = (
    "AM Root",
    "AM Child",
    "AM Grandchild",
    "AM Outside",
)

POST_IDENTITIES = (
    ("AM Root Article", "post", "administrator"),
    ("AM Child Article", "post", "administrator"),
    ("AM Grandchild Article", "post", "administrator"),
    ("AM Outside Article", "post", "administrator"),
    ("AM Draft Article", "post", "author_a"),
    ("AM Page Fixture", "page", "administrator"),
    ("AM Archive Photo Fixture", "archive_photo", "administrator"),
    ("AM Private Article", "post", "author_b"),
    ("AM Future Article", "post", "administrator"),
    ("AM Password Protected Article", "post", "administrator"),
)

USER_IDENTITIES = {
    "administrator": ("administrator", "administrator"),
    "subscriber": ("subscriber", "subscriber"),
    "author_a": ("author", "author-a"),
    "author_b": ("author", "author-b"),
}


def parse_json(raw: str) -> dict[str, Any]:
    try:
        data = json.loads(raw)

    except json.JSONDecodeError:
        raise RecoveryTestError(
            "Invalid JSON response."
        ) from None

    require(
        isinstance(data, dict),
        "Expected JSON object.",
    )

    return data


def validate_run_id(value: Any) -> str:
    require(
        isinstance(value, str)
        and re.fullmatch(
            r"[0-9a-f]{12}",
            value,
        ) is not None,
        "Invalid recovery run ID.",
    )

    return value


def validate_timestamp(value: Any) -> None:
    require(
        isinstance(value, str)
        and re.fullmatch(
            r"[0-9]{4}-[0-9]{2}-[0-9]{2}"
            r"T[0-9]{2}:[0-9]{2}:[0-9]{2}"
            r"[+-][0-9]{2}:[0-9]{2}",
            value,
        ) is not None,
        "Invalid manifest timestamp.",
    )

    try:
        timestamp = datetime.fromisoformat(value)

    except ValueError:
        raise RecoveryTestError(
            "Invalid manifest timestamp."
        ) from None

    require(
        timestamp.tzinfo is not None
        and timestamp.isoformat(
            timespec="seconds"
        ) == value,
        "Invalid manifest timestamp.",
    )


def validate_user_identity(
    identity: dict[str, Any],
    run_id: str,
    slot: str,
) -> None:
    validate_run_id(run_id)

    require(
        isinstance(identity, dict)
        and set(identity) == {
            "slot",
            "login",
            "email",
            "nicename",
            "role",
        },
        "Invalid user identity structure.",
    )

    require(
        isinstance(slot, str)
        and slot in USER_IDENTITIES
        and all(
            isinstance(value, str)
            for value in identity.values()
        ),
        "Invalid user identity field type.",
    )

    role, segment = USER_IDENTITIES[slot]

    login = f"am-http-{segment}-{run_id}"

    require(
        identity["slot"] == slot
        and identity["role"] == role
        and identity["login"] == login
        and identity["email"] == f"{login}@example.invalid",
        "Invalid canonical user identity.",
    )

    require(
        re.fullmatch(
            rf"am-user-{run_id}-[0-9a-f]{{16}}",
            identity["nicename"],
        ) is not None,
        "Invalid canonical user nicename.",
    )


def validate_category_identity(
    identity: dict[str, Any],
    run_id: str,
    position: int,
    category_ids: list[int],
) -> None:
    validate_run_id(run_id)

    require(
        isinstance(identity, dict)
        and set(identity) == {
            "name",
            "slug",
            "parent",
        },
        "Invalid category identity structure.",
    )

    require(
        isinstance(identity["name"], str)
        and isinstance(identity["slug"], str)
        and type(identity["parent"]) is int
        and identity["parent"] >= 0,
        "Invalid category identity fields.",
    )

    require(
        type(position) is int
        and 0 <= position < len(CATEGORY_NAMES),
        "Unexpected fixture category position.",
    )

    slugs = (
        "diletant-armenia",
        f"am-child-{run_id}",
        f"am-grandchild-{run_id}",
        f"am-outside-{run_id}",
    )

    if position == 1:
        require(
            len(category_ids) >= 1,
            "Root category unavailable.",
        )

        parent = category_ids[0]

    elif position == 2:
        require(
            len(category_ids) >= 2,
            "Child category unavailable.",
        )

        parent = category_ids[1]

    else:
        parent = 0

    require(
        identity["name"]
            == f"{CATEGORY_NAMES[position]} {run_id}"
        and identity["slug"] == slugs[position]
        and identity["parent"] == parent,
        "Fixture category identity or hierarchy mismatch.",
    )


def validate_post_identity(
    identity: dict[str, Any],
    run_id: str,
    position: int,
    user_fields: dict[str, int],
) -> None:
    validate_run_id(run_id)

    require(
        isinstance(identity, dict)
        and set(identity) == {
            "title",
            "type",
            "author_id",
            "content_marker",
        },
        "Invalid post identity structure.",
    )

    require(
        isinstance(identity["title"], str)
        and isinstance(identity["type"], str)
        and type(identity["author_id"]) is int
        and identity["author_id"] > 0
        and isinstance(identity["content_marker"], str),
        "Invalid post identity fields.",
    )

    require(
        type(position) is int
        and 0 <= position < len(POST_IDENTITIES),
        "Unexpected fixture post position.",
    )

    title, post_type, slot = POST_IDENTITIES[position]

    require(
        slot in user_fields
        and identity["title"] == f"{title} {run_id}"
        and identity["type"] == post_type
        and identity["author_id"] == user_fields[slot],
        "Fixture post identity or author policy mismatch.",
    )

    require(
        re.fullmatch(
            rf"AM-CONTENT-{run_id}-[0-9a-f]{{16}}",
            identity["content_marker"],
        ) is not None,
        "Invalid fixture content marker.",
    )


def validate_registry(
    objects: list[dict[str, Any]],
    run_id: str,
    state: str,
) -> None:
    validate_run_id(run_id)

    require(
        isinstance(state, str)
        and state in STATES,
        "Invalid manifest registry state.",
    )

    require(
        isinstance(objects, list)
        and len(objects) <= sum(LIMITS.values()),
        "Invalid Manifest V2 registry.",
    )

    counts = dict.fromkeys(LIMITS, 0)

    order = {
        "user": 0,
        "category": 1,
        "post": 2,
    }

    previous_order = 0

    ids: set[tuple[str, int]] = set()
    identities: set[tuple[str, str]] = set()
    taxonomy_ids: set[int] = set()

    user_fields: dict[str, int] = {}
    category_ids: list[int] = []

    pending = 0

    for index, record in enumerate(objects):
        require(
            isinstance(record, dict)
            and set(record) == {
                "kind",
                "id",
                "taxonomy_id",
                "identity",
            },
            "Invalid manifest object structure.",
        )

        kind = record["kind"]
        identifier = record["id"]
        taxonomy_id = record["taxonomy_id"]
        identity = record["identity"]

        require(
            isinstance(kind, str)
            and kind in LIMITS
            and type(identifier) is int
            and identifier >= 0
            and type(taxonomy_id) is int
            and taxonomy_id >= 0
            and isinstance(identity, dict),
            "Invalid manifest object fields.",
        )

        require(
            order[kind] >= previous_order,
            "Invalid fixture registration order.",
        )

        previous_order = order[kind]

        position = counts[kind]

        counts[kind] += 1

        require(
            counts[kind] <= LIMITS[kind],
            "Fixture registry limit exceeded.",
        )

        if kind == "category":
            require(
                (identifier == 0) == (taxonomy_id == 0),
                "Invalid category identifiers.",
            )

        else:
            require(
                taxonomy_id == 0,
                "Unexpected taxonomy ID.",
            )

        if kind != "user":
            require(
                len(user_fields) == LIMITS["user"],
                "Incomplete confirmed user registry.",
            )

        if kind == "post":
            require(
                len(category_ids) == LIMITS["category"],
                "Incomplete confirmed category registry.",
            )

        if identifier == 0:
            pending += 1

            require(
                pending == 1
                and index == len(objects) - 1,
                "Invalid pending intent.",
            )

        else:
            key = (kind, identifier)

            require(
                key not in ids,
                "Duplicate manifest object ID.",
            )

            ids.add(key)

        if kind == "user":
            slot = SLOTS[position]

            validate_user_identity(
                identity,
                run_id,
                slot,
            )

            for field in (
                "slot",
                "login",
                "email",
                "nicename",
            ):
                key = (
                    field,
                    identity[field],
                )

                require(
                    key not in identities,
                    "Duplicate user identity.",
                )

                identities.add(key)

            if identifier > 0:
                user_fields[slot] = identifier

        elif kind == "category":
            validate_category_identity(
                identity,
                run_id,
                position,
                category_ids,
            )

            key = (
                "category",
                identity["slug"],
            )

            require(
                key not in identities,
                "Duplicate category identity.",
            )

            identities.add(key)

            if identifier > 0:
                require(
                    taxonomy_id not in taxonomy_ids,
                    "Duplicate taxonomy ID.",
                )

                taxonomy_ids.add(taxonomy_id)

                category_ids.append(identifier)

        else:
            validate_post_identity(
                identity,
                run_id,
                position,
                user_fields,
            )

            key = (
                "post",
                identity["content_marker"],
            )

            require(
                key not in identities,
                "Duplicate post identity.",
            )

            identities.add(key)

    if state in {
        "READY",
        "TESTING",
        "CLEANED",
    }:
        require(
            pending == 0,
            "Pending intent in stable manifest.",
        )

    if state in {
        "READY",
        "TESTING",
    }:
        require(
            counts == LIMITS,
            "Incomplete ready registry.",
        )


def validate_snapshot(
    manifest: dict[str, Any],
) -> None:
    snapshot = manifest["snapshot"]
    state = manifest["state"]

    if snapshot is None:
        require(
            state not in {
                "READY",
                "TESTING",
            },
            "Ready manifest has no snapshot.",
        )

        return

    require(
        state not in {
            "CREATING",
            "FAILED",
        },
        "Unexpected fixture snapshot.",
    )

    require(
        isinstance(snapshot, dict)
        and set(snapshot) == {
            "run_id",
            "base_created",
            "query_created",
            "users_created",
            "user_ids",
            "category_ids",
            "post_ids",
            "fields",
        },
        "Invalid Snapshot V2 structure.",
    )

    require(
        snapshot["run_id"] == manifest["run_id"]
        and snapshot["base_created"] is True
        and snapshot["query_created"] is True
        and snapshot["users_created"] is True,
        "Invalid Snapshot V2 metadata.",
    )

    for kind, field in (
        ("user", "user_ids"),
        ("category", "category_ids"),
        ("post", "post_ids"),
    ):
        expected = [
            record["id"]
            for record in manifest["objects"]
            if record["kind"] == kind
        ]

        actual = snapshot[field]

        require(
            len(expected) == LIMITS[kind]
            and all(
                identifier > 0
                for identifier in expected
            )
            and isinstance(actual, list)
            and all(
                type(identifier) is int
                and identifier > 0
                for identifier in actual
            )
            and actual == expected,
            f"Snapshot registry mismatch: {field}.",
        )

    fields = snapshot["fields"]

    require(
        isinstance(fields, dict)
        and all(
            type(value) is int
            and value > 0
            for value in fields.values()
        ),
        "Invalid Snapshot V2 fields.",
    )

    expected_fields = (
        *SLOTS,
        *CATEGORY_FIELDS,
        *POST_FIELDS,
    )

    identifiers = [
        record["id"]
        for record in manifest["objects"]
    ]

    require(
        len(identifiers) == len(expected_fields)
        and fields == dict(
            zip(
                expected_fields,
                identifiers,
            )
        ),
        "Snapshot field identifiers mismatch.",
    )


def validate_manifest(
    data: dict[str, Any],
) -> None:
    require(
        isinstance(data, dict)
        and set(data) == {
            "schema_version",
            "run_id",
            "database_name",
            "table_prefix",
            "container_origin",
            "created_at",
            "state",
            "objects",
            "snapshot",
        },
        "Invalid Manifest V2 structure.",
    )

    require(
        type(data["schema_version"]) is int
        and data["schema_version"] == SCHEMA_VERSION
        and data["database_name"] == DATABASE
        and data["table_prefix"] == TABLE_PREFIX,
        "Invalid Manifest V2 identity.",
    )

    run_id = validate_run_id(
        data["run_id"]
    )

    state = data["state"]

    require(
        isinstance(state, str)
        and state in STATES,
        "Invalid Manifest V2 state.",
    )

    require(
        isinstance(data["container_origin"], str)
        and bool(data["container_origin"]),
        "Invalid Manifest V2 metadata.",
    )

    validate_timestamp(
        data["created_at"]
    )

    validate_registry(
        data["objects"],
        run_id,
        state,
    )

    validate_snapshot(data)