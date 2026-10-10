"""
Global pytest fixtures for Playwright E2E tests.

Provides:
    - Browser/page setup with configurable options
    - Authenticated page (logged in)
    - Test data loader
    - Screenshot/video on failure
    - Database connection for verification
"""
import os
import sys
import pytest
import yaml
from pathlib import Path
from datetime import datetime
from dotenv import load_dotenv

# Ensure tests/ root is on path so `from pages.xxx import` works
sys.path.insert(0, str(Path(__file__).parent))

from playwright.sync_api import sync_playwright, Page, Browser, BrowserContext

# Load environment
load_dotenv(Path(__file__).parent / ".env")

# ── Constants ─────────────────────────────────────────────────────────────────

BASE_URL = os.getenv("BASE_URL", "http://localhost/etail_v3/admin")
E2E_USER = os.getenv("E2E_USERNAME", "admin")
E2E_PASS = os.getenv("E2E_PASSWORD", "admin123")
HEADLESS = os.getenv("HEADLESS", "true").lower() == "true"
SLOW_MO = int(os.getenv("SLOW_MO", "0"))
BROWSER_TYPE = os.getenv("BROWSER", "chromium")
SCREENSHOT_ON_FAILURE = os.getenv("SCREENSHOT_ON_FAILURE", "true").lower() == "true"
VIDEO_ON_FAILURE = os.getenv("VIDEO_ON_FAILURE", "true").lower() == "true"
TRACE_ON_FAILURE = os.getenv("TRACE_ON_FAILURE", "true").lower() == "true"

REPORTS_DIR = Path(__file__).parent / "reports"
SCREENSHOTS_DIR = REPORTS_DIR / "screenshots"
VIDEOS_DIR = REPORTS_DIR / "videos"
TRACES_DIR = REPORTS_DIR / "traces"
DATA_DIR = Path(__file__).parent / "data"

# Ensure directories exist
for d in [REPORTS_DIR, SCREENSHOTS_DIR, VIDEOS_DIR, TRACES_DIR]:
    d.mkdir(parents=True, exist_ok=True)


# ── Fixtures ──────────────────────────────────────────────────────────────────

@pytest.fixture(scope="session")
def playwright_instance():
    """Session-scoped Playwright instance."""
    with sync_playwright() as p:
        yield p


@pytest.fixture(scope="session")
def browser(playwright_instance) -> Browser:
    """Session-scoped browser instance."""
    launcher = getattr(playwright_instance, BROWSER_TYPE)
    browser = launcher.launch(
        headless=HEADLESS,
        slow_mo=SLOW_MO,
        args=[
            "--no-sandbox",
            "--disable-dev-shm-usage",
            "--disable-extensions",
            "--use-fake-device-for-media-stream",
            "--use-fake-ui-for-media-stream",
        ],
    )
    yield browser
    browser.close()


@pytest.fixture(scope="function")
def context(browser) -> BrowserContext:
    """Function-scoped browser context (isolated cookies/storage per test)."""
    ctx = browser.new_context(
        viewport={"width": 1920, "height": 1080},
        ignore_https_errors=True,
        record_video_dir=str(VIDEOS_DIR) if VIDEO_ON_FAILURE else None,
    )
    if TRACE_ON_FAILURE:
        ctx.tracing.start(screenshots=True, snapshots=True, sources=True)
    yield ctx
    ctx.close()


@pytest.fixture(scope="function")
def page(context) -> Page:
    """Function-scoped page — fresh for each test."""
    pg = context.new_page()
    pg.set_default_timeout(30000)  # 30s default timeout
    pg.set_default_navigation_timeout(60000)  # 60s for navigation
    yield pg
    pg.close()


@pytest.fixture(scope="session")
def auth_storage_state(browser, tmp_path_factory) -> str:
    """
    Session-scoped: logs in once, saves storage state.
    All tests needing auth reuse this state (no repeated logins).
    """
    storage_path = str(tmp_path_factory.mktemp("auth") / "state.json")

    context = browser.new_context(
        viewport={"width": 1920, "height": 1080},
        ignore_https_errors=True,
    )
    page = context.new_page()
    page.set_default_timeout(30000)

    # Login flow — AJAX-based (form submits via JS → $.ajax → redirect)
    page.goto(f"{BASE_URL}/index.php/chit_admin/login", wait_until="networkidle")
    page.fill("#username", E2E_USER)
    page.fill("#password", E2E_PASS)
    page.click("#submit_login")

    # Wait for AJAX login to complete and redirect to dashboard
    try:
        page.wait_for_url("**/dashboard**", timeout=30000)
    except Exception:
        # Some setups may have OTP or company select — wait a bit more
        page.wait_for_timeout(5000)

    # Save authenticated state
    context.storage_state(path=storage_path)
    page.close()
    context.close()

    return storage_path


@pytest.fixture(scope="function")
def auth_context(browser, auth_storage_state) -> BrowserContext:
    """Function-scoped authenticated browser context."""
    ctx = browser.new_context(
        viewport={"width": 1920, "height": 1080},
        ignore_https_errors=True,
        storage_state=auth_storage_state,
        record_video_dir=str(VIDEOS_DIR) if VIDEO_ON_FAILURE else None,
    )
    if TRACE_ON_FAILURE:
        ctx.tracing.start(screenshots=True, snapshots=True, sources=True)
    yield ctx
    ctx.close()


@pytest.fixture(scope="function")
def auth_page(auth_context) -> Page:
    """Function-scoped authenticated page — already logged in."""
    pg = auth_context.new_page()
    pg.set_default_timeout(30000)
    pg.set_default_navigation_timeout(60000)
    yield pg
    pg.close()


# ── Data Loading ──────────────────────────────────────────────────────────────

@pytest.fixture(scope="session")
def load_test_data():
    """Factory fixture: load_test_data('billing') -> list of test case dicts."""
    def _loader(module_name: str) -> list:
        yaml_path = DATA_DIR / f"{module_name}.yml"
        if not yaml_path.exists():
            pytest.skip(f"No test data found: {yaml_path}")
        with open(yaml_path, "r", encoding="utf-8") as f:
            data = yaml.safe_load(f)
        return data.get("test_cases", [])
    return _loader


# ── Failure Hooks ─────────────────────────────────────────────────────────────

@pytest.hookimpl(hookwrapper=True)
def pytest_runtest_makereport(item, call):
    """Capture screenshot, video, and trace on test failure."""
    outcome = yield
    report = outcome.get_result()

    if report.when == "call" and report.failed:
        # Try to get the page from the test's fixtures
        page = None
        for fixture_name in ["auth_page", "page"]:
            if fixture_name in item.funcargs:
                page = item.funcargs[fixture_name]
                break

        if page and SCREENSHOT_ON_FAILURE:
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            test_name = item.name.replace("[", "_").replace("]", "")
            screenshot_path = SCREENSHOTS_DIR / f"{test_name}_{timestamp}.png"
            try:
                page.screenshot(path=str(screenshot_path), full_page=True)
                # Attach to HTML report
                if hasattr(report, "extra"):
                    report.extra = getattr(report, "extra", [])
                    report.extra.append(
                        pytest.html.extras.image(str(screenshot_path))
                    )
            except Exception:
                pass

        # Save trace on failure
        if TRACE_ON_FAILURE:
            for fixture_name in ["auth_context", "context"]:
                if fixture_name in item.funcargs:
                    ctx = item.funcargs[fixture_name]
                    trace_path = TRACES_DIR / f"{item.name}_{datetime.now().strftime('%H%M%S')}.zip"
                    try:
                        ctx.tracing.stop(path=str(trace_path))
                    except Exception:
                        pass
                    break


# ── Database ──────────────────────────────────────────────────────────────────

@pytest.fixture(scope="session")
def db_connection():
    """Session-scoped database connection for verification queries."""
    try:
        import mysql.connector
        conn = mysql.connector.connect(
            host=os.getenv("DB_HOST", "127.0.0.1"),
            port=int(os.getenv("DB_PORT", "3307")),
            user=os.getenv("DB_USER", "retaillmx_admin"),
            password=os.getenv("DB_PASSWORD", ""),
            database=os.getenv("DB_NAME", "retail_dev"),
            connect_timeout=10,
        )
        yield conn
        conn.close()
    except Exception:
        yield None


# ── Helpers ───────────────────────────────────────────────────────────────────

@pytest.fixture(scope="session")
def base_url():
    """Base URL for the application."""
    return BASE_URL
