"""
SmithCompanyOpBalPage — Page object for Smith/Company Opening Balance module.

Extracted from: Sparqla/Test_Purchase/SmithCompanyOpBal.py (331 lines)
Covers: Smith Stock flow, smith type (Supplier/Smith/Approval/Stone),
        balance type (Metal/Diamond), metal type (Ornament/Old Metal),
        karigar/metal/category/product Select2, weight fields (gross/net/dia/pure),
        weight/amount type (Credit/Debit), submit, list verification.
"""
import logging
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class SmithCompanyOpBalPage(BasePage):
    """Page object for Smith/Company Opening Balance (Purchase Module)."""

    # ── Stock Type ────────────────────────────────────────────────────────────
    SMITH_STOCK_RADIO = "#smth_stock"
    ADD_BTN = '//a[@id="add_Order"]'

    # ── Smith Type ────────────────────────────────────────────────────────────
    SMITH_TYPE_SUPPLIER = '//input[@name="smth_cmpy_stk[smith_type]" and @value="1"]'
    SMITH_TYPE_SMITH = '//input[@name="smth_cmpy_stk[smith_type]" and @value="2"]'

    # ── Balance Type ──────────────────────────────────────────────────────────
    BAL_TYPE_METAL = '//input[@name="smth_cmpy_stk[bal_type]" and @value="1"]'
    BAL_TYPE_DIAMOND = '//input[@name="smth_cmpy_stk[bal_type]" and @value="3"]'

    # ── Metal Type ────────────────────────────────────────────────────────────
    METAL_TYPE_ORNAMENT = '//input[@name="smth_cmpy_stk[metal_type]" and @value="1"]'
    METAL_TYPE_OLD = '//input[@name="smth_cmpy_stk[metal_type]" and @value="2"]'

    # ── Select2 Dropdowns ─────────────────────────────────────────────────────
    KARIGAR_SELECT2 = "//span[@id='select2-select_karigar-container']"
    METAL_SELECT2 = "//span[@id='select2-select_metal-container']"
    CATEGORY_SELECT2 = "//span[@id='select2-select_category-container']"
    PRODUCT_SELECT2 = "//span[@id='select2-select_product-container']"
    SELECT2_SEARCH = '//span[@class="select2-search select2-search--dropdown"]/input'

    # ── Weight Fields ─────────────────────────────────────────────────────────
    GROSS_WGT = '//input[@name="smth_cmpy_stk[op_wgt]"]'
    NET_WGT = '//input[@name="smth_cmpy_stk[op_net_wgt]"]'
    DIA_WGT = '//input[@name="smth_cmpy_stk[op_dia_wgt]"]'
    PURE_WGT = '//input[@name="smth_cmpy_stk[op_pure_wgt]"]'

    # ── Amount ────────────────────────────────────────────────────────────────
    AMOUNT = '//input[@name="smth_cmpy_stk[op_amt]"]'
    REMARK = '//textarea[@name="smth_cmpy_stk[remark]"]'
    SUBMIT_BTN = '//button[@id="smth_cmpy_bal_submit"]'

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto(self):
        self.navigate("index.php/admin_ret_purchase/smith_cmpy_op_bal/list")
        return self

    def click_add(self):
        self.click(self.ADD_BTN)
        self.page.wait_for_timeout(2000)

    def select_smith_type(self, smith_type: str):
        type_map = {"supplier": self.SMITH_TYPE_SUPPLIER, "smith": self.SMITH_TYPE_SMITH}
        self.click(type_map.get(smith_type.lower(), self.SMITH_TYPE_SUPPLIER))
        self.page.wait_for_timeout(1000)

    def select_balance_type(self, bal_type: str):
        self.click(self.BAL_TYPE_METAL if bal_type.lower() == "metal"
                   else self.BAL_TYPE_DIAMOND)
        self.page.wait_for_timeout(1000)

    def select_metal_type(self, metal_type: str):
        self.click(self.METAL_TYPE_ORNAMENT if metal_type.lower() != "old metal"
                   else self.METAL_TYPE_OLD)
        self.page.wait_for_timeout(1000)

    def fill_dropdowns(self, karigar: str = None, metal: str = None,
                       category: str = None, product: str = None):
        if karigar:
            self.select2(self.KARIGAR_SELECT2, karigar, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(1000)
        if metal:
            self.select2(self.METAL_SELECT2, metal, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(1000)
        if category:
            self.select2(self.CATEGORY_SELECT2, category, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(1000)
        if product:
            self.select2(self.PRODUCT_SELECT2, product, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(1000)

    def fill_weights(self, gross: str = None, net: str = None,
                     dia: str = None, pure: str = None,
                     wt_type: str = "credit"):
        if gross:
            self.page.locator(f"xpath={self.GROSS_WGT}").fill(str(gross))
        if net:
            self.page.locator(f"xpath={self.NET_WGT}").fill(str(net))
        if dia:
            self.page.locator(f"xpath={self.DIA_WGT}").fill(str(dia))
        if pure:
            self.page.locator(f"xpath={self.PURE_WGT}").fill(str(pure))

        wt_map = {"credit": "1", "debit": "2"}
        self.click(
            f'//input[@name="smth_cmpy_stk[wt_receipt_type]" and @value="{wt_map.get(wt_type.lower(), "1")}"]'
        )

    def fill_amount(self, amount: str, amt_type: str = "credit"):
        self.page.locator(f"xpath={self.AMOUNT}").fill(str(amount))
        amt_map = {"credit": "1", "debit": "2"}
        self.click(
            f'//input[@name="smth_cmpy_stk[amt_receipt_type]" and @value="{amt_map.get(amt_type.lower(), "1")}"]'
        )

    def fill_remark(self, remark: str):
        self.page.locator(f"xpath={self.REMARK}").fill(remark)

    def submit(self) -> dict:
        self.screenshot("before_op_bal_submit")
        self.click(self.SUBMIT_BTN)
        self.page.wait_for_timeout(3000)

        dialog_msg = None
        def handle_dialog(dialog):
            nonlocal dialog_msg
            dialog_msg = dialog.message
            dialog.accept()
        self.page.on("dialog", handle_dialog)
        self.page.wait_for_timeout(2000)

        if dialog_msg and "Successfully" in dialog_msg:
            return {"status": "pass", "message": dialog_msg}

        try:
            success = self.page.locator("div.alert-success")
            success.wait_for(state="visible", timeout=3000)
            return {"status": "pass", "message": success.inner_text().strip()}
        except Exception:
            if "list" in self.page.url:
                return {"status": "pass", "message": "Redirected to list"}
            return {"status": "fail", "message": dialog_msg or "No confirmation"}

    def assert_list_layout(self):
        expect(self.page).to_have_screenshot(
            "smith_op_bal_list.png", full_page=True, max_diff_pixel_ratio=0.01
        )
