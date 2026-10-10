"""
Testing Plugin — Backend
Run PHPUnit and Jest tests with real-time output streaming.

Endpoints:
  GET    /status                 — Discover available test suites
  POST   /run                    — Start a test run (async)
  POST   /run/{run_id}/cancel    — Cancel a running test
  GET    /runs                   — List recent test runs
  GET    /results/{run_id}       — Get test run summary
  GET    /results/{run_id}/output — Get full test output
"""
from fastapi import APIRouter, HTTPException, Query
from pydantic import BaseModel, Field
from typing import Optional

from ...config import get_settings
from ...core.plugin_system import BackendPlugin
from .service import TestService, RunnerType


class RunTestRequest(BaseModel):
    """Request body for starting a test run."""
    runner: RunnerType = Field(..., description="Test runner to use (phpunit or jest)")
    filter: Optional[str] = Field(None, description="Filter pattern (test name or regex)")
    file: Optional[str] = Field(None, description="Specific test file to run")
    coverage: bool = Field(False, description="Generate coverage report")


class TestingPlugin(BackendPlugin):
    """Test Runner backend plugin — real subprocess management."""

    def __init__(self):
        settings = get_settings()
        self._service = TestService(settings.etail_root / "admin" / "tests")

    @property
    def id(self) -> str:
        return "testing"

    @property
    def name(self) -> str:
        return "Test Runner"

    def get_router(self) -> APIRouter:
        router = APIRouter(tags=["Testing"])
        svc = self._service

        @router.get("/status", summary="Discover test suites")
        async def get_status():
            """Discover available test suites (PHPUnit, Jest) and their test files."""
            return {
                "suites": svc.discover_suites(),
                "active_runs": [
                    r.to_dict() for r in svc._runs.values()
                    if r.status.value == "running"
                ],
            }

        @router.post("/run", summary="Start test run")
        async def run_tests(req: RunTestRequest):
            """Start a test run asynchronously. Subscribe to WS channel
            `test:{run_id}` for real-time output streaming."""
            result = await svc.run_tests(
                runner=req.runner,
                filter_pattern=req.filter,
                file=req.file,
                coverage=req.coverage,
            )
            return {
                "run_id": result.id,
                "status": result.status.value,
                "runner": result.runner.value,
                "ws_channel": f"test:{result.id}",
                "message": "Test run started. Connect to ws_channel for live output.",
            }

        @router.post("/run/{run_id}/cancel", summary="Cancel test run")
        async def cancel_run(run_id: str):
            """Cancel a running test execution."""
            cancelled = await svc.cancel_run(run_id)
            if not cancelled:
                raise HTTPException(404, f"No active run found: {run_id}")
            return {"run_id": run_id, "status": "cancelled"}

        @router.get("/runs", summary="List recent runs")
        async def list_runs(
            limit: int = Query(20, ge=1, le=50, description="Max runs to return"),
        ):
            """List recent test runs, newest first."""
            return svc.list_runs(limit=limit)

        @router.get("/results/{run_id}", summary="Get run results")
        async def get_results(run_id: str):
            """Get test run summary and parsed results."""
            run = svc.get_run(run_id)
            if not run:
                raise HTTPException(404, f"Run not found: {run_id}")
            return run.to_dict()

        @router.get("/results/{run_id}/detail", summary="Get detailed results")
        async def get_detail(run_id: str):
            """Get full test run detail including output and individual test cases."""
            run = svc.get_run(run_id)
            if not run:
                raise HTTPException(404, f"Run not found: {run_id}")
            return run.to_detail_dict()

        @router.get("/results/{run_id}/output", summary="Get raw output")
        async def get_output(run_id: str):
            """Get the raw output lines from a test run."""
            output = svc.get_run_output(run_id)
            if output is None:
                raise HTTPException(404, f"Run not found: {run_id}")
            return {"run_id": run_id, "output": output}

        return router

    def get_mcp_tools(self) -> list[dict]:
        return [
            {
                "name": "run_tests",
                "description": "Run PHPUnit or Jest tests with optional filter",
                "parameters": {
                    "runner": "string (required: phpunit | jest)",
                    "filter": "string (optional: test name filter)",
                    "file": "string (optional: specific test file)",
                    "coverage": "boolean (optional: generate coverage)",
                },
            },
            {
                "name": "get_test_status",
                "description": "Discover available test suites and active runs",
                "parameters": {},
            },
            {
                "name": "get_test_results",
                "description": "Get results of a specific test run by ID",
                "parameters": {"run_id": "string (required)"},
            },
        ]
