"""Совместимый интерфейс валидации Manifest V2."""

from .config import recovery_process

from recovery_process.validation import (
    validate_user_identity,
    validate_category_identity,
    validate_post_identity,
    validate_registry,
    validate_snapshot,
    validate_manifest,
    validate_timestamp,
    validate_run_id,
)


__all__ = [
    "validate_user_identity",
    "validate_category_identity",
    "validate_post_identity",
    "validate_registry",
    "validate_snapshot",
    "validate_manifest",
    "validate_timestamp",
    "validate_run_id",
]