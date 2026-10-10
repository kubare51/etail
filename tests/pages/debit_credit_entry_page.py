"""
DebitCreditEntryPage — Page object for Debit/Credit Entry module.

Extracted from: Sparqla/Test_Purchase/DebitCreditEntry.py (325 lines)
Covers: Amount/Weight toggle, supplier select, account type (Supplier/Smith/Approvals),
        supplier bill select, ledger type, amount/weight with credit/debit,
        narration, submit, cancel flow with modal, list verification with filters.
"""
import logging
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class DebitCreditEntryPage(BasePage):
    """Page object for Debit/Credit Entry (Purchase Module)."""

    # ── Header ────────────────────────────────────────────────────────────────
    TRANSACTION_TOGGLE = "//label[@class='switch']"
    TRANSACTION_LABEL = "#transaction-in"
    KARIGAR_SELECT2 = '//select[@id="select_karigar"]/following-sibling::span'
    SELECT2_SEARCH = '//span[@class="select2-search select2-search--dropdown"]/input'
    SUPPLIER_BILL_SELECT2 = '//select[@id="select_karigar_bills"]/following-sibling::span'
    LEDGER_TYPE = "#id_cr_dr_ledger"

    # ── Amount/Weight ─────────────────────────────────────────────────────────
    AMOUNT_INPUT = "#trans_amount"
    TRANS_TYPE = "#transtype"
    WEIGHT_INPUT = "#trans_weight"
    WTRANS_TYPE = "#wtranstype"

    # ── Footer ────────────────────────────────────────────────────────────────
    NARRATION = "#naration"
    SUBMIT_BTN = '//button[@id="save_credit_entry"]'
    CANCEL_BTN = '//button[@id="cancel_bill_edit"]'

    # ── Cancel Modal ──────────────────────────────────────────────────────────
    CANCEL_REMARK = "#credit_cancel_remark"
    CANCEL_CONFIRM = '//button[@id="crdr_cancel"]'

    # ── List Page ─────────────────────────────────────────────────────────────
    LIST_DATE_BTN = '//button[@id="rpt_payment_date"]'
    LIST_TRANS_TYPE = "#transcation_type"
    LIST_ACCOUNT_TYPE = "#trans_type"
    LIST_STATUS = "#status_type"
    LIST_SEARCH_BTN = '//button[@id="credit_debit_search"]'
    LIST_SEARCH = '//div[@id="trans_list_filter"]//input'
    LIST_TABLE = '//table[@id="trans_list"]'

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto_add(self):
        self.navigate("index.php/admin_ret_purchase/credit_debit_entry/add")
        return self

    def goto_list(self):
        self.navigate("index.php/admin_ret_purchase/credit_debit_entry/list")
        return self

    def set_transaction_in(self, target: str):
        """Set toggle to 'amount' or 'weight'."""
        current = self.page.locator(self.TRANSACTION_LABEL).inner_text().strip().lower()
        if current != target.lower():
            self.click(self.TRANSACTION_TOGGLE)
            self.page.wait_for_timeout(1000)

    def select_karigar(self, karigar: str):
        self.select2(self.KARIGAR_SELECT2, karigar, self.SELECT2_SEARCH)
        self.page.wait_for_timeout(2000)

    def select_account_type(self, account_type: str):
        type_map = {"supplier": "1", "smith": "2", "approvals": "3"}
        val = type_map.get(account_type.lower(), account_type)
        self.click(f'//input[@name="credit[accountto]" and @value="{val}"]')
        self.page.wait_for_timeout(2000)

    def select_supplier_bill(self, bill: str):
        self.select2(self.SUPPLIER_BILL_SELECT2, bill, self.SELECT2_SEARCH)
        self.page.wait_for_timeout(1000)

    def select_ledger_type(self, ledger: str):
        self.page.locator(self.LEDGER_TYPE).select_option(label=ledger)
        self.page.wait_for_timeout(1000)

    def fill_amount(self, amount: str, trans_type: str = "credit"):
        self.fill(self.AMOUNT_INPUT, str(amount))
        type_map = {"credit": "1", "debit": "2"}
        self.page.locator(self.TRANS_TYPE).select_option(
            value=type_map.get(trans_type.lower(), "1"))

    def fill_weight(self, weight: str, trans_type: str = "credit"):
        self.fill(self.WEIGHT_INPUT, str(weight))
        type_map = {"credit": "1", "debit": "2"}
        self.page.locator(self.WTRANS_TYPE).select_option(
            value=type_map.get(trans_type.lower(), "1"))

    def fill_narration(self, narration: str):
        self.fill(self.NARRATION, narration)

    def submit(self) -> dict:
        self.screenshot("before_crdr_submit")
        self.click(self.SUBMIT_BTN)
        self.page.wait_for_timeout(3000)
        if "list" in self.page.url:
            return {"status": "pass", "message": "Redirected to list"}
        return {"status": "fail", "message": "Not redirected"}

    def verify_in_list(self, karigar: str) -> dict:
        self.goto_list()
        self.page.wait_for_timeout(2000)
        self.click(self.LIST_DATE_BTN)
        self.page.wait_for_timeout(500)
        self.page.locator("li:text('Today')").click()
        self.page.wait_for_timeout(1000)
        self.click(self.LIST_SEARCH_BTN)
        self.page.wait_for_timeout(2000)

        search = self.page.locator(f"xpath={self.LIST_SEARCH}")
        search.fill(karigar)
        self.page.wait_for_timeout(2000)

        rows = self.page.locator(f"xpath={self.LIST_TABLE}/tbody/tr")
        if rows.count() > 0:
            bill = rows.first.locator("td:nth-child(1)").inner_text().strip()
            return {"status": "pass", "message": f"Verified: {bill}"}
        return {"status": "fail", "message": "Not found in list"}

    def assert_add_layout(self):
        expect(self.page).to_have_screenshot(
            "debit_credit_add.png", full_page=True, max_diff_pixel_ratio=0.01
        )
