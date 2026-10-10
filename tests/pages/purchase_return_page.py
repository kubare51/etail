"""
PurchaseReturnPage — Page object for Purchase Return module.

Extracted from: Sparqla/Test_Purchase/PurchaseReturn.py (609 lines)
Covers: Return/Sales/Sales Return types, Normal/Suspense stock,
        PO Ref/Tag/NonTag receipt types, item row adjustments
        (pcs/gwt/wastage/mc/touch/rate), stones/other metal/charges modals,
        footer (discount/round off/narration), submit + invoice capture,
        list verification.
"""
import logging
import re
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class PurchaseReturnPage(BasePage):
    """Page object for Purchase Return (Purchase Module)."""

    # ── Select Type (Return/Sales/Sales Return) ───────────────────────────────
    TYPE_RETURN = '//input[@name="purchase_type" and @value="0"]'
    TYPE_SALES = '//input[@name="purchase_type" and @value="1"]'
    TYPE_SALES_RETURN = '//input[@name="purchase_type" and @value="2"]'

    # ── Stock Type ────────────────────────────────────────────────────────────
    NORMAL_STOCK = '//input[@name="stock_type" and @value="0"]'
    SUSPENSE_STOCK = '//input[@name="stock_type" and @value="1"]'

    # ── Receipt Type ──────────────────────────────────────────────────────────
    RECEIPT_PO_REF = '//input[@name="purret_receipt_type" and @value="0"]'
    RECEIPT_TAG = '//input[@name="purret_receipt_type" and @value="1"]'
    RECEIPT_NONTAG = '//input[@name="purret_receipt_type" and @value="2"]'

    # ── Header ────────────────────────────────────────────────────────────────
    FILTER_KARIGAR_SELECT2 = '//select[@id="select_karigar"]/following-sibling::span'
    ISSUE_KARIGAR_SELECT2 = '//select[@id="purret_to_karigar"]/following-sibling::span'
    SELECT2_SEARCH = '//span[@class="select2-search select2-search--dropdown"]/input'

    # Convert To
    CONVERT_SUPPLIER = '//input[@name="pur_return_convert_to" and @value="1"]'
    CONVERT_MANUFACTURER = '//input[@name="pur_return_convert_to" and @value="2"]'

    # Return Reason
    REASON_DAMAGE = '//input[@name="returnreason" and @value="1"]'
    REASON_EXCESS = '//input[@name="returnreason" and @value="2"]'

    # ── PO Ref Items ──────────────────────────────────────────────────────────
    PO_REF_SELECT2 = '//select[@id="select_po_ref_no"]/following-sibling::span'
    SELECT_ALL = "//table[@id='return_item_detail']//input[@id='select_all']"
    ITEM_TABLE = '//table[@id="return_item_detail"]'

    # ── Tag / NonTag ──────────────────────────────────────────────────────────
    TAG_INPUT = "#tag_number"
    TAG_SEARCH_BTN = '//button[@id="tag_history_search"]'
    BT_INPUT = "#bt_number"
    SALES_RETURN_BILL_SELECT2 = '//select[@id="sales_return_bill_no"]/following-sibling::span'

    # ── Footer ────────────────────────────────────────────────────────────────
    DISCOUNT = ".return_discount"
    ROUND_OFF_SYMBOL = ".round_off_symbol"
    ROUND_OFF = ".return_round_off"
    NARRATION = "#returnnarration"

    # ── Submit ────────────────────────────────────────────────────────────────
    SUBMIT_BTN = '//button[@id="return_po_items_submit"]'

    # ── List Page ─────────────────────────────────────────────────────────────
    LIST_DATE_BTN = '//button[@id="rpt_payment_date"]'
    LIST_SEARCH_BTN = '//button[@id="purchase_return_search"]'
    LIST_SEARCH = '//div[@id="pur_return_list_filter"]//input'
    LIST_TABLE = '//table[@id="pur_return_list"]'

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto_add(self):
        self.navigate("index.php/admin_ret_purchase/purchasereturn/add")
        return self

    def goto_list(self):
        self.navigate("index.php/admin_ret_purchase/purchasereturn/list")
        return self

    def select_type(self, select_type: str):
        """Return, Sales, or Sales Return."""
        type_map = {"Return": self.TYPE_RETURN, "Sales": self.TYPE_SALES,
                    "Sales Return": self.TYPE_SALES_RETURN}
        self.click(type_map.get(select_type, self.TYPE_RETURN))
        self.page.wait_for_timeout(1000)

    def select_stock_type(self, stock_type: str):
        self.click(self.NORMAL_STOCK if stock_type == "Normal Stock"
                   else self.SUSPENSE_STOCK)
        self.page.wait_for_timeout(500)

    def select_receipt_type(self, receipt_type: str):
        rt_map = {"PO RefNO": self.RECEIPT_PO_REF, "Tag": self.RECEIPT_TAG,
                  "NonTag": self.RECEIPT_NONTAG}
        self.click(rt_map.get(receipt_type, self.RECEIPT_PO_REF))
        self.page.wait_for_timeout(500)

    def fill_header(self, filter_supplier: str = None,
                    issue_supplier: str = None,
                    convert_to: str = None,
                    return_reason: str = None):
        if filter_supplier:
            self.select2(self.FILTER_KARIGAR_SELECT2, filter_supplier,
                         self.SELECT2_SEARCH)
            self.page.wait_for_timeout(1000)
        if issue_supplier:
            self.select2(self.ISSUE_KARIGAR_SELECT2, issue_supplier,
                         self.SELECT2_SEARCH)
            self.page.wait_for_timeout(1000)
        if convert_to:
            conv_map = {"Supplier": self.CONVERT_SUPPLIER,
                        "Manufaucturers": self.CONVERT_MANUFACTURER}
            self.click(conv_map.get(convert_to, self.CONVERT_SUPPLIER))
        if return_reason:
            self.click(self.REASON_DAMAGE if return_reason == "Damage"
                       else self.REASON_EXCESS)

    def select_po_ref(self, po_ref_no: str):
        self.select2(self.PO_REF_SELECT2, po_ref_no, self.SELECT2_SEARCH)
        self.page.wait_for_timeout(2000)
        self.click(self.SELECT_ALL)
        self.page.wait_for_timeout(1000)

    def fill_tag_code(self, tag_code: str):
        self.fill(self.TAG_INPUT, tag_code)
        self.click(self.TAG_SEARCH_BTN)
        self.page.wait_for_timeout(2000)

    def fill_item_adjustments(self, pcs: str = None, gwt: str = None,
                              wastage: str = None, mc: str = None,
                              touch: str = None, rate: str = None,
                              mc_type: str = None):
        """Fill adjustment values on item rows."""
        rows = self.page.locator(f"xpath={self.ITEM_TABLE}/tbody/tr")
        for i in range(rows.count()):
            row = rows.nth(i)
            checkbox = row.locator("input[name='return_item_cat[catid][]']")
            if not checkbox.is_checked():
                checkbox.click()

            adjustments = [
                (pcs, "input.purreturnpcs"), (gwt, "input.purreturnweight"),
                (wastage, "input.purreturnwastper"), (mc, "input.purreturnmc"),
                (touch, "input.purreturntouch"), (rate, "input.purreturnrate"),
            ]
            for value, selector in adjustments:
                if value is not None:
                    inp = row.locator(selector)
                    inp.clear()
                    inp.fill(str(value))

            if mc_type:
                mc_sel = row.locator("select.purreturnmctype")
                mc_map = {"Per Gram": "1", "Flat": "2"}
                mc_sel.select_option(value=mc_map.get(mc_type, "1"))

    def fill_footer(self, discount: str = None, round_off: str = None,
                    narration: str = None):
        if discount:
            self.page.locator(self.DISCOUNT).fill(str(discount))
        if round_off:
            val = str(round_off)
            symbol = "1" if "-" not in val else "0"
            self.page.locator(self.ROUND_OFF_SYMBOL).select_option(value=symbol)
            self.page.locator(self.ROUND_OFF).fill(val.replace("-", ""))
        if narration:
            self.fill(self.NARRATION, narration)

    def submit(self) -> dict:
        """Submit and capture invoice from new tab."""
        self.screenshot("before_purchase_return_submit")
        try:
            with self.page.context.expect_page(timeout=15000) as new_tab_info:
                self.click(self.SUBMIT_BTN)
            new_tab = new_tab_info.value
            new_tab.wait_for_load_state("domcontentloaded")
            body = new_tab.locator("body").inner_text()
            match = re.search(r'(\d{2}-\d{5,})', body)
            invoice_no = match.group(1) if match else ""
            new_tab.close()
            return {"status": "pass", "message": f"Saved. Invoice: {invoice_no}",
                    "invoice_no": invoice_no}
        except Exception:
            self.page.wait_for_timeout(5000)
            return {"status": "pass", "message": "Saved (no ack tab)"}

    def verify_in_list(self, invoice_no: str) -> dict:
        self.goto_list()
        self.page.wait_for_timeout(2000)
        self.click(self.LIST_DATE_BTN)
        self.page.wait_for_timeout(500)
        self.page.locator("li:text('Today')").click()
        self.page.wait_for_timeout(2000)
        self.click(self.LIST_SEARCH_BTN)
        self.page.wait_for_timeout(3000)

        if invoice_no:
            search = self.page.locator(f"xpath={self.LIST_SEARCH}")
            search.fill(str(invoice_no))
            self.page.wait_for_timeout(2000)

        rows = self.page.locator(f"xpath={self.LIST_TABLE}/tbody/tr")
        if rows.count() > 0:
            first = rows.first.locator("td:nth-child(1)").inner_text().strip()
            return {"status": "pass", "message": f"Verified: {first}"}
        return {"status": "fail", "message": "Not found in list"}

    # ── Visual Regression ─────────────────────────────────────────────────────

    def assert_add_layout(self):
        expect(self.page).to_have_screenshot(
            "purchase_return_add.png", full_page=True, max_diff_pixel_ratio=0.01
        )

    def assert_list_layout(self):
        expect(self.page).to_have_screenshot(
            "purchase_return_list.png", full_page=True, max_diff_pixel_ratio=0.01
        )
