"""
ApprovalToInvoicePage — Page object for Approval To Invoice Conversion module.

Extracted from: Sparqla/Test_Purchase/ApprovalToInvoice.py (300 lines)
Covers: Opening balance, rate cut type (Amount/Pure to Amount/Amount to Pure),
        convert to (Supplier/Manufacturer/etc), conversion type (Fix/Unfix),
        supplier/ref/metal/category/product Select2, pure weight/amount/rate,
        remark, submit with dialog/redirect handling.
"""
import logging
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class ApprovalToInvoicePage(BasePage):
    """Page object for Approval To Invoice (Purchase Module)."""

    # ── Header ────────────────────────────────────────────────────────────────
    ADD_BTN = '//a[@id="add_Order"]'

    # ── Opening Balance ───────────────────────────────────────────────────────
    OP_BAL_YES = '//input[@name="supplier_rate_cut[is_opening_blc]" and @value="1"]'
    OP_BAL_NO = '//input[@name="supplier_rate_cut[is_opening_blc]" and @value="0"]'

    # ── Rate Cut Type ─────────────────────────────────────────────────────────
    RCT_AMOUNT = '//input[@name="supplier_rate_cut[rate_cut_type]" and @value="1"]'
    RCT_PURE_TO_AMOUNT = '//input[@name="supplier_rate_cut[rate_cut_type]" and @value="2"]'
    RCT_AMOUNT_TO_PURE = '//input[@name="supplier_rate_cut[rate_cut_type]" and @value="3"]'

    # ── Convert To ────────────────────────────────────────────────────────────
    CONVERT_SUPPLIER = '//input[@name="supplier_rate_cut[convert_to]" and @value="1"]'
    CONVERT_MANUFACTURER = '//input[@name="supplier_rate_cut[convert_to]" and @value="2"]'

    # ── Conversion Type ───────────────────────────────────────────────────────
    CONV_FIX = '//input[@name="supplier_rate_cut[conversion_type]" and @value="1"]'
    CONV_UNFIX = '//input[@name="supplier_rate_cut[conversion_type]" and @value="2"]'

    # ── Select2 ───────────────────────────────────────────────────────────────
    SUPPLIER_SELECT2 = "//span[@id='select2-select_karigar-container']"
    PO_REF_SELECT2 = "//span[@id='select2-select_po_ref_no-container']"
    OPENING_REF_SELECT2 = "//span[@id='select2-opening_ref_no-container']"
    METAL_SELECT2 = "//span[@id='select2-select_metal-container']"
    CATEGORY_SELECT2 = "//span[@id='select2-select_category-container']"
    PRODUCT_SELECT2 = "//span[@id='select2-select_product-container']"
    SELECT2_SEARCH = '//span[@class="select2-search select2-search--dropdown"]/input'

    # ── Value Fields ──────────────────────────────────────────────────────────
    PURE_WEIGHT = '//input[@name="supplier_rate_cut[type2_wt]"]'
    CHARGES_AMOUNT = '//input[@name="supplier_rate_cut[charges_amount]"]'
    RATE = '//input[@name="supplier_rate_cut[src_rate]"]'
    REMARK = '//textarea[@name="supplier_rate_cut[src_remark]"]'

    SUBMIT_BTN = '//button[@id="supplier_rate_cut_submit"]'

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto(self):
        self.navigate("index.php/admin_ret_purchase/supplier_rate_cut/list")
        return self

    def click_add(self):
        self.click(self.ADD_BTN)
        self.page.wait_for_timeout(2000)

    def set_opening_balance(self, is_opening: bool):
        self.click(self.OP_BAL_YES if is_opening else self.OP_BAL_NO)
        self.page.wait_for_timeout(1000)

    def set_rate_cut_type(self, rct: str):
        rct_map = {"amount": self.RCT_AMOUNT,
                   "pure to amount": self.RCT_PURE_TO_AMOUNT,
                   "amount to pure": self.RCT_AMOUNT_TO_PURE}
        self.click(rct_map.get(rct.lower(), self.RCT_PURE_TO_AMOUNT))
        self.page.wait_for_timeout(1000)

    def set_convert_to(self, convert_to: str):
        conv_map = {"supplier": self.CONVERT_SUPPLIER,
                    "manufacturer": self.CONVERT_MANUFACTURER}
        self.click(conv_map.get(convert_to.lower(), self.CONVERT_SUPPLIER))
        self.page.wait_for_timeout(1000)

    def set_conversion_type(self, conv_type: str):
        self.click(self.CONV_FIX if conv_type.lower() == "fix" else self.CONV_UNFIX)
        self.page.wait_for_timeout(1000)

    def fill_supplier(self, supplier: str):
        self.select2(self.SUPPLIER_SELECT2, supplier, self.SELECT2_SEARCH)
        self.page.wait_for_timeout(1000)

    def fill_ref_no(self, ref_no: str, is_opening: bool = False):
        target = self.OPENING_REF_SELECT2 if is_opening else self.PO_REF_SELECT2
        self.select2(target, ref_no, self.SELECT2_SEARCH)
        self.page.wait_for_timeout(1000)

    def fill_dropdowns(self, metal: str = None, category: str = None,
                       product: str = None):
        if metal:
            self.select2(self.METAL_SELECT2, metal, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(1000)
        if category:
            self.select2(self.CATEGORY_SELECT2, category, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(1000)
        if product:
            self.select2(self.PRODUCT_SELECT2, product, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(1000)

    def fill_values(self, pure_weight: str = None, amount: str = None,
                    rate: str = None):
        if pure_weight:
            self.page.locator(f"xpath={self.PURE_WEIGHT}").fill(str(pure_weight))
        if amount:
            self.page.locator(f"xpath={self.CHARGES_AMOUNT}").fill(str(amount))
        if rate:
            self.page.locator(f"xpath={self.RATE}").fill(str(rate))
            self.page.wait_for_timeout(1000)

    def fill_remark(self, remark: str):
        self.page.locator(f"xpath={self.REMARK}").fill(remark)

    def submit(self) -> dict:
        self.screenshot("before_approval_invoice_submit")
        dialog_msg = None
        def handle_dialog(dialog):
            nonlocal dialog_msg
            dialog_msg = dialog.message
            dialog.accept()
        self.page.on("dialog", handle_dialog)

        self.click(self.SUBMIT_BTN)
        self.page.wait_for_timeout(3000)

        if dialog_msg and "Successfully" in dialog_msg:
            return {"status": "pass", "message": dialog_msg}
        if "list" in self.page.url:
            return {"status": "pass", "message": "Redirected to list"}
        return {"status": "fail", "message": dialog_msg or "No confirmation"}

    def assert_add_layout(self):
        expect(self.page).to_have_screenshot(
            "approval_to_invoice_add.png", full_page=True, max_diff_pixel_ratio=0.01
        )
