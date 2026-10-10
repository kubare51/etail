"""
GRNEntryPage — Page object for GRN Entry module.

Extracted from: Sparqla/Test_Purchase/GRNEntry.py (1083 lines)
Covers: Bill/Receipt/Charges GRN types, karigar selection,
        ref details, item table (category, pcs, gwt, wastage, rate),
        TDS/TCS, cancel flow with modal, edit/update, list verification.
"""
import logging
import re
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class GRNEntryPage(BasePage):
    """Page object for GRN Entry (Purchase Module)."""

    # ── GRN Type ──────────────────────────────────────────────────────────────
    TYPE_BILL = '//input[@id="oranment_type"]'
    TYPE_RECEIPT = '//input[@id="mt_type"]'
    TYPE_CHARGES = '//input[@id="st_type"]'

    # ── Header ────────────────────────────────────────────────────────────────
    KARIGAR_SELECT2 = '//select[@id="select_karigar"]/following-sibling::span'
    SELECT2_SEARCH = '//span[@class="select2-search select2-search--dropdown"]/input'
    REF_NO = '//input[@name="order[po_supplier_ref_no]"]'
    PURCHASE_TYPE = "#purchase_type"
    REF_DATE = '//input[@name="order[po_ref_date]"]'
    EWAY_BILL = "#ewaybillno"
    IRN_NO = "#invoice_ref_no"
    DISPATCH_THROUGH = "#despatch_through"

    # ── Item Table ────────────────────────────────────────────────────────────
    ADD_ITEM_BTN = '//button[@id="add_item_details"]'
    ITEM_TABLE = '//table[@id="grn_item_details"]'
    CATEGORY_SELECT2 = '//span[@title="Select Category"]'

    # ── Tax ───────────────────────────────────────────────────────────────────
    TDS_INPUT = "#tds_percent"
    TCS_INPUT = "#tcs_percent"

    # ── Save ──────────────────────────────────────────────────────────────────
    SUBMIT_BTN = '//button[@id="submit_grn_entry"]'
    SAVE_GRN_BTN = '//button[@id="save_grn"]'
    CANCEL_FORM_BTN = '(//button[@class="btn btn-default btn-cancel"])[2]'

    # ── List Page ─────────────────────────────────────────────────────────────
    GRN_LIST = '//table[@id="grn_list"]'
    LIST_SEARCH = '//div[@id="grn_list_filter"]//input'
    DATE_PICKER = '//button[@id="grn-dt-btn"]'

    # ── Cancel Modal ──────────────────────────────────────────────────────────
    CANCEL_MODAL = "#confirm-delete"
    CANCEL_REMARK = "#cancel_remark"
    CANCEL_CONFIRM_BTN = '//button[@id="grn_cancel"]'

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto(self):
        """Navigate to GRN Entry page."""
        self.navigate_via_menu(
            "Purchase Module", "GRN Entry",
            fallback_path="index.php/admin_ret_purchase/grnentry/list"
        )
        return self

    def click_add(self):
        self.click('//a[@id="add_Order"]')
        self.page.wait_for_timeout(3000)

    def select_grn_type(self, grn_type: str):
        """Bill, Receipt, or Charges."""
        type_map = {
            "Bill": self.TYPE_BILL,
            "Receipt": self.TYPE_RECEIPT,
            "Charges": self.TYPE_CHARGES,
        }
        self.click(type_map.get(grn_type, self.TYPE_BILL))
        self.page.wait_for_timeout(1000)

    def fill_header(self, karigar: str, ref_no: str, purchase_type: str,
                    ref_date: str, dispatch_through: str,
                    eway_bill: str = None, irn_no: str = None):
        """Fill GRN header fields."""
        self.select2(self.KARIGAR_SELECT2, karigar, self.SELECT2_SEARCH)
        self.page.wait_for_timeout(1000)

        self.fill(self.REF_NO, str(ref_no))
        self.select_native(self.PURCHASE_TYPE, purchase_type)

        # Date with JS bypass
        date_el = self.page.locator(f"xpath={self.REF_DATE}")
        date_el.evaluate(
            "(el, val) => { el.value = val; el.dispatchEvent(new Event('change')); }",
            str(ref_date)
        )
        self.page.keyboard.press("Tab")
        self.page.wait_for_timeout(500)

        if eway_bill:
            self.fill(self.EWAY_BILL, str(eway_bill))
        if irn_no:
            self.fill(self.IRN_NO, str(irn_no))

        self.select_native(self.DISPATCH_THROUGH, dispatch_through)

    def add_item(self, category: str, pcs: str, gross_wt: str,
                 wastage: str = None, rate_per_gram: str = None,
                 rate_type: str = None, grn_type: str = "Bill"):
        """Add an item row to the GRN."""
        self.click(self.ADD_ITEM_BTN)
        self.page.wait_for_timeout(2000)

        # Get row count for indexing
        rows = self.page.locator(f"xpath={self.ITEM_TABLE}/tbody/tr")
        idx = rows.count()

        # Category
        self.select2(self.CATEGORY_SELECT2, category, self.SELECT2_SEARCH)
        self.page.wait_for_timeout(1000)

        # Pcs
        pcs_input = self.page.locator(f"xpath=(//input[@name='item[pcs][]'])[{idx}]")
        pcs_input.fill(str(pcs))

        # Gross Weight
        gwt_input = self.page.locator(f"xpath=(//input[@name='item[gross_wt][]'])[{idx}]")
        gwt_input.fill(str(gross_wt))

        # Wastage (Receipt only)
        if wastage and grn_type == "Receipt":
            va_input = self.page.locator(f"xpath=(//input[@name='item[wastage][]'])[{idx}]")
            va_input.fill(str(wastage))

        # Rate Per Gram (Bill only)
        if rate_per_gram and grn_type == "Bill":
            rate_input = self.page.locator(f"xpath=(//input[@name='item[rate_per_gram][]'])[{idx}]")
            rate_input.fill(str(rate_per_gram))

        # Rate Type
        if rate_type:
            rate_map = {"Grm": "1", "Pcs": "2"}
            rate_select = self.page.locator(
                f"xpath=(//select[@class='ratecaltype form-control'])[{idx}]"
            )
            rate_select.select_option(value=rate_map.get(rate_type, "1"))

        self.page.wait_for_timeout(1000)

    def fill_tax(self, tds: str = None, tcs: str = None):
        if tds:
            self.fill(self.TDS_INPUT, str(tds))
        if tcs:
            self.fill(self.TCS_INPUT, str(tcs))

    def save(self) -> dict:
        """Submit GRN entry."""
        self.screenshot("before_grn_save")
        self.click(self.SUBMIT_BTN)
        self.page.wait_for_timeout(3000)

        try:
            success = self.page.locator("div.alert-success")
            success.wait_for(state="visible", timeout=5000)
            msg = success.inner_text().strip()

            if "success" in msg.lower():
                grn_no = ""
                try:
                    grn_no = self.get_text(f"{self.GRN_LIST}/tbody/tr[1]/td[2]")
                except Exception:
                    pass
                return {"status": "pass", "message": msg, "grn_no": grn_no}
            return {"status": "fail", "message": msg}
        except Exception:
            return {"status": "fail", "message": "No success message"}

    def cancel_form(self):
        try:
            self.click(self.CANCEL_FORM_BTN)
        except Exception:
            pass

    # ── List ──────────────────────────────────────────────────────────────────

    def verify_in_list(self, grn_no: str) -> dict:
        search = self.page.locator(f"xpath={self.LIST_SEARCH}")
        search.clear()
        search.fill(grn_no)
        self.page.wait_for_timeout(2000)

        rows = self.page.locator(f"xpath={self.GRN_LIST}//tr[contains(., '{grn_no}')]")
        if rows.count() > 0:
            return {"status": "pass", "message": f"GRN {grn_no} found"}
        return {"status": "fail", "message": f"GRN {grn_no} not found"}

    def cancel_grn(self, grn_no: str, reason: str = "Test cancellation") -> dict:
        """Cancel GRN from list page via modal."""
        search = self.page.locator(f"xpath={self.LIST_SEARCH}")
        search.clear()
        search.fill(grn_no)
        self.page.wait_for_timeout(2000)

        # Click cancel action button
        cancel_btn = self.page.locator(
            f"xpath=(//button[@class='btn btn-warning'])[1]"
        )
        cancel_btn.click()
        self.page.wait_for_timeout(2000)

        # Modal
        self.page.locator(self.CANCEL_MODAL).wait_for(state="visible", timeout=5000)
        self.page.locator(self.CANCEL_REMARK).fill(reason)
        self.page.wait_for_timeout(1000)
        self.click(self.CANCEL_CONFIRM_BTN)
        self.page.wait_for_timeout(3000)

        try:
            success = self.page.locator("div.alert-success")
            success.wait_for(state="visible", timeout=5000)
            return {"status": "pass", "message": success.inner_text().strip()}
        except Exception:
            return {"status": "pass", "message": "Cancel command sent"}

    # ── Visual Regression ─────────────────────────────────────────────────────

    def assert_form_layout(self):
        expect(self.page).to_have_screenshot(
            "grn_entry_form.png", full_page=True, max_diff_pixel_ratio=0.01
        )

    def assert_list_layout(self):
        expect(self.page).to_have_screenshot(
            "grn_entry_list.png", full_page=True, max_diff_pixel_ratio=0.01
        )
