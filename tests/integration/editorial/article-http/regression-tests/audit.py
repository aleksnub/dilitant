"""Итоговые проверки полной регрессии.

HF-M10-V2.11 — Schema Downgrade Rejection.
HF-M10-V2.12 — Independent SQL Audit.
"""

from __future__ import annotations

from typing import TYPE_CHECKING

from .commands import require

if TYPE_CHECKING:
    from .context import RegressionContext


def case_11(
    context: RegressionContext,
) -> None:
    before = context.assert_cleaned()

    rejected = context.process.manifest_command(
        "create",
        check=False,
    )

    require(
        rejected.returncode == 1
        and "Manifest schema downgrade rejected."
            in rejected.stderr,
        "Manifest V2 downgrade was not rejected.",
    )

    context.assert_manifest(
        before
    )

    context.assert_cleaned()

    print(
        "PASS: Manifest V2 downgrade rejected",
        flush=True,
    )


def case_12(
    context: RegressionContext,
) -> None:
    context.assert_shared_context()

    require(
        context.user_suite is not None,
        "User Recovery audit context unavailable.",
    )

    manifest = context.assert_cleaned()

    suite = context.fixture_suite

    suite.finalize()

    context.assert_shared_context()

    audit = suite.worker(
        "audit",
        *sorted(context.user_ids),
    )

    suite._validate_audit(
        audit
    )

    require(
        audit == suite._baseline,
        "Final database baseline mismatch.",
    )

    require(
        audit == context.user_suite._baseline,
        "Final User Recovery baseline mismatch.",
    )

    context.assert_manifest(
        manifest
    )

    context.assert_shared_context()

    for name, count in audit["checks"].items():
        print(
            f"PASS: {name} = {count}"
        )

    for name, data in audit["baseline"].items():
        print(
            f"PASS: {name} data unchanged "
            f"({data['count']} records)"
        )

    print(
        f"Audited fixture runs: "
        f"{len(context.run_ids)}"
    )

    print(
        f"Audited user IDs: "
        f"{len(context.user_ids)}"
    )

    print(
        f"Audited post IDs: "
        f"{len(context.post_ids)}"
    )

    print("PASS: Shared Recovery audit context")
    print("PASS: Protected administrator unchanged")
    print("PASS: Final Manifest V2 CLEANED")
    print("PASS: Independent SQL audit")