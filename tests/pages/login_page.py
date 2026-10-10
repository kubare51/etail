"""LoginPage — Login page object."""
from pages.base_page import BasePage


class LoginPage(BasePage):
    """Page object for the admin login page."""

    # Selectors
    USERNAME = "#username"
    PASSWORD = "#password"
    SUBMIT = "#submit_login"
    DASHBOARD_HEADING = "//h1"
    ERROR_MESSAGE = ".alert-danger"

    def goto(self):
        """Navigate to the login page."""
        self.navigate("index.php/chit_admin/login")
        return self

    def login(self, username: str, password: str) -> "LoginPage":
        """Fill credentials and submit."""
        self.fill(self.USERNAME, username)
        self.fill(self.PASSWORD, password)
        self.click(self.SUBMIT)
        return self

    def is_dashboard_visible(self) -> bool:
        """Check if we landed on the dashboard after login."""
        try:
            # AJAX login: JS posts credentials → on success, window.location.href redirects
            # This can take 10-15s depending on server load
            self.page.wait_for_url("**/dashboard**", timeout=30000)
            self.page.wait_for_load_state("networkidle")
            return True
        except Exception:
            return False

    def get_error_message(self) -> str:
        """Get login error message if present."""
        try:
            return self.page.locator(self.ERROR_MESSAGE).inner_text().strip()
        except Exception:
            return ""
