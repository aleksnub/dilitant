"""Управление жизненным циклом HTTP Environment."""

from __future__ import annotations

import sys
import tempfile

from pathlib import Path

from .config import EnvironmentConfig

from .docker import DockerEnvironment

from .errors import (
    CleanupError,
    ContainerError,
    EnvironmentError,
)

from .preflight import EnvironmentPreflight

from .process import (
    ProcessRunner,
    report_failure,
    report_pass,
)

from .routing import WordPressRouting

from .verification import EnvironmentVerifier


class EnvironmentLifecycle:
    def __init__(
        self,
        config: EnvironmentConfig,
    ) -> None:
        self.config = config

        self.process = ProcessRunner(
            default_timeout=config.command_timeout,
        )

        self.preflight = EnvironmentPreflight(
            config=config,
            process=self.process,
        )

        self.docker = DockerEnvironment(
            config=config,
            process=self.process,
        )

        self.verifier = EnvironmentVerifier(
            config=config,
            docker=self.docker,
        )

        self.routing = WordPressRouting(
            config=config,
            docker=self.docker,
        )

    def _read_created_container(
        self,
        cid_file: Path,
    ) -> str | None:
        if not cid_file.exists():
            return None

        container_id = (
            cid_file
            .read_text(encoding="utf-8")
            .strip()
        )

        self.docker.validate_id(
            container_id,
        )

        self.docker.verify_owner(
            container_id,
        )

        return container_id

    def _show_startup_logs(
        self,
        container_id: str,
    ) -> None:
        print(
            "\n=== HTTP TEST CONTAINER LOGS ===",
            file=sys.stderr,
            flush=True,
        )

        try:
            logs = self.docker.logs(
                container_id,
                tail=200,
            )

        except Exception as error:
            report_failure(
                f"Unable to read container logs: {error}"
            )

            return

        if logs:
            print(
                logs,
                file=sys.stderr,
                flush=True,
            )

        else:
            print(
                "Container logs are empty.",
                file=sys.stderr,
                flush=True,
            )

    def _verify_shutdown(self) -> None:
        if self.docker.find_container_id() is not None:
            raise CleanupError(
                "HTTP test container cleanup "
                "could not be confirmed."
            )

        try:
            self.preflight.verify_port(wait=True)

        except EnvironmentError as error:
            raise CleanupError(
                "HTTP test container is absent, "
                "but port release could not be confirmed: "
                f"{error}"
            ) from error

        if self.docker.find_container_id() is not None:
            raise CleanupError(
                "HTTP test container reappeared "
                "during shutdown verification."
            )

    def _cleanup_failed_start(
        self,
        container_id: str | None,
        cid_file: Path,
    ) -> None:
        if container_id is None:
            container_id = self._read_created_container(
                cid_file,
            )

        if container_id is None:
            if self.docker.find_container_id() is not None:
                raise CleanupError(
                    "A container exists, but ownership "
                    "by the current startup is unconfirmed."
                )

            return

        self._show_startup_logs(
            container_id,
        )

        print(
            "\n=== CONTAINER CLEANUP ===",
            file=sys.stderr,
            flush=True,
        )

        self.docker.remove(
            container_id,
        )

        self._verify_shutdown()

        report_pass(
            "Failed HTTP startup container removed"
        )

    def _verify_initialization_environment(
        self,
        container_id: str,
    ) -> None:
        self.docker.verify_owner(
            container_id,
        )

        if not self.docker.is_running(
            container_id,
        ):
            raise ContainerError(
                "HTTP test container is not running."
            )

        self.verifier.verify_mounts(
            container_id,
        )

        self.verifier.verify_private_storage(
            container_id,
        )

        self.verifier.verify_port_binding(
            container_id,
        )

        self.verifier.verify_network(
            container_id,
        )

        self.verifier.verify_apache(
            container_id,
        )

        self.verifier.verify_wordpress(
            container_id,
        )

        report_pass(
            "HTTP initialization environment isolation"
        )

    def _initialize_routing(
        self,
        container_id: str,
    ) -> None:
        print(
            "\nInitializing WordPress routing...",
            flush=True,
        )

        self.routing.initialize(
            container_id,
        )

        self.routing.verify(
            container_id,
        )

        report_pass(
            "HTTP test WordPress routing initialized"
        )

    def start(self) -> None:
        self.preflight.verify_dependencies()
        self.preflight.verify_environment()
        self.preflight.verify_source()
        self.preflight.verify_docker_environment()

        if self.docker.find_container_id() is not None:
            raise ContainerError(
                "HTTP test container already exists. "
                "Use status, check or stop."
            )

        self.preflight.verify_port()

        print(
            "\nStarting HTTP test container...",
            flush=True,
        )

        with tempfile.TemporaryDirectory(
            prefix="dilitant-http-",
        ) as directory:

            cid_file = (
                Path(directory)
                / "container.id"
            )

            container_id = None

            try:
                container_id = self.docker.create(
                    cid_file,
                )

                self.docker.start(
                    container_id,
                )

                self.verifier.wait_ready(
                    container_id,
                )

                self._verify_initialization_environment(
                    container_id,
                )

                self._initialize_routing(
                    container_id,
                )

                self.verifier.verify(
                    container_id,
                )

            except BaseException as startup_error:
                try:
                    self._cleanup_failed_start(
                        container_id,
                        cid_file,
                    )

                except BaseException as cleanup_error:
                    raise CleanupError(
                        "HTTP startup failed and cleanup "
                        "could not be confirmed.\n"
                        f"Startup error: {startup_error}\n"
                        f"Cleanup error: {cleanup_error}"
                    ) from cleanup_error

                raise

        report_pass(
            "HTTP-D01 environment started"
        )

        print(
            f"Container: {self.config.container_name}",
            flush=True,
        )

        print(
            f"URL: {self.config.base_url}",
            flush=True,
        )

    def check(self) -> None:
        container_id = (
            self.docker.require_container()
        )

        if not self.docker.is_running(
            container_id,
        ):
            raise ContainerError(
                "HTTP test container is not running."
            )

        self._verify_initialization_environment(
            container_id,
        )

        self.routing.verify(
            container_id,
        )

        self.verifier.verify(
            container_id,
        )

        report_pass(
            "HTTP test environment check completed"
        )

    def status(self) -> None:
        container_id = (
            self.docker.find_container_id()
        )

        if container_id is None:
            print(
                "HTTP test container: absent"
            )

            return

        record = self.docker.verify_owner(
            container_id,
        )

        state = record.get(
            "State",
            {},
        )

        print(
            f"Container: {record.get('Name')}"
        )

        print(
            f"ID: {container_id}"
        )

        print(
            f"Running: {state.get('Running')}"
        )

        print(
            f"Status: {state.get('Status')}"
        )

        print(
            f"Image: {record.get('Image')}"
        )

        print(
            f"URL: {self.config.base_url}"
        )

    def stop(self) -> None:
        container_id = (
            self.docker.find_container_id()
        )

        if container_id is None:
            report_pass(
                "HTTP test container is already absent"
            )

        else:
            self.docker.verify_owner(
                container_id,
            )

            self.docker.remove(
                container_id,
            )

            if self.docker.find_container_id() is not None:
                raise CleanupError(
                    "HTTP test container cleanup "
                    "could not be confirmed."
                )

            report_pass(
                "HTTP test container removed"
            )

        self._verify_shutdown()

        report_pass(
            "HTTP test shutdown completed"
        )

    def cli(
        self,
        arguments: list[str],
    ) -> int:
        if not arguments:
            raise EnvironmentError(
                "PHP arguments are required."
            )

        container_id = (
            self.docker.require_container()
        )

        result = self.docker.php(
            container_id,
            arguments,
            capture=False,
            check=False,
        )

        return result.returncode

    def logs(self) -> None:
        container_id = (
            self.docker.require_container()
        )

        logs = self.docker.logs(
            container_id,
            tail=200,
        )

        if logs:
            print(logs)

        else:
            print(
                "Container logs are empty."
            )