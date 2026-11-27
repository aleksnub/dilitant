"""HTTP-регрессия редакционного функционала.

HF-M10-V2.6 — HTTP Bootstrap.
"""

from __future__ import annotations

from typing import TYPE_CHECKING

from urllib.request import (
    HTTPRedirectHandler,
    ProxyHandler,
    build_opener,
)

from .config import (
    HTTP,
    HTTP_ROUTES,
    HTTP_TIMEOUT,
    HTTP_BODY_LIMIT,
)

from .commands import (
    RegressionError,
    require,
    require_output,
)

if TYPE_CHECKING:
    from .context import RegressionContext


class RejectRedirect(HTTPRedirectHandler):
    """Запрещает неявное перенаправление HTTP."""

    def redirect_request(
        self,
        req,
        fp,
        code,
        msg,
        headers,
        newurl,
    ):
        raise RegressionError(
            f"Unexpected HTTP redirect: "
            f"{code} {newurl}"
        )


def verify_http_routes(
    context: RegressionContext,
) -> None:
    context.assert_shared_context()

    process = context.process

    opener = build_opener(
        ProxyHandler({}),
        RejectRedirect(),
    )

    with process.session():
        process.assert_container()

        for route in HTTP_ROUTES:
            url = context.http_url + route

            with opener.open(
                url,
                timeout=HTTP_TIMEOUT,
            ) as response:
                require(
                    response.status == 200,
                    f"Unexpected HTTP status: {route}",
                )

                require(
                    response.geturl() == url,
                    f"Unexpected effective URL: {route}",
                )

                require(
                    response.headers.get_content_type()
                    == "text/html",
                    f"Unexpected HTTP content type: {route}",
                )

                body = response.read(
                    HTTP_BODY_LIMIT + 1
                )

            require(
                0 < len(body) <= HTTP_BODY_LIMIT,
                f"Invalid HTTP response size: {route}",
            )

            html = body.decode(
                "utf-8",
                errors="replace",
            )

            require(
                "fatal error" not in html.lower(),
                f"PHP fatal error: {route}",
            )

            if route == "/diletant-armenia/":
                required_markers = (
                    "diletant-armenia__article-card",
                    "diletant-armenia__photo-card",
                    "data-carousel-track",
                )

                require(
                    all(
                        marker in html
                        for marker in required_markers
                    ),
                    "Editorial page content unavailable.",
                )

            print(
                f"PASS: HTTP 200 {route}",
                flush=True,
            )

        process.assert_container()

    require(
        context.process is process,
        "RecoveryProcess changed during HTTP verification.",
    )

    context.assert_shared_context()


def case_6(
    context: RegressionContext,
) -> None:
    context.assert_shared_context()

    context.environment(
        "start"
    )

    context.reconnect()

    output = context.environment_cli(
        "-r",
        (
            f'require "{HTTP}/bootstrap.php";'
            "dilitant_http_require_bootstrap();"
            'echo "PASS: HTTP Bootstrap\\n";'
        ),
    )

    context.report(
        output
    )

    require_output(
        output,
        "PASS: HTTP Bootstrap",
    )

    context.assert_manifest(
        context.initial_manifest
    )

    context.assert_baseline()

    context.assert_shared_context()