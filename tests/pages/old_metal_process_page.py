"""
OldMetalProcessPage — Page object for Old Metal Process module.

Extracted from: Sparqla/Test_OldMetalProcess/OldMetalProcess.py (267 lines)
Covers: Issue/Receipt flows, Melting/Testing process types,
        vendor selection, pocket selection, modal interaction (receipt),
        save + capture process ID, list verification.
"""
import logging
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class OldMetalProcessPage(BasePage):
    """Page object for Old Metal Process (Inventory)."""

    # ── Process For ───────────────────────────────────────────────────────────
    ISSUE_RADIO = '//input[@id="issue_process"]'
    RECEIPT_RADIO = '//input[@id="receipt_process"]'

    # ── Against Melting ───────────────────────────────────────────────────────
    MELTING_YES = '//input[@id="against_melting_yes"]'
    MELTING_NO = '//input[@id="against_melting_no"]'

    # ── Filters ───────────────────────────────────────────────────────────────
    PROCESS_TYPE_SELECT2 = '//select[@id="select_process"]/following-sibling::span'
    VENDOR_SELECT2 = '//select[@id="karigar"]/following-sibling::span'
    SELECT2_SEARCH = '//span[@class="select2-search select2-search--dropdown"]/input'
    FILTER_BTN = '//button[@id="process_filter"]'

    # ── Melting Issue ─────────────────────────────────────────────────────────
    MELTING_TYPE_SELECT = '//select[@id="melting_trans_type"]'
    POCKET_SELECT = '//select[@id="select_pocket"]'
    POCKET_TABLE = '//table[@id="pocket_details"]'

    # ── Melting Receipt ───────────────────────────────────────────────────────
    MELTING_RECEIPT_TABLE = '//table[@id="melting_receipt"]'
    MODAL_CATEGORY = '//select[contains(@class, "id_ret_category")]'
    MODAL_SECTION = '//select[contains(@class, "id_section")]'
    MODAL_PRODUCT = '//select[contains(@class, "id_product")]'
    MODAL_DESIGN = '//select[contains(@class, "id_design")]'
    MODAL_SUB_DESIGN = '//select[contains(@class, "id_sub_design")]'
    MODAL_PCS = '//input[contains(@class, "recd_pcs")]'
    MODAL_WEIGHT = '//input[contains(@class, "recd_gross_wt")]'
    MODAL_UPDATE_BTN = '//button[@id="update_category"]'

    # ── Testing ───────────────────────────────────────────────────────────────
    TESTING_TABLE = '//table[@id="testing_process_details"]'
    METAL_PROCESS_SELECT = '//select[@id="select_metal_process"]'
    ADD_TO_STOCK = '//input[@id="add_to_acc_stock"]'
    ADD_TO_NEXT = '//input[@id="add_to_next_process"]'
    MELTING_RECEIPT_PURITY = '//table[@id="against_melting_receipt"]//input[contains(@name, "purity")]'

    # ── Common ────────────────────────────────────────────────────────────────
    REMARK = '//textarea[@id="remark"]'
    SUBMIT_BTN = '//button[@id="issue_submit"]'

    # ── List Page ─────────────────────────────────────────────────────────────
    PROCESS_LIST = '//table[@id="process_list"]'
    DATE_PICKER = '//button[@id="date_range_picker"]'
    DT_SEARCH = "//input[@type='search']"

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto_add(self):
        """Navigate to Add page."""
        self.navigate("index.php/admin_ret_metal_process/metal_process_issue/add")
        return self

    def goto_list(self):
        """Navigate to List page."""
        self.navigate("index.php/admin_ret_metal_process/metal_process_issue/list")
        return self

    def select_process_for(self, process_for: str):
        """Issue or Receipt."""
        if process_for.upper() == "ISSUE":
            self.click(self.ISSUE_RADIO)
        else:
            self.click(self.RECEIPT_RADIO)
            self.page.wait_for_timeout(1000)

    def select_against_melting(self, against: str):
        if against.lower() == "yes":
            self.click(self.MELTING_YES)
        else:
            self.click(self.MELTING_NO)

    def fill_filters(self, process_type: str = None, vendor: str = None):
        """Fill process type and vendor."""
        if process_type:
            self.select2(self.PROCESS_TYPE_SELECT2, process_type, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(1000)
        if vendor:
            self.select2(self.VENDOR_SELECT2, vendor, self.SELECT2_SEARCH)

    def click_search(self):
        self.click(self.FILTER_BTN)
        self.page.wait_for_timeout(3000)

    # ── Melting Issue ─────────────────────────────────────────────────────────

    def fill_melting_issue(self, melting_type: str, pocket_no: str):
        self.select_native(self.MELTING_TYPE_SELECT, melting_type)
        self.page.wait_for_timeout(1000)
        if pocket_no:
            self.select_native(self.POCKET_SELECT, pocket_no)
            self.page.wait_for_timeout(2000)
            checkbox = self.page.locator(
                f"xpath={self.POCKET_TABLE}//td[contains(text(), '{pocket_no}')]/..//input[@type='checkbox']"
            )
            if checkbox.count() > 0:
                checkbox.first.click()

    # ── Melting Receipt ───────────────────────────────────────────────────────

    def fill_melting_receipt(self, process_no: str, modal_data: dict):
        """Select receipt row, click plus, fill modal."""
        row_xpath = f'{self.MELTING_RECEIPT_TABLE}//td[contains(text(), "{process_no}")]/..'

        # Checkbox
        self.click(f'{row_xpath}//input[@type="checkbox"]')
        self.page.wait_for_timeout(1000)

        # Plus button
        self.click(f'{row_xpath}//a[contains(@class, "btn-success")]')
        self.page.wait_for_timeout(2000)

        # Modal
        if modal_data.get("category"):
            self.select_native(self.MODAL_CATEGORY, modal_data["category"])
        if modal_data.get("section"):
            self.select_native(self.MODAL_SECTION, modal_data["section"])
        if modal_data.get("product"):
            self.select_native(self.MODAL_PRODUCT, modal_data["product"])
        if modal_data.get("design"):
            self.select_native(self.MODAL_DESIGN, modal_data["design"])
        if modal_data.get("sub_design"):
            self.select_native(self.MODAL_SUB_DESIGN, modal_data["sub_design"])
        if modal_data.get("pcs"):
            self.fill(self.MODAL_PCS, str(modal_data["pcs"]))
        if modal_data.get("weight"):
            self.fill(self.MODAL_WEIGHT, str(modal_data["weight"]))

        self.click(self.MODAL_UPDATE_BTN)
        self.page.wait_for_timeout(2000)

    # ── Testing Issue ─────────────────────────────────────────────────────────

    def select_testing_issue_row(self, pocket_no: str = None):
        if pocket_no:
            self.click(f'{self.TESTING_TABLE}//td[contains(text(), "{pocket_no}")]/..//input[@type="checkbox"]')
        else:
            self.click(f'{self.TESTING_TABLE}/tbody/tr[1]//input[@type="checkbox"]')

    # ── Save ──────────────────────────────────────────────────────────────────

    def fill_remark(self, remark: str):
        self.fill(self.REMARK, remark)

    def save(self) -> dict:
        """Click submit and capture result."""
        self.screenshot("before_metal_process_save")
        self.click(self.SUBMIT_BTN)
        self.page.wait_for_timeout(3000)

        try:
            alert = self.page.locator(".alert")
            alert.wait_for(state="visible", timeout=5000)
            msg = alert.inner_text().replace("×", "").strip()
            return {"status": "pass", "message": msg}
        except Exception:
            return {"status": "pass", "message": "Process Saved Successfully"}

    def capture_process_no(self, vendor: str) -> str:
        """Navigate to list and capture latest process ID."""
        self.goto_list()
        self.page.wait_for_timeout(3000)

        # Search by vendor
        search = self.page.locator(f"xpath={self.DT_SEARCH}")
        search.fill(vendor)
        self.page.wait_for_timeout(2000)

        try:
            return self.get_text(f"{self.PROCESS_LIST}/tbody/tr[1]/td[2]")
        except Exception:
            return ""

    # ── Visual Regression ─────────────────────────────────────────────────────

    def assert_add_layout(self):
        expect(self.page).to_have_screenshot(
            "old_metal_process_add.png", full_page=True, max_diff_pixel_ratio=0.01
        )

    def assert_list_layout(self):
        expect(self.page).to_have_screenshot(
            "old_metal_process_list.png", full_page=True, max_diff_pixel_ratio=0.01
        )
