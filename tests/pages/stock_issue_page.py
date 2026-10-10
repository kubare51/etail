"""
StockIssuePage — Page object for Stock Issue / Receipt module.

Extracted from: Sparqla/Test_StockIssue/StockIssue.py
Covers: Issue flow (tagged + non-tagged), Receipt flow, list verification.
"""
import logging
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class StockIssuePage(BasePage):
    """Page object for Stock Issue / Receipt."""

    # ── Navigation ────────────────────────────────────────────────────────────
    MENU_STOCK_ISSUE = "//span[contains(text(), 'Stock Issue / Receipt')]"
    MENU_STOCK_ISSUE_SUB = "(//span[contains(text(), 'Stock Issue')])[3]"
    ADD_BTN = '//a[@id="add_Order"]'

    # ── Type Selection ────────────────────────────────────────────────────────
    TYPE_ISSUE = '//input[@id="type_issue"]'
    TYPE_RECEIPT = '//input[@id="type_receipt"]'

    # ── Header Fields ─────────────────────────────────────────────────────────
    BRANCH_SELECT2 = '//select[@id="branch_select"]/following-sibling::span'
    SEL_EMP_SELECT2 = '//select[@id="sel_emp"]/following-sibling::span'
    ISSUE_TYPE_SELECT2 = '//select[@id="issue_type"]/following-sibling::span'
    STOCK_TYPE = '//select[@id="stock_type"]'
    METAL_SELECT2 = '//select[@id="metal"]/following-sibling::span'
    ISSUED_TO = '//select[@id="issued_to"]'
    SELECT2_SEARCH = '//span[@class="select2-search select2-search--dropdown"]/input'

    # Recipient fields (dynamic based on IssuedTo)
    CUSTOMER_AUTOCOMPLETE = "est_cus_name"
    ISSUE_EMPLOYEE_SELECT2 = '//select[@id="issue_employee"]/following-sibling::span'
    KARIGAR_SELECT2 = '//select[@id="karigar"]/following-sibling::span'

    # Section
    SECTION_SELECT2 = '//select[@id="section_select"]/following-sibling::span'

    # ── Tagged Item ───────────────────────────────────────────────────────────
    TAG_CODE_INPUT = "#issue_tag_code"
    TAG_SEARCH_BTN = '//button[@id="issue_tag_search"]'
    TAG_TABLE = '//table[@id="tagissue_item_detail"]'

    # ── Non-Tagged Item ───────────────────────────────────────────────────────
    NONTAG_SEARCH_BTN = '//input[@id="search_non_tag"]'
    NONTAG_TABLE = '//table[@id="nontagissue_item_detail"]'

    # ── Common ────────────────────────────────────────────────────────────────
    RATE_PER_GRAM = "#rate_per_gram"
    REMARK = "#remark"
    SUBMIT_BTN = '//button[@id="stock_issue_submit"]'
    CANCEL_BTN = '//button[contains(@class, "btn-cancel")]'

    # ── Receipt ───────────────────────────────────────────────────────────────
    SELECT_ISSUE_NO = '//select[@id="select_issue_no"]/following-sibling::span'
    RECEIPT_TAG_CODE = "#receipt_tag_code"
    RECEIPT_TAG_SEARCH = '//button[@id="receipt_tag_search"]'
    RECEIPT_TAG_TABLE = '//table[@id="tag_receipt_item_detail"]'
    RECEIPT_NONTAG_TABLE = '//table[@id="nontag_receipt_item_detail"]'

    # ── List Verification ─────────────────────────────────────────────────────
    STATUS_FILTER = '//select[@id="issue_status"]'
    SEARCH_BTN = '//button[contains(text(), "Search")]'
    DT_SEARCH = '//div[@id="DataTables_Table_0_filter"]//input'

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto(self):
        """Navigate to Stock Issue list page."""
        self.navigate_via_menu(
            "Stock Issue / Receipt", "Stock Issue",
            fallback_path="index.php/admin_ret_stock_issue/stock_issue/list"
        )
        return self

    def start_issue(self):
        """Click Add and select Issue type."""
        self.click(self.ADD_BTN)
        self.page.wait_for_timeout(1500)
        self.click(self.TYPE_ISSUE)

    def start_receipt(self):
        """Click Add and select Receipt type."""
        self.click(self.ADD_BTN)
        self.page.wait_for_timeout(1500)
        self.click(self.TYPE_RECEIPT)

    def fill_issue_header(self, issue_from: str = None, employee: str = None,
                          issue_type: str = None, stock_type: str = None,
                          metal: str = None):
        """Fill Issue header fields."""
        if issue_from:
            self.select2(self.BRANCH_SELECT2, issue_from, self.SELECT2_SEARCH)
        if employee:
            self.select2(self.SEL_EMP_SELECT2, employee, self.SELECT2_SEARCH)
        if issue_type:
            self.select2(self.ISSUE_TYPE_SELECT2, issue_type, self.SELECT2_SEARCH)
        if stock_type:
            self.select_native(self.STOCK_TYPE, stock_type)
        if metal:
            self.select2(self.METAL_SELECT2, metal, self.SELECT2_SEARCH)

    def fill_recipient(self, issued_to: str, customer: str = None,
                       employee: str = None, karigar: str = None):
        """Fill the Issued To section with dynamic recipient fields."""
        self.select_native(self.ISSUED_TO, issued_to)
        self.page.wait_for_timeout(1500)

        if issued_to == "Customer" and customer:
            self.autocomplete(self.CUSTOMER_AUTOCOMPLETE, str(customer))
        elif issued_to == "Employee" and employee:
            self.select2(self.ISSUE_EMPLOYEE_SELECT2, employee, self.SELECT2_SEARCH)
        elif issued_to == "karigar" and karigar:
            self.select2(self.KARIGAR_SELECT2, karigar, self.SELECT2_SEARCH)

    def scan_tag(self, tag_code: str, section: str = None):
        """Scan a tagged item."""
        if section:
            self.select2(self.SECTION_SELECT2, section, self.SELECT2_SEARCH)

        self.fill(self.TAG_CODE_INPUT, tag_code)
        self.click(self.TAG_SEARCH_BTN)
        self.page.wait_for_timeout(3000)

        # Verify tag row loaded
        table = self.page.locator(f"xpath={self.TAG_TABLE}")
        expect(table.locator("tbody tr")).to_have_count(1, timeout=10000)

    def fill_nontag_items(self, products: list, pcs_list: list,
                          gwt_list: list, nwt_list: list):
        """Fill non-tagged item grid. Supports pipe-separated multi-item from Sparqla."""
        self.click(self.NONTAG_SEARCH_BTN)
        self.page.wait_for_timeout(3000)

        rows = self.page.locator(f"xpath={self.NONTAG_TABLE}//tbody/tr")
        for i in range(rows.count()):
            row = rows.nth(i)
            product_name = row.locator("td:nth-child(3)").inner_text().strip()

            if product_name in products:
                idx = products.index(product_name)

                # Check checkbox
                checkbox = row.locator(".nt_item_sel")
                if not checkbox.is_checked():
                    checkbox.click()

                # Fill Pcs, GWt, NWt
                row.locator(".ntpcs").fill(str(pcs_list[idx]))
                row.locator(".nt_gross_wt").fill(str(gwt_list[idx]))
                nwt_input = row.locator(".nt_net_wt")
                if nwt_input.is_enabled():
                    nwt_input.fill(str(nwt_list[idx]))

                logger.info(f"Filled non-tag row: {product_name}")

    def fill_rate_and_remark(self, rate: str = None, remark: str = None):
        """Fill rate per gram and remark."""
        if rate:
            self.fill(self.RATE_PER_GRAM, str(rate))
        if remark:
            self.fill(self.REMARK, str(remark))

    def submit_issue(self) -> str:
        """Submit stock issue and capture ID from new tab."""
        self.screenshot("before_issue_submit")
        return self.click_and_capture_new_tab(self.SUBMIT_BTN)

    def submit_receipt(self) -> bool:
        """Submit receipt and verify redirect to list."""
        self.screenshot("before_receipt_submit")
        self.click(self.SUBMIT_BTN)
        try:
            self.page.wait_for_url("**/stock_issue/list**", timeout=10000)
            return True
        except Exception:
            return False

    def fill_receipt_header(self, issue_from: str = None, employee: str = None,
                            stock_type: str = None):
        """Fill Receipt header fields."""
        if issue_from:
            self.select2(self.BRANCH_SELECT2, issue_from, self.SELECT2_SEARCH)
        if employee:
            self.select2(self.SEL_EMP_SELECT2, employee, self.SELECT2_SEARCH)
        if stock_type:
            self.select_native(self.STOCK_TYPE, stock_type)

    def select_receipt_issue_no(self, issue_no: str):
        """Select the Issue No to receive against."""
        self.select2(self.SELECT_ISSUE_NO, issue_no, self.SELECT2_SEARCH)
        self.page.wait_for_timeout(2000)

    def select_all_receipt_items(self):
        """Select all checkboxes in receipt grid."""
        checkboxes = self.page.locator(f"xpath={self.RECEIPT_NONTAG_TABLE}//input[@type='checkbox']")
        for i in range(checkboxes.count()):
            if not checkboxes.nth(i).is_checked():
                checkboxes.nth(i).click()

    def verify_in_list(self, issue_id: str, status: str = "Issued") -> dict:
        """Verify stock issue/receipt in the list page."""
        if "stock_issue/list" not in self.page.url:
            self.navigate("index.php/admin_ret_stock_issue/stock_issue/list")

        self.select_native(self.STATUS_FILTER, status)
        self.click(self.SEARCH_BTN)
        self.page.wait_for_timeout(3000)

        if issue_id:
            search = self.page.locator(f"xpath={self.DT_SEARCH}")
            search.fill(str(issue_id))
            self.page.wait_for_timeout(2000)

        first_row = self.page.locator('table.dataTable tbody tr:first-child')
        row_text = first_row.inner_text()
        found = str(issue_id) in row_text

        if found:
            invoice = first_row.locator("td:nth-child(2)").inner_text()
            return {"status": "pass", "message": f"Verified: ID={issue_id}, Invoice={invoice}", "invoice": invoice}

        return {"status": "fail", "message": f"Not found in list: {issue_id}"}

    # ── Visual Regression ─────────────────────────────────────────────────────

    def assert_issue_form_layout(self):
        """Visual regression: issue form."""
        expect(self.page).to_have_screenshot(
            "stock_issue_form.png", full_page=True, max_diff_pixel_ratio=0.01
        )

    def assert_list_layout(self):
        """Visual regression: stock issue list."""
        expect(self.page).to_have_screenshot(
            "stock_issue_list.png", full_page=True, max_diff_pixel_ratio=0.01
        )
