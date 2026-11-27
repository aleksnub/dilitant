"""Конфигурация полной регрессии HF-M10-V2."""

from __future__ import annotations

from pathlib import Path


BASE = Path(__file__).resolve().parent.parent

ROOT = BASE.parents[3]

RECOVERY_PROCESS = BASE / "recovery_process"

FIXTURE_RECOVERY = BASE / "fixture-recovery-v2"

USER_RECOVERY = BASE / "user-recovery-tests"

FIXTURE_ENTRY = BASE / "test-fixture-recovery-v2.py"

USER_ENTRY = BASE / "test-user-recovery.py"

EDITORIAL = "/tests/integration/editorial"

HTTP = f"{EDITORIAL}/article-http"

HTTP_URL = "http://127.0.0.1:8095"

PRODUCTION_CONTAINER = "dilitant_land_wp"

NETWORK = "dilitant_land_dilitant_land_network"

TIMEOUT = 180

HTTP_TIMEOUT = 15

HTTP_BODY_LIMIT = 8 * 1024 * 1024

TOTAL_CASES = 12

PASSWORD_ENV = "DILITANT_TEST_DB_PASSWORD"


REQUIRED_ENVIRONMENT = (
    "DILITANT_TEST_MODE",
    "DILITANT_TEST_DB_NAME",
    "DILITANT_TEST_DB_USER",
    "DILITANT_TEST_DB_HOST",
    PASSWORD_ENV,
)


EXPECTED_ENVIRONMENT = {
    "DILITANT_TEST_MODE": "1",
    "DILITANT_TEST_DB_NAME": "dilitant_land_test",
    "DILITANT_TEST_DB_USER": "dilitant_test",
    "DILITANT_TEST_DB_HOST": "dilitant_land_db:3306",
}


REQUIRED_PATHS = (
    ROOT / "src/wordpress",
    ROOT / "tests",
    RECOVERY_PROCESS,
    FIXTURE_RECOVERY,
    USER_RECOVERY,
    FIXTURE_ENTRY,
    USER_ENTRY,
)


EXPECTED_OBJECTS = {
    "user": 4,
    "category": 4,
    "post": 10,
}


HTTP_ROUTES = (
    "/",
    "/diletant-armenia/",
    "/diletant-armenia/articles/",
    "/diletant-armenia/archive/",
)


CLI_TESTS = {
    1: "bootstrap.php",
    2: "test-article-identity.php",
    3: "test-article-query.php",
    4: "test-article-catalog.php",
    5: "test-article-visibility.php",
}


FIXTURE_SCENARIOS = tuple(range(1, 11))


USER_SCENARIOS = tuple(
    f"HF-U-R{number:02d}"
    for number in range(1, 11)
)


SCENARIO_NAMES = {
    1: "CLI Bootstrap",
    2: "Article Identity",
    3: "Article Query",
    4: "Article Catalog",
    5: "Article Visibility",
    6: "HTTP Bootstrap",
    7: "Manifest V2 Lifecycle",
    8: "Fixture Ownership",
    9: "Fixture Recovery V2",
    10: "User Recovery",
    11: "Schema Downgrade Rejection",
    12: "Independent SQL Audit",
}