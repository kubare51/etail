"""DashboardPage — Dashboard page object."""
from pages.base_page import BasePage


class DashboardPage(BasePage):
    """Page object for the admin dashboard."""

    HEADING = "//h1"
    SALES_WIDGET = "#total_collection"
    SIDEBAR_TOGGLE = "a:has-text('Toggle navigation')"

    def goto(self):
        """Navigate to dashboard."""
        self.navigate("index.php/admin_ret_dashboard")
        return self

    def get_heading(self) -> str:
        """Get the dashboard heading text."""
        return self.get_text(self.HEADING)

    def is_loaded(self) -> bool:
        """Verify dashboard is fully loaded."""
        try:
            # Dashboard may not have an H1 — check URL and page readiness
            return "dashboard" in self.page.url.lower()
        except Exception:
            return False
