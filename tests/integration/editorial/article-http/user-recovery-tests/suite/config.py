"""Конфигурация User Recovery Suite."""

from __future__ import annotations

import sys
import re

from dataclasses import replace
from pathlib import Path
from typing import Any


BASE = Path(__file__).resolve().parent.parent

HTTP = BASE.parent

PROCESS_ROOT = HTTP / "recovery_process"

PROCESS_FILE = PROCESS_ROOT / "__init__.py"

if not PROCESS_FILE.is_file():
    raise RuntimeError(
        "RecoveryProcess package unavailable."
    )

existing = sys.modules.get("recovery_process")

if existing is not None:
    origin = getattr(existing, "__file__", None)

    if (
        not isinstance(origin, str)
        or Path(origin).resolve()
            != PROCESS_FILE.resolve()
    ):
        raise RuntimeError(
            "Unexpected RecoveryProcess module origin."
        )

if str(HTTP) not in sys.path:
    sys.path.insert(
        0,
        str(HTTP),
    )

import recovery_process

if (
    Path(recovery_process.__file__).resolve()
    != PROCESS_FILE.resolve()
):
    raise RuntimeError(
        "Unexpected RecoveryProcess module origin."
    )

from recovery_process import RecoveryConfig

from recovery_process.config import (
    WORKER,
    DATABASE,
    TABLE_PREFIX,
    STATES,
    SLOTS,
    LIMITS,
    MANIFEST_COMMANDS,
)

from recovery_process.commands import require

from recovery_process.validation import validate_run_id


LEGACY_WORKER = (
    "/tests/integration/editorial/article-http/"
    "recovery-tests/worker.php"
)

STAGES = {
    "user-intent",
    "user-unconfirmed",
    "user-partial",
    "user-ambiguous",
    "user-substituted",
    "user-sql-intent",
    "user-container",
    "user-cleanup-partial",
    "user-cleanup-crash",
}

CLEANUP_STAGES = {
    "user-cleanup-partial",
    "user-cleanup-crash",
}

SQL_INJECTION_MARKER = (
    "INJECT: HF-U-R06 user resolution SQL failure"
)

AUDIT_CHECKS = {
    "fixture_posts",
    "fixture_terms",
    "orphan_postmeta",
    "orphan_termmeta",
    "orphan_taxonomy",
    "orphan_relationships",
    "orphan_comments",
    "orphan_commentmeta",
    "orphan_usermeta",
    "fixture_users",
    "orphan_post_authors",
    "orphan_comment_users",
    "orphan_link_owners",
}

AUDIT_TABLES = {
    "posts",
    "postmeta",
    "terms",
    "termmeta",
    "taxonomy",
    "relationships",
    "comments",
    "commentmeta",
    "users",
    "usermeta",
    "links",
}


def post_audit_config(
    config: RecoveryConfig,
) -> RecoveryConfig:
    """Конфигурация Worker независимого аудита.

    Изменяется исключительно путь PHP Worker.

    Идентичность окружения, параметры блокирования,
    Manifest и переменные окружения сохраняются.
    """

    require(
        isinstance(config, RecoveryConfig),
        "Invalid Recovery configuration.",
    )

    return replace(
        config,
        worker=LEGACY_WORKER,
    )


def positive_id(value: Any) -> int:
    require(
        type(value) is int
        and value > 0,
        "Invalid recovery object identifier.",
    )

    return value


def valid_digest(value: Any) -> bool:
    return (
        isinstance(value, str)
        and re.fullmatch(
            r"[0-9a-f]{64}",
            value,
        ) is not None
    )