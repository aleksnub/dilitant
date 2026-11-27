from __future__ import annotations

import sys

from pathlib import Path


BASE = Path(__file__).resolve().parent
HTTP = BASE.parent

USER_RECOVERY = HTTP / "user-recovery-tests"

if not USER_RECOVERY.is_dir():
    raise RuntimeError(
        "User Recovery package unavailable."
    )

if str(USER_RECOVERY) not in sys.path:
    sys.path.insert(
        0,
        str(USER_RECOVERY),
    )

from suite.config import positive_id
from suite.execution import UserRecoverySuite

from recovery_process.config import (
    LIMITS,
    SLOTS,
)

from recovery_process.commands import require

from recovery_process.validation import (
    validate_manifest,
    validate_run_id,
)


SCENARIO_NAMES = {
    1: "Empty manifest",
    2: "Intent without object",
    3: "Unconfirmed category",
    4: "Unconfirmed post",
    5: "Partially confirmed fixtures",
    6: "Interrupted cleanup",
    7: "Ambiguous post identity",
    8: "Container crash",
    9: "Recovery SQL failure",
    10: "Partial category",
}

CREATION_STAGES = frozenset({
    "fixture-empty",
    "fixture-intent",
    "fixture-category",
    "fixture-post",
    "fixture-confirmed",
    "fixture-ambiguous",
    "fixture-container",
    "fixture-sql-intent",
    "fixture-partial-category",
})

CLEANUP_STAGE = "fixture-cleanup-crash"

STAGES = CREATION_STAGES | {
    CLEANUP_STAGE
}

USER_COUNT = LIMITS["user"]

CATEGORY_COUNT = LIMITS["category"]

POST_COUNT = LIMITS["post"]

CATEGORY_PENDING_COUNT = (
    USER_COUNT + 1
)

POST_PENDING_COUNT = (
    USER_COUNT
    + CATEGORY_COUNT
    + 1
)

READY_COUNT = (
    USER_COUNT
    + CATEGORY_COUNT
    + POST_COUNT
)

PROBE_ID_FIELDS = (
    "user_ids",
    "post_ids",
    "term_ids",
    "taxonomy_ids",
    "partial_term_ids",
)

SQL_INJECTION_MARKER = (
    "INJECT: HF-M09-V2 post resolution SQL failure"
)