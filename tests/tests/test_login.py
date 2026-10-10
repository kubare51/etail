"""
Login E2E Tests

Migrated from: Sparqla/Test_login/Login.py
Tests: Valid login, invalid password, empty credentials
"""
import pytest
from pages.login_page import LoginPage
from pages.dashboard_page import DashboardPage


@pytest.mark.smoke
class TestLogin:
    """Login tests — P0 priority."""

    def test_valid_login(self, page, load_test_data, base_url):
        """TC_LOGIN_001: Valid admin login should reach dashboard."""
        import os

        login_page = LoginPage(page)
        login_page.goto()
        # Use .env credentials (actual working credentials, not YAML hardcoded)
        login_page.login(
            os.getenv("E2E_USERNAME", "admin"),
            os.getenv("E2E_PASSWORD", "admin123")
        )

        assert login_page.is_dashboard_visible(), "Dashboard should be visible after valid login"
        login_page.screenshot("login_success")

    def test_invalid_password(self, page, load_test_data, base_url):
        """TC_LOGIN_002: Invalid password should show error."""
        test_cases = load_test_data("login")
        tc = next(t for t in test_cases if t["id"] == "TC_LOGIN_002")

        login_page = LoginPage(page)
        login_page.goto()
        login_page.login(tc["username"], tc["password"])

        assert not login_page.is_dashboard_visible(), "Should NOT reach dashboard with wrong password"

    def test_empty_credentials(self, page, load_test_data, base_url):
        """TC_LOGIN_003: Empty credentials should not login."""
        test_cases = load_test_data("login")
        tc = next(t for t in test_cases if t["id"] == "TC_LOGIN_003")

        login_page = LoginPage(page)
        login_page.goto()
        login_page.login(tc["username"], tc["password"])

        assert not login_page.is_dashboard_visible(), "Should NOT reach dashboard with empty credentials"

    def test_dashboard_loads_after_login(self, page, base_url):
        """Verify dashboard loads correctly after logging in."""
        import os

        login_page = LoginPage(page)
        login_page.goto()
        login_page.login(
            os.getenv("E2E_USERNAME", "admin"),
            os.getenv("E2E_PASSWORD", "admin123")
        )
        login_page.is_dashboard_visible()  # Wait for redirect

        dashboard = DashboardPage(page)
        assert dashboard.is_loaded(), "Dashboard should load after login"
