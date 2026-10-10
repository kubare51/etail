"""
SmithSupplierPaymentPage — Page object for Supplier PO Payment module.

Extracted from: Sparqla/Test_Purchase/SmithSupplierPayment.py (477 lines)
Covers: Bill/Receipt type, karigar selection, opening balance,
        bill selection (All/specific), payment modes (Cash/Net Banking/Cheque),
        payment modals, remark, submit + validation (cash >10k block),
        list verification.
"""
import logging
import re
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class SmithSupplierPaymentPage(BasePage):
    """Page object for Smith Supplier Payment (Purchase Module)."""

    # ── Bill Type ─────────────────────────────────────────────────────────────
    BILL_TYPE_BILL = '//input[@name="billing[bill_type]" and @value="1"]'
    BILL_TYPE_RECEIPT = '//input[@name="billing[bill_type]" and @value="2"]'

    # ── Header ────────────────────────────────────────────────────────────────
    KARIGAR_SELECT2 = '//select[@id="select_karigar"]/following-sibling::span'
    SELECT2_SEARCH = '//span[@class="select2-search select2-search--dropdown"]/input'
    OPENING_CHECKBOX = '#opening_checkbox'
    BALANCE_AMOUNT = "#balance_amount"

    # ── Payment ───────────────────────────────────────────────────────────────
    CASH_AMOUNT = '//input[@name="billing[cash_amount]"]'
    NET_BANK_MODAL_BTN = '//a[@id="net_bank_modal"]'
    CHEQUE_MODAL_BTN = '//a[@id="cheque_modal"]'

    # ── Net Banking Modal ─────────────────────────────────────────────────────
    NB_ADD_ROW = '//button[@id="create_net_banking_row"]'
    NB_TABLE = '//table[@id="net_banking_details"]'
    NB_SAVE = '//a[@id="save_net_banking"]'

    # ── Cheque Modal ──────────────────────────────────────────────────────────
    CHQ_ADD_ROW = '//button[@id="new_chq"]'
    CHQ_TABLE = '//table[@id="chq_details"]'
    CHQ_SAVE = '//a[@id="save_issue_chq"]'

    # ── Footer ────────────────────────────────────────────────────────────────
    REMARK = "#remark"
    TOTAL_PAY = "#total_pay_amount"
    BAL_AMOUNT = "#bal_amount"

    # ── Submit ────────────────────────────────────────────────────────────────
    SUBMIT_BTN = '//button[@id="pay_submit"]'

    # ── List Page ─────────────────────────────────────────────────────────────
    LIST_DATE_BTN = '//button[@id="sp-dt-btn"]'
    LIST_SEARCH = '//div[@id="supplier_po_pay_list_filter"]//input'
    LIST_TABLE = '//table[@id="supplier_po_pay_list"]'

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto_add(self):
        self.navigate("index.php/admin_ret_purchase/supplier_po_payment/add")
        return self

    def goto_list(self):
        self.navigate("index.php/admin_ret_purchase/supplier_po_payment/list")
        return self

    def select_bill_type(self, bill_type: str):
        self.click(self.BILL_TYPE_BILL if bill_type == "BILL"
                   else self.BILL_TYPE_RECEIPT)
        self.page.wait_for_timeout(1000)

    def select_karigar(self, karigar: str):
        self.select2(self.KARIGAR_SELECT2, karigar, self.SELECT2_SEARCH)
        self.page.wait_for_timeout(3000)

    def toggle_opening_balance(self, enable: bool = True):
        checkbox = self.page.locator(self.OPENING_CHECKBOX)
        if enable and not checkbox.is_checked():
            checkbox.click()
        elif not enable and checkbox.is_checked():
            checkbox.click()
        self.page.wait_for_timeout(1000)

    def select_bills(self, selection: str = "All"):
        """Select bills — 'All' or comma-separated ref numbers."""
        if selection.lower() == "all":
            # Bills auto-selected on karigar load
            pass
        else:
            # Clear all first
            self.page.evaluate("window.scrollTo(0, document.body.scrollHeight)")
            self.page.wait_for_timeout(500)
            self.page.locator("button:text('Clear All')").click()
            self.page.wait_for_timeout(2000)

            refs = [r.strip() for r in selection.split(",")]
            rows = self.page.locator(
                "//div[@id='pending_bills_container']//table/tbody/tr"
            )
            for i in range(rows.count()):
                row = rows.nth(i)
                ref_text = row.locator("td:nth-child(2)").inner_text().strip()
                if ref_text in refs:
                    checkbox = row.locator(f"input[name='billing[{i}][bill_ids][]']")
                    if not checkbox.is_checked():
                        checkbox.click()
            self.page.wait_for_timeout(2000)

    def get_net_amount(self) -> float:
        val = self.page.locator(self.BALANCE_AMOUNT).input_value()
        return float(val.replace(",", "") or 0)

    def fill_cash_amount(self, amount: str):
        self.fill(self.CASH_AMOUNT, str(amount))

    def fill_net_banking(self, nb_type: str, bank: str, amount: str,
                         ref_no: str, date: str):
        """Open NB modal and fill one row."""
        self.click(self.NET_BANK_MODAL_BTN)
        self.page.wait_for_timeout(2000)
        self.click(self.NB_ADD_ROW)
        self.page.wait_for_timeout(1000)

        last_row = self.page.locator(
            f"xpath=({self.NB_TABLE}/tbody/tr)[last()]"
        )
        if nb_type:
            last_row.locator("select.nb_type").select_option(label=nb_type)
        if bank:
            selects = last_row.locator("select:not(.nb_type)")
            if selects.count() > 0:
                selects.first.select_option(label=bank)
        if amount:
            last_row.locator("input.pay_amount").fill(str(amount))
        if ref_no:
            last_row.locator("input.ref_no").fill(str(ref_no))

        self.click(self.NB_SAVE)
        self.page.wait_for_timeout(1000)

    def fill_cheque(self, cheque_date: str, bank: str, branch: str,
                    cheque_no: str, ifsc: str, amount: str):
        """Open Cheque modal and fill one row."""
        self.click(self.CHEQUE_MODAL_BTN)
        self.page.wait_for_timeout(2000)
        self.click(self.CHQ_ADD_ROW)
        self.page.wait_for_timeout(1000)

        last_row = self.page.locator(
            f"xpath=({self.CHQ_TABLE}/tbody/tr)[last()]"
        )
        if cheque_date:
            last_row.locator("input.cheque_date").fill(str(cheque_date))
        if bank:
            last_row.locator("select.bank_name").select_option(label=bank)
        if branch:
            last_row.locator("input.bank_branch").fill(str(branch))
        if cheque_no:
            last_row.locator("input.cheque_no").fill(str(cheque_no))
        if ifsc:
            last_row.locator("input.bank_IFSC").fill(str(ifsc))
        if amount:
            last_row.locator("input.payment_amount").fill(str(amount))
            self.page.wait_for_timeout(2000)

        self.click(self.CHQ_SAVE)
        self.page.wait_for_timeout(1000)

    def fill_remark(self, remark: str):
        self.fill(self.REMARK, remark)

    def submit(self) -> dict:
        """Submit payment."""
        self.screenshot("before_payment_submit")
        self.click(self.SUBMIT_BTN)
        self.page.wait_for_timeout(5000)

        # Check for validation alert (cash > 10k)
        dialog_msg = None

        def handle_dialog(dialog):
            nonlocal dialog_msg
            dialog_msg = dialog.message
            dialog.accept()

        self.page.on("dialog", handle_dialog)
        self.page.wait_for_timeout(2000)

        if dialog_msg and "10000" in dialog_msg:
            return {"status": "blocked", "message": dialog_msg}

        try:
            success = self.page.locator("div.alert-success")
            success.wait_for(state="visible", timeout=5000)
            return {"status": "pass", "message": success.inner_text().strip()}
        except Exception:
            return {"status": "fail", "message": dialog_msg or "No success"}

    def verify_in_list(self, karigar: str) -> dict:
        self.goto_list()
        self.page.wait_for_timeout(2000)
        self.click(self.LIST_DATE_BTN)
        self.page.wait_for_timeout(500)
        self.page.locator("li:text('Today')").click()
        self.page.wait_for_timeout(2000)

        search = self.page.locator(f"xpath={self.LIST_SEARCH}")
        search.fill(karigar)
        self.page.wait_for_timeout(2000)

        rows = self.page.locator(f"xpath={self.LIST_TABLE}/tbody/tr")
        if rows.count() > 0:
            ref = rows.first.locator("td:nth-child(3)").inner_text().strip()
            return {"status": "pass", "message": f"Verified: {ref}"}
        return {"status": "fail", "message": "Not found in list"}

    # ── Visual Regression ─────────────────────────────────────────────────────

    def assert_add_layout(self):
        expect(self.page).to_have_screenshot(
            "supplier_payment_add.png", full_page=True, max_diff_pixel_ratio=0.01
        )

    def assert_list_layout(self):
        expect(self.page).to_have_screenshot(
            "supplier_payment_list.png", full_page=True, max_diff_pixel_ratio=0.01
        )
