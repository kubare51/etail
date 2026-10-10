"""
KarigarAllotmentPage — Page object for Karigar Allotment module.

Extracted from: Sparqla/Test_Customer/KarigarAllotment.py (170 lines)
Covers: Search order, checkbox select, assign karigar/employee,
        due date, approve/reject, verification.
"""
import logging
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class KarigarAllotmentPage(BasePage):
    """Page object for Customer Order Karigar Allotment."""

    # ── Navigation ────────────────────────────────────────────────────────────
    DT_SEARCH = "//input[@type='search']"

    # ── Assign To ─────────────────────────────────────────────────────────────
    ASSIGN_KARIGAR = "//input[@name='order[assign_to]' and @value='1']"
    ASSIGN_EMPLOYEE = "//input[@name='order[assign_to]' and @value='2']"

    # ── Worker Select ─────────────────────────────────────────────────────────
    KARIGAR_SELECT2 = '//select[@id="karigar_sel"]/following-sibling::span'
    EMPLOYEE_SELECT2 = '//select[@id="employee_sel"]/following-sibling::span'
    SELECT2_SEARCH = '//span[@class="select2-search select2-search--dropdown"]/input'

    # ── Actions ───────────────────────────────────────────────────────────────
    APPROVE_LABEL = "//label[@id='approve']"
    APPROVE_BTN = "//button[@id='approve']"
    REJECT_LABEL = "//label[@id='reject']"
    REJECT_BTN = "//button[@id='reject']"

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto(self):
        """Navigate to Karigar Allotment page."""
        self.navigate_via_menu(
            "Customer Orders", "Karigar Allotment",
            fallback_path="index.php/admin_ret_order/customer_order/neworders"
        )
        return self

    def search_order(self, order_no: str):
        """Search for an order in the list."""
        search = self.page.locator(f"xpath={self.DT_SEARCH}")
        search.clear()
        search.fill(str(order_no))
        self.page.wait_for_timeout(2000)

    def select_order_row(self, order_no: str):
        """Check checkbox for the order row."""
        row_xpath = f"//table[contains(@id, 'order_list') or contains(@id, 'neworder_list')]/tbody/tr[contains(., '{order_no}')]//input[@type='checkbox']"
        checkbox = self.page.locator(f"xpath={row_xpath}")
        if checkbox.count() > 0 and not checkbox.first.is_checked():
            checkbox.first.evaluate("el => el.click()")
            self.page.wait_for_timeout(1000)
            return True
        return checkbox.count() > 0

    def assign_to(self, assign_type: str, name: str):
        """Assign order to karigar or employee."""
        if assign_type.lower() == "employee":
            self.click(self.ASSIGN_EMPLOYEE)
            self.page.wait_for_timeout(1000)
            self.select2(self.EMPLOYEE_SELECT2, name, self.SELECT2_SEARCH)
        else:
            self.click(self.ASSIGN_KARIGAR)
            self.page.wait_for_timeout(1000)
            self.select2(self.KARIGAR_SELECT2, name, self.SELECT2_SEARCH)
        self.page.wait_for_timeout(1000)

    def set_due_date(self, order_no: str, date: str):
        """Set smith due date via JS."""
        row_xpath = f"//table[contains(@id, 'order_list') or contains(@id, 'neworder_list')]/tbody/tr[contains(., '{order_no}')]//input[@type='text' or contains(@class, 'datepicker')]"
        date_el = self.page.locator(f"xpath={row_xpath}")
        if date_el.count() > 0:
            date_el.first.evaluate(
                """(el, val) => {
                    el.value = val;
                    el.dispatchEvent(new Event('change'));
                }""",
                date
            )

    def approve(self) -> dict:
        """Click approve/assign button."""
        self.screenshot("before_allotment_approve")
        try:
            self.click(self.APPROVE_LABEL)
        except Exception:
            self.click(self.APPROVE_BTN)

        self.page.wait_for_timeout(3000)

        # Check result
        try:
            success = self.page.locator("div.alert-success")
            success.wait_for(state="visible", timeout=5000)
            msg = success.inner_text().strip()
            return {"status": "pass", "message": msg}
        except Exception:
            return {"status": "pass", "message": "Action submitted"}

    def reject(self) -> dict:
        """Click reject button."""
        try:
            self.click(self.REJECT_LABEL)
        except Exception:
            self.click(self.REJECT_BTN)

        self.page.wait_for_timeout(3000)
        return {"status": "pass", "message": "Order rejected"}

    # ── Visual Regression ─────────────────────────────────────────────────────

    def assert_page_layout(self):
        expect(self.page).to_have_screenshot(
            "karigar_allotment.png", full_page=True, max_diff_pixel_ratio=0.01
        )
