"""
BasePage — Foundation for all Page Objects.

Replaces Sparqla's Function_Call class with Playwright-native methods.
All page objects inherit from this class.

Playwright auto-waits, so no sleep() calls needed.
"""
import re
import os
import logging
from datetime import datetime
from playwright.sync_api import Page, expect, Locator

logger = logging.getLogger(__name__)

BASE_URL = os.getenv("BASE_URL", "http://localhost/etail_v3/admin")
SCREENSHOTS_DIR = os.path.join(os.path.dirname(__file__), "..", "reports", "screenshots")


class BasePage:
    """Base class for all page objects."""

    def __init__(self, page: Page):
        self.page = page

    # ── Navigation ────────────────────────────────────────────────────────────

    def navigate(self, path: str = ""):
        """Navigate to a path relative to BASE_URL."""
        url = f"{BASE_URL}/{path.lstrip('/')}" if path else BASE_URL
        self.page.goto(url, wait_until="networkidle")
        logger.info(f"Navigated to: {url}")

    def navigate_via_menu(self, menu_text: str, submenu_text: str = None, fallback_path: str = None):
        """
        Navigate using the sidebar menu (Sparqla's primary navigation pattern).
        Falls back to direct URL if menu click fails.
        """
        try:
            # Open sidebar if collapsed
            toggle = self.page.locator("a:has-text('Toggle navigation')")
            if toggle.is_visible():
                toggle.click()
                self.page.wait_for_timeout(500)

            # Click menu item
            self.page.locator(f"//span[contains(text(), '{menu_text}')]").click()

            if submenu_text:
                self.page.locator(f"//span[contains(text(), '{submenu_text}')]").click()

            self.page.wait_for_load_state("networkidle")
            logger.info(f"Menu navigation: {menu_text} → {submenu_text}")

        except Exception as e:
            if fallback_path:
                logger.warning(f"Menu nav failed ({e}), using fallback URL: {fallback_path}")
                self.navigate(fallback_path)
            else:
                raise

    # ── Click ─────────────────────────────────────────────────────────────────

    def click(self, selector: str):
        """
        Click an element. Playwright auto-waits for visibility and actionability.
        Replaces Sparqla's Function_Call.click() 3-level fallback.
        """
        locator = self._resolve_locator(selector)
        locator.scroll_into_view_if_needed()
        locator.click()
        logger.debug(f"Clicked: {selector}")

    def force_click(self, selector: str):
        """JS click — for elements blocked by overlays or sticky headers."""
        locator = self._resolve_locator(selector)
        locator.evaluate("el => el.click()")
        logger.debug(f"Force clicked (JS): {selector}")

    # ── Fill ──────────────────────────────────────────────────────────────────

    def fill(self, selector: str, value: str):
        """Fill an input field. Auto-clears first."""
        if value is None:
            return
        locator = self._resolve_locator(selector)
        locator.fill(str(value))
        logger.debug(f"Filled {selector}: {value}")

    def fill_and_validate(
        self,
        selector: str,
        value: str,
        field_name: str,
        pattern: str = None,
        range_check=None,
        date_range: str = None,
    ) -> dict:
        """
        Fill input with validation — replaces Sparqla's Function_Call.fill_input().

        Returns:
            dict: {'status': 'pass'|'fail', 'message': str, 'value': str}
        """
        if value is None:
            return {"status": "pass", "message": "Skipped (None)", "value": ""}

        value_str = str(value)
        if isinstance(value, datetime):
            value_str = value.strftime("%d-%m-%Y")

        locator = self._resolve_locator(selector)
        locator.fill(value_str)

        # Read back entered value
        entered = locator.input_value()

        # Empty check
        if not entered or entered == "0":
            self.screenshot(f"validation_fail_{field_name}")
            return {
                "status": "fail",
                "message": f"{value_str} → Not allowed in {field_name}",
                "value": entered,
            }

        # Regex validation
        if pattern and not re.fullmatch(pattern, entered):
            self.screenshot(f"validation_fail_{field_name}")
            return {
                "status": "fail",
                "message": f"'{entered}' → Invalid in {field_name}",
                "value": entered,
            }

        # Range check
        if range_check:
            try:
                if not range_check(float(entered)):
                    return {
                        "status": "fail",
                        "message": f"'{entered}' → Out of range for {field_name}",
                        "value": entered,
                    }
            except (ValueError, TypeError):
                return {
                    "status": "fail",
                    "message": f"'{entered}' → Not numeric for {field_name}",
                    "value": entered,
                }

        # Date range validation
        if date_range:
            result = self._validate_date(entered, field_name, date_range)
            if result:
                return result

        logger.debug(f"Validated {field_name}: '{entered}' ✅")
        return {"status": "pass", "message": "OK", "value": entered}

    # ── Select2 Dropdown ──────────────────────────────────────────────────────

    def select2(self, trigger_selector: str, value: str, search_selector: str = None):
        """
        Select2 dropdown — replaces Sparqla's Function_Call.dropdown_select().

        Args:
            trigger_selector: The span/element that opens the dropdown
            value: Text to search and select
            search_selector: Search input inside dropdown (auto-detected if not provided)
        """
        self.click(trigger_selector)

        # Default search input for Select2
        if not search_selector:
            search_selector = '//span[@class="select2-search select2-search--dropdown"]/input'

        search = self._resolve_locator(search_selector)
        search.fill(value)
        self.page.wait_for_timeout(500)  # Let results load

        # Click the matching option
        option = self.page.locator(f".select2-results__option:has-text('{value}')").first
        option.click()
        logger.debug(f"Select2: selected '{value}'")

    def select_native(self, selector: str, value: str):
        """Native <select> dropdown — by visible text."""
        locator = self._resolve_locator(selector)
        locator.select_option(label=value)
        logger.debug(f"Native select: {value}")

    # ── Autocomplete ──────────────────────────────────────────────────────────

    def autocomplete(self, selector: str, value: str, by_id: bool = True):
        """
        Autocomplete field — replaces Sparqla's fill_autocomplete_field().

        Types value, waits for suggestions, clicks matching suggestion.
        """
        if by_id:
            locator = self.page.locator(f"#{selector}")
        else:
            locator = self._resolve_locator(selector)

        locator.click()
        locator.fill("")
        locator.type(str(value), delay=50)
        self.page.wait_for_timeout(1000)  # Wait for suggestions

        # Click suggestion
        suggestion = self.page.locator(f"//li[contains(text(), '{value}')]").first
        suggestion.click(timeout=5000)
        logger.debug(f"Autocomplete: selected '{value}'")

    # ── Date Field ────────────────────────────────────────────────────────────

    def fill_date(self, selector: str, date_value: str):
        """
        Fill a date field using JS bypass — replaces Sparqla's datepicker workaround.
        Datepickers block normal input, so we set value directly + dispatch events.
        """
        locator = self._resolve_locator(selector)
        locator.evaluate(
            """(el, val) => {
                el.value = val;
                el.dispatchEvent(new Event('change', {bubbles: true}));
                el.dispatchEvent(new Event('blur', {bubbles: true}));
            }""",
            date_value,
        )
        logger.debug(f"Date filled: {date_value}")

    # ── Toast / Alert ─────────────────────────────────────────────────────────

    def get_toast_message(self, timeout: int = 5000) -> str:
        """
        Capture toast notification text — replaces Sparqla's alert1/alert2/alert6.
        """
        try:
            toast = self.page.locator("#toaster .message").first
            toast.wait_for(state="visible", timeout=timeout)
            text = toast.inner_text().strip()
            logger.info(f"Toast: {text}")
            return text
        except Exception:
            return ""

    def click_and_get_toast(self, click_selector: str, timeout: int = 5000) -> str:
        """Click a button and capture the resulting toast message."""
        self.click(click_selector)
        return self.get_toast_message(timeout)

    def dismiss_alert(self) -> str:
        """Handle native JS alert — accept and return text."""
        try:
            dialog_text = []
            self.page.on("dialog", lambda d: (dialog_text.append(d.message), d.accept()))
            self.page.wait_for_timeout(1000)
            return dialog_text[0] if dialog_text else ""
        except Exception:
            return ""

    # ── DataTable ─────────────────────────────────────────────────────────────

    def datatable_search(self, table_id: str, search_text: str) -> bool:
        """
        Search a DataTable and verify the search text appears in results.
        Replaces Sparqla's post-save verification pattern.
        """
        search_input = self.page.locator(f"#{table_id}_wrapper input[type='search']")
        search_input.fill("")
        search_input.fill(str(search_text))
        self.page.wait_for_timeout(1500)  # DataTable debounce

        first_row = self.page.locator(f"#{table_id} tbody tr:first-child")
        row_text = first_row.inner_text()
        found = str(search_text) in row_text
        logger.info(f"DataTable search '{search_text}': {'Found ✅' if found else 'Not found ❌'}")
        return found

    # ── New Tab Capture ───────────────────────────────────────────────────────

    def click_and_capture_new_tab(self, click_selector: str) -> str:
        """
        Click button, wait for new tab, capture ID from URL, close new tab.
        Replaces Sparqla's Pattern A (RepairOrder, StockIssue, Billing).
        """
        with self.page.context.expect_page() as new_page_info:
            self.click(click_selector)

        new_page = new_page_info.value
        new_page.wait_for_load_state("domcontentloaded")
        captured_url = new_page.url
        captured_id = captured_url.rstrip("/").split("/")[-1]
        new_page.close()

        logger.info(f"Captured ID from new tab: {captured_id}")
        return captured_id

    # ── Read Values ───────────────────────────────────────────────────────────

    def get_text(self, selector: str) -> str:
        """Get visible text of an element."""
        return self._resolve_locator(selector).inner_text().strip()

    def get_value(self, selector: str) -> str:
        """Get input value of an element."""
        return self._resolve_locator(selector).input_value().strip()

    def get_float(self, selector: str, default: float = 0.0) -> float:
        """Get numeric value from an element, handling commas."""
        try:
            raw = self.get_value(selector) or self.get_text(selector)
            return float(raw.replace(",", "").strip()) if raw else default
        except (ValueError, TypeError):
            return default

    # ── File Upload ───────────────────────────────────────────────────────────

    def upload_file(self, selector: str, file_path: str):
        """Upload a file to a file input."""
        locator = self._resolve_locator(selector)
        locator.set_input_files(file_path)
        logger.debug(f"Uploaded: {file_path}")

    # ── Screenshot ────────────────────────────────────────────────────────────

    def screenshot(self, name: str = None) -> str:
        """Take a screenshot. Returns the file path."""
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        filename = f"{name or 'screenshot'}_{timestamp}.png"
        path = os.path.join(SCREENSHOTS_DIR, filename)
        self.page.screenshot(path=path, full_page=True)
        logger.info(f"Screenshot: {path}")
        return path

    # ── Radio Buttons ─────────────────────────────────────────────────────────

    def select_radio(self, label_map: dict, value_map: dict, label: str):
        """
        Select radio button using label→value→id mapping.
        Replaces Sparqla's radio map pattern.

        Example:
            label_map = {"Customer": "3", "Stock": "4"}
            value_map = {"3": "cus_repair_order", "4": "stock_repair_order"}
            select_radio(label_map, value_map, "Customer")
        """
        val = label_map.get(str(label).strip(), str(label).strip())
        element_id = value_map.get(val)
        if element_id:
            self.click(f"#{element_id}")

    # ── Wait Helpers ──────────────────────────────────────────────────────────

    def wait_for_url(self, pattern: str, timeout: int = 15000):
        """Wait for URL to match a pattern."""
        self.page.wait_for_url(f"**{pattern}**", timeout=timeout)

    def wait_for_network_idle(self, timeout: int = 10000):
        """Wait for network to become idle."""
        self.page.wait_for_load_state("networkidle", timeout=timeout)

    # ── Private Helpers ───────────────────────────────────────────────────────

    def _resolve_locator(self, selector: str) -> Locator:
        """Resolve a selector to a Playwright Locator. Supports XPath and CSS."""
        if selector.startswith("//") or selector.startswith("(//"):
            return self.page.locator(f"xpath={selector}")
        elif selector.startswith("#") or selector.startswith("."):
            return self.page.locator(selector)
        else:
            return self.page.locator(selector)

    def _validate_date(self, entered: str, field_name: str, date_range: str):
        """Validate date against range constraint."""
        from datetime import datetime as dt

        formats = ["%d-%m-%Y", "%Y-%m-%d", "%d/%m/%Y"]
        entered_date = None
        for fmt in formats:
            try:
                entered_date = dt.strptime(entered.strip(), fmt).date()
                break
            except ValueError:
                continue

        if not entered_date:
            return {"status": "fail", "message": f"{field_name}: invalid date format", "value": entered}

        today = dt.today().date()
        checks = {
            "future": entered_date > today,
            "past": entered_date < today,
            "current": entered_date == today,
            "today": entered_date == today,
            "future_or_current": entered_date >= today,
            "past_or_current": entered_date <= today,
        }

        if date_range in checks and not checks[date_range]:
            return {
                "status": "fail",
                "message": f"{field_name} must be {date_range} date → {entered}",
                "value": entered,
            }
        return None
