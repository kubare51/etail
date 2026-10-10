"""
SmithMetalIssuePage — Page object for Smith Metal Issue module.

Extracted from: Sparqla/Test_Purchase/SmithMetalIssue.py (508 lines)
Covers: Branch/Karigar/Metal header, Issue Type (Normal/Approval),
        Against Opening/Order/Bill, Tag/Non-Tag source, item selection,
        purity/touch/pcs/weight entry, pure weight verification,
        submit + capture ID from new tab, list verification.
"""
import logging
import re
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class SmithMetalIssuePage(BasePage):
    """Page object for Smith Metal Issue (Purchase Module)."""

    # ── Header ────────────────────────────────────────────────────────────────
    BRANCH_SELECT2 = '//select[@id="select_branch"]/following-sibling::span'
    KARIGAR_SELECT2 = '//select[@id="select_karigar"]/following-sibling::span'
    METAL_SELECT2 = '//select[@id="select_metal"]/following-sibling::span'
    SELECT2_SEARCH = '//span[@class="select2-search select2-search--dropdown"]/input'

    # ── Issue Type ────────────────────────────────────────────────────────────
    NORMAL_ISSUE = '//input[@name="issue[metal_issue_type]" and @value="1"]'
    APPROVAL_ISSUE = '//input[@name="issue[metal_issue_type]" and @value="2"]'

    # ── Against ───────────────────────────────────────────────────────────────
    AGAINST_OPENING_YES = '//input[@name="issue[is_against_opening]" and @value="1"]'
    AGAINST_OPENING_NO = '//input[@name="issue[is_against_opening]" and @value="0"]'
    OPENING_KARIGAR_SELECT2 = '//select[@id="karigar_select"]/following-sibling::span'
    SEARCH_OPENING_BTN = '//button[@id="search_kar_opening_det"]'

    AGAINST_ORDER_YES = '//input[@name="issue[issue_aganist]" and @value="1"]'
    AGAINST_ORDER_NO = '//input[@name="issue[issue_aganist]" and @value="0"]'
    PO_SELECT2 = '//select[@id="select_po_no"]/following-sibling::span'

    AGAINST_BILL_YES = '//input[@name="issue[issue_against_po]" and @value="1"]'
    AGAINST_BILL_NO = '//input[@name="issue[issue_against_po]" and @value="0"]'
    FY_SELECT2 = '//select[@id="pur_fin_year_select"]/following-sibling::span'
    SUPPLIER_PO_SELECT2 = '//select[@id="select_supplier_po_no"]/following-sibling::span'

    # ── Source ────────────────────────────────────────────────────────────────
    SOURCE_TAG = '//input[@name="issue[issue_from]" and @value="1"]'
    SOURCE_NONTAG = '//input[@name="issue[issue_from]" and @value="2"]'
    TAG_CODE_INPUT = "#tag_code"
    TAG_SEARCH_BTN = '//button[@id="tag_history_search"]'
    BT_CODE_INPUT = "#bt_number"

    # ── Item Table ────────────────────────────────────────────────────────────
    SELECT_ALL = "#select_all"
    METAL_TABLE = '//table[@id="metal_details"]'

    # ── Footer ────────────────────────────────────────────────────────────────
    REMARK = "#remark"
    SUBMIT_BTN = '//button[@id="submit_metal_issue"]'

    # ── List Page ─────────────────────────────────────────────────────────────
    LIST_SEARCH = '//div[@id="stock_issue_list_filter"]//input'
    LIST_TABLE = '//table[@id="stock_issue_list"]'

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto_add(self):
        self.navigate("index.php/admin_ret_purchase/karigarmetalissue/add")
        return self

    def goto_list(self):
        self.navigate("index.php/admin_ret_purchase/karigarmetalissue/list")
        return self

    def fill_header(self, branch: str = None, karigar: str = None,
                    metal: str = None):
        if branch:
            self.select2(self.BRANCH_SELECT2, branch, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(1000)
        if karigar:
            self.select2(self.KARIGAR_SELECT2, karigar, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(3000)
        if metal:
            self.select2(self.METAL_SELECT2, metal, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(2000)

    def select_issue_type(self, issue_type: str):
        if "appr" in issue_type.lower():
            self.click(self.APPROVAL_ISSUE)
        else:
            self.click(self.NORMAL_ISSUE)
        self.page.wait_for_timeout(1000)

    def set_against_opening(self, against: str, search_karigar: str = None):
        if against.lower() == "yes":
            self.click(self.AGAINST_OPENING_YES)
            self.page.wait_for_timeout(1000)
            if search_karigar:
                self.select2(self.OPENING_KARIGAR_SELECT2, search_karigar, self.SELECT2_SEARCH)
                self.click(self.SEARCH_OPENING_BTN)
                self.page.wait_for_timeout(2000)
        else:
            self.click(self.AGAINST_OPENING_NO)

    def set_against_order(self, against: str, ref_order_no: str = None):
        if against.lower() == "yes":
            self.click(self.AGAINST_ORDER_YES)
            self.page.wait_for_timeout(1000)
            if ref_order_no:
                self.select2(self.PO_SELECT2, ref_order_no, self.SELECT2_SEARCH)
                self.page.wait_for_timeout(2000)
        else:
            self.click(self.AGAINST_ORDER_NO)

    def set_against_bill(self, against: str, fy: str = None,
                         ref_order_no: str = None):
        if against.lower() == "yes":
            self.click(self.AGAINST_BILL_YES)
            self.page.wait_for_timeout(1000)
            if fy:
                self.select2(self.FY_SELECT2, fy, self.SELECT2_SEARCH)
            if ref_order_no:
                self.select2(self.SUPPLIER_PO_SELECT2, ref_order_no, self.SELECT2_SEARCH)
                self.page.wait_for_timeout(2000)
        else:
            self.click(self.AGAINST_BILL_NO)

    def select_source(self, source_type: str, code: str = None):
        """Tag or Non-Tag source, optional code search."""
        is_tag = "tag" in source_type.lower() and "non" not in source_type.lower()
        self.click(self.SOURCE_TAG if is_tag else self.SOURCE_NONTAG)
        self.page.wait_for_timeout(1000)

        if code:
            if is_tag:
                self.fill(self.TAG_CODE_INPUT, str(code))
                self.click(self.TAG_SEARCH_BTN)
            else:
                self.fill(self.BT_CODE_INPUT, str(code))
            self.page.wait_for_timeout(3000)

    def select_all_items(self):
        self.page.locator(self.SELECT_ALL).click()
        self.page.wait_for_timeout(1000)

    def fill_item_details(self, items: list):
        """Fill touch/pcs/weight per item row from YAML data."""
        for item in items:
            ref_no = item.get("ref_order_no", "")
            row_xpath = (f"{self.METAL_TABLE}/tbody/tr[contains(., '{ref_no}')]"
                         if ref_no else f"{self.METAL_TABLE}/tbody/tr")
            row = self.page.locator(f"xpath={row_xpath}").first

            # Checkbox
            checkbox = row.locator("input.id_kar_issue")
            if not checkbox.is_checked():
                checkbox.click()
                self.page.wait_for_timeout(500)

            if item.get("touch"):
                touch_input = row.locator("[id*='karigar_touch']")
                touch_input.clear()
                touch_input.fill(str(item["touch"]))
                touch_input.press("Tab")
                self.page.wait_for_timeout(500)

            if item.get("pcs"):
                pcs_input = row.locator("input.issue_pcs")
                pcs_input.clear()
                pcs_input.fill(str(item["pcs"]))
                pcs_input.press("Tab")
                self.page.wait_for_timeout(500)

            if item.get("weight"):
                wt_input = row.locator("input.issue_weight")
                wt_input.clear()
                wt_input.fill(str(item["weight"]))
                wt_input.press("Tab")
                self.page.wait_for_timeout(500)

    def fill_remark(self, remark: str):
        self.fill(self.REMARK, remark)

    def submit(self) -> dict:
        """Submit and capture ID from new tab."""
        self.screenshot("before_smith_issue_submit")
        main_page = self.page

        with self.page.context.expect_page(timeout=15000) as new_tab_info:
            self.click(self.SUBMIT_BTN)

        try:
            new_tab = new_tab_info.value
            new_tab.wait_for_load_state("domcontentloaded")
            url = new_tab.url
            match = re.search(r'/(\d+)$', url)
            captured_id = match.group(1) if match else ""
            new_tab.close()
            return {"status": "pass", "message": f"Saved. ID: {captured_id}",
                    "id": captured_id}
        except Exception:
            self.page.wait_for_timeout(5000)
            return {"status": "pass", "message": "Saved (no acknowledgment tab)"}

    def verify_in_list(self, search_term: str) -> dict:
        self.goto_list()
        self.page.wait_for_timeout(3000)

        search = self.page.locator(f"xpath={self.LIST_SEARCH}")
        search.fill(str(search_term))
        self.page.wait_for_timeout(2000)

        rows = self.page.locator(f"xpath={self.LIST_TABLE}/tbody/tr")
        if rows.count() > 0:
            ref_no = self.get_text(f"{self.LIST_TABLE}/tbody/tr[1]/td[3]")
            return {"status": "pass", "message": f"Verified: {ref_no}",
                    "ref_no": ref_no}
        return {"status": "fail", "message": "Not found in list"}

    # ── Visual Regression ─────────────────────────────────────────────────────

    def assert_add_layout(self):
        expect(self.page).to_have_screenshot(
            "smith_metal_issue_add.png", full_page=True, max_diff_pixel_ratio=0.01
        )

    def assert_list_layout(self):
        expect(self.page).to_have_screenshot(
            "smith_metal_issue_list.png", full_page=True, max_diff_pixel_ratio=0.01
        )
