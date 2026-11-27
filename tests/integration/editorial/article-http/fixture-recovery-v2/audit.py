from __future__ import annotations

from typing import Any

from .config import (
    PROBE_ID_FIELDS,
    require,
    positive_id,
    validate_run_id,
)


class FixtureAuditMixin:
    def fixture_probe(
        self,
        run_id: str,
    ) -> dict[str, Any]:
        validate_run_id(run_id)

        result = self.worker(
            "fixture-probe",
            run_id,
        )

        require(
            isinstance(result, dict)
            and set(result) == {
                "run_id",
                *PROBE_ID_FIELDS,
            }
            and result["run_id"] == run_id,
            "Invalid fixture probe structure.",
        )

        for field in PROBE_ID_FIELDS:
            values = result[field]

            require(
                isinstance(values, list)
                and all(
                    type(value) is int and value > 0
                    for value in values
                ),
                f"Invalid fixture probe field: {field}.",
            )

            require(
                values == sorted(set(values)),
                f"Noncanonical fixture identifiers: {field}.",
            )

        require(
            set(result["partial_term_ids"])
            .issubset(result["term_ids"]),
            "Partial category absent from term registry.",
        )

        require(
            self._protected_id()
            not in result["user_ids"],
            "Protected user in fixture probe.",
        )

        self.remember_fixture_probe(result)

        return result

    def remember_fixture_probe(
        self,
        observed: dict[str, Any],
    ) -> None:
        run_id = validate_run_id(
            observed["run_id"]
        )

        self._run_ids.add(run_id)

        for identifier in observed["user_ids"]:
            self._remember_user(identifier)

        for identifier in observed["post_ids"]:
            self._post_ids.add(
                positive_id(identifier)
            )

    @staticmethod
    def registered_ids(
        objects: list[dict[str, Any]],
        kind: str,
    ) -> list[int]:
        return sorted(
            record["id"]
            for record in objects
            if record["kind"] == kind
            and record["id"] > 0
        )

    @staticmethod
    def registered_taxonomy_ids(
        objects: list[dict[str, Any]],
    ) -> list[int]:
        return sorted(
            record["taxonomy_id"]
            for record in objects
            if record["kind"] == "category"
            and record["id"] > 0
        )

    def assert_registry_probe(
        self,
        objects: list[dict[str, Any]],
        observed: dict[str, Any],
        *,
        exact: bool = False,
    ) -> None:
        expected = {
            "user_ids": self.registered_ids(
                objects, "user"
            ),
            "post_ids": self.registered_ids(
                objects, "post"
            ),
            "term_ids": self.registered_ids(
                objects, "category"
            ),
            "taxonomy_ids": self.registered_taxonomy_ids(
                objects
            ),
        }

        for field, identifiers in expected.items():
            actual = observed[field]

            if exact:
                require(
                    actual == identifiers,
                    f"Unexpected fixture identities: {field}.",
                )
            else:
                require(
                    set(identifiers).issubset(actual),
                    f"Missing registered fixtures: {field}.",
                )

        if exact:
            require(
                observed["partial_term_ids"] == [],
                "Unexpected partial category.",
            )

    def assert_empty_fixture_probe(
        self,
        observed: dict[str, Any],
    ) -> None:
        require(
            all(
                observed[field] == []
                for field in PROBE_ID_FIELDS
            ),
            f"Residual fixture identities: {observed}",
        )