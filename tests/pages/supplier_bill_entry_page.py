"""
SupplierBillEntryPage — Page object for Supplier Bill Entry module.

Extracted from: Sparqla/Test_Purchase/SupplierBillEntry.py (1099 lines)
Covers: 3-tab form (Bill Details, Items, Summary), GRN selection,
        Purchase Type (Ornament/Bullion/Stone), Against Order,
        item category/product/purity/design/sub-design,
        pcs/gwt/lwt/wastage/mc/touch/rate entry, add item,
        calculation engine (6 formulas), cancel/edit flows,
        print tab capture, list verification + approval.
"""
import logging
import re
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class SupplierBillEntryPage(BasePage):
    """Page object for Supplier Bill Entry (Purchase Module)."""

    # ── Tab 1: Bill Details ───────────────────────────────────────────────────
    GRN_SELECT2 = '//select[@id="select_grn"]/following-sibling::span'
    SELECT2_SEARCH = '//span[@class="select2-search select2-search--dropdown"]/input'

    TYPE_ORNAMENT = '//input[@id="oranment_type"]'
    TYPE_BULLION = '//input[@id="mt_type"]'
    TYPE_STONE = '//input[@id="st_type"]'

    AGAINST_ORDER = '//input[@id="aganist_order"]'
    AGAINST_PURCHASE = '//input[@id="aganist_purchase"]'

    PURCHASE_CATEGORY = "#purchase_type"
    HALLMARK_YES = '//input[@name="order[is_halmerked]" and @value="1"]'
    HALLMARK_NO = '//input[@name="order[is_halmerked]" and @value="0"]'
    REF_NO = '//input[@name="order[po_supplier_ref_no]"]'

    AGAINST_KAR_ISSUE_YES = '//input[@name="aganist_karigar_metal_issue" and @value="1"]'
    AGAINST_KAR_ISSUE_NO = '//input[@name="aganist_karigar_metal_issue" and @value="0"]'

    # ── Tab 2: Item Details ───────────────────────────────────────────────────
    TAB_ITEMS = '//a[@id="tab_items"]'
    PO_SELECT2 = '//select[@id="select_po_no"]/following-sibling::span'
    CLOSE_ORDER_BTN = '//button[@id="close_order_details"]'

    CATEGORY_SELECT2 = '//select[@id="select_category"]/following-sibling::span'
    PURITY_SELECT2 = '//select[@id="select_purity"]/following-sibling::span'
    PRODUCT_SELECT2 = '//select[@id="select_product"]/following-sibling::span'
    DESIGN_SELECT2 = '//select[@id="select_design"]/following-sibling::span'
    SUB_DESIGN_SELECT2 = '//select[@id="select_sub_design"]/following-sibling::span'

    PCS_INPUT = "#tot_pcs"
    GWT_INPUT = "#tot_gwt"
    LWT_INPUT = "#less_wt"
    MC_TYPE = "#mc_type"
    CALC_TYPE = "#karigar_calc_type"
    WASTAGE_PCT = "#wastage_per"
    PURITY_CALC = "#purity_calc_type"
    TOUCH_INPUT = "#purchase_touch"
    MC_VALUE = "#mc_value"
    RATE_PER_GRAM = "#rate_per_gram"
    ADD_ITEM_BTN = '//button[@id="add_po_order_items"]'

    # ── Tab 3: Summary ────────────────────────────────────────────────────────
    TAB_SUMMARY = '//li[@id="tab_tot_summary"]/a'

    # ── Save ──────────────────────────────────────────────────────────────────
    SUBMIT_BTN = '//button[@id="submit_pur_entry"]'

    # ── List Page ─────────────────────────────────────────────────────────────
    LIST_DATE_BTN = '//button[@id="sbe-dt-btn"]'
    LIST_SEARCH = '//div[@id="pur_entry_list_filter"]//input'
    LIST_TABLE = '//table[@id="pur_entry_list"]'

    # ── Cancel Modal ──────────────────────────────────────────────────────────
    CANCEL_MODAL = "#confirm-delete"
    CANCEL_REMARK = "#po_cancel_remark"
    CANCEL_CONFIRM = '//button[@id="cancel_po"]'

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto(self):
        self.navigate_via_menu(
            "Purchase Module", "Supplier Bill Entry",
            fallback_path="index.php/admin_ret_purchase/pur_entry/list"
        )
        return self

    def click_add(self):
        self.click('//a[@id="add_Order"]')
        self.page.wait_for_timeout(3000)

    # ── Tab 1 ─────────────────────────────────────────────────────────────────

    def fill_tab1(self, grn_number: str = None, purchase_type: str = None,
                  against_order: str = None, purchase_category: str = None,
                  hallmark: str = None, ref_no: str = None,
                  against_karigar_issue: str = None):
        """Fill Bill Details tab."""
        if grn_number:
            self.select2(self.GRN_SELECT2, str(grn_number), self.SELECT2_SEARCH)
            self.page.wait_for_timeout(2000)

        if purchase_type:
            ptype = purchase_type.lower()
            if "oranment" in ptype or "ornament" in ptype:
                self.click(self.TYPE_ORNAMENT)
            elif "bullion" in ptype:
                self.click(self.TYPE_BULLION)
            elif "stone" in ptype:
                self.click(self.TYPE_STONE)
            self.page.wait_for_timeout(1000)

        if against_order:
            if against_order.lower() == "yes":
                self.click(self.AGAINST_ORDER)
            else:
                self.click(self.AGAINST_PURCHASE)
            self.page.wait_for_timeout(1000)

        if purchase_category:
            self.select_native(self.PURCHASE_CATEGORY, purchase_category)

        if hallmark:
            self.click(self.HALLMARK_YES if hallmark.lower() == "yes" else self.HALLMARK_NO)

        if ref_no:
            self.fill(self.REF_NO, str(ref_no))

        if against_karigar_issue:
            self.click(
                self.AGAINST_KAR_ISSUE_YES if against_karigar_issue.lower() == "yes"
                else self.AGAINST_KAR_ISSUE_NO
            )

    def goto_tab_items(self):
        self.click(self.TAB_ITEMS)
        self.page.wait_for_timeout(1000)

    # ── Tab 2 ─────────────────────────────────────────────────────────────────

    def fill_item(self, category: str = None, purity: str = None,
                  product: str = None, design: str = None,
                  sub_design: str = None, pcs: str = None,
                  gwt: str = None, lwt: str = None,
                  mc_type: str = None, calc_type: str = None,
                  wastage_pct: str = None, purity_calc: str = None,
                  touch: str = None, mc_value: str = None,
                  rate_per_gram: str = None, po_number: str = None,
                  against_order: str = None):
        """Fill single item in Tab 2."""
        if against_order == "yes" and po_number:
            self.select2(self.PO_SELECT2, str(po_number), self.SELECT2_SEARCH)
            self.page.wait_for_timeout(1000)
            self.click(self.CLOSE_ORDER_BTN)
            self.page.wait_for_timeout(1000)

        select2_fields = [
            (category, self.CATEGORY_SELECT2),
            (purity, self.PURITY_SELECT2),
            (product, self.PRODUCT_SELECT2),
            (design, self.DESIGN_SELECT2),
            (sub_design, self.SUB_DESIGN_SELECT2),
        ]
        for value, selector in select2_fields:
            if value:
                self.select2(selector, str(value), self.SELECT2_SEARCH)
                self.page.wait_for_timeout(500)

        if pcs:
            self.fill(self.PCS_INPUT, str(pcs))
        if gwt:
            self.fill(self.GWT_INPUT, str(gwt))
        if lwt:
            self.fill(self.LWT_INPUT, str(lwt))
            self.page.wait_for_timeout(500)

        if mc_type:
            self.select_native(self.MC_TYPE, mc_type)

        if calc_type:
            self.select_native(self.CALC_TYPE, calc_type)
            self.page.wait_for_timeout(1000)

        is_purchase_touch = calc_type and "purchase touch" in calc_type.lower()
        if wastage_pct and not is_purchase_touch:
            self.fill(self.WASTAGE_PCT, str(wastage_pct))
            self.page.wait_for_timeout(500)

        if purity_calc:
            self.select_native(self.PURITY_CALC, str(purity_calc))
            self.page.wait_for_timeout(500)

        if touch:
            self.fill(self.TOUCH_INPUT, str(touch))
        if mc_value:
            self.fill(self.MC_VALUE, str(mc_value))
        if rate_per_gram:
            self.fill(self.RATE_PER_GRAM, str(rate_per_gram))
            self.page.wait_for_timeout(1000)

        # Add item
        self.click(self.ADD_ITEM_BTN)
        self.page.wait_for_timeout(2000)

    def goto_tab_summary(self):
        self.click(self.TAB_SUMMARY)
        self.page.wait_for_timeout(1000)

    def submit(self) -> dict:
        """Submit and handle print tab."""
        self.screenshot("before_sbe_submit")

        try:
            with self.page.context.expect_page(timeout=15000) as new_tab_info:
                self.click(self.SUBMIT_BTN)
            new_tab = new_tab_info.value
            new_tab.wait_for_load_state("domcontentloaded")
            url = new_tab.url
            match = re.search(r'/(\d+)$', url)
            job_id = match.group(1) if match else ""
            new_tab.close()
        except Exception:
            job_id = ""

        self.page.wait_for_timeout(5000)

        try:
            success = self.page.locator("div.alert-success")
            success.wait_for(state="visible", timeout=5000)
            msg = success.inner_text().strip()
            return {"status": "pass", "message": msg, "job_id": job_id}
        except Exception:
            return {"status": "fail", "message": "No success message"}

    def verify_in_list(self, search_term: str) -> dict:
        search = self.page.locator(f"xpath={self.LIST_SEARCH}")
        search.clear()
        search.fill(str(search_term))
        self.page.wait_for_timeout(2000)

        rows = self.page.locator(
            f"xpath={self.LIST_TABLE}//tr[contains(., '{search_term}')]"
        )
        if rows.count() > 0:
            po_no = rows.first.locator("td:nth-child(5)").inner_text().strip()
            return {"status": "pass", "message": f"Verified: {po_no}",
                    "po_no": po_no}
        return {"status": "fail", "message": "Not found in list"}

    # ── Calculation Engine ────────────────────────────────────────────────────

    @staticmethod
    def calculate_expected(gwt: float, lwt: float, wastage_pct: float,
                           touch: float, mc_value: float, rate: float,
                           mc_type: str = "Per Gram", calc_type: str = "",
                           purity_calc: float = 999, pcs: float = 1,
                           calc_based_on: str = "Mc on Gross, Wast On Net") -> dict:
        """
        Pure-Python calculation engine for Supplier Bill Entry.
        Verified against 6 scenarios from Sparqla.
        """
        nwt = round(gwt - lwt, 3)
        is_pt = "purchase touch" in calc_type.lower()
        is_wxr = "weight x rate" in calc_type.lower()
        cbl = calc_based_on.lower()

        purity_base = 100.0 if int(purity_calc) == 999 else (purity_calc / 10.0)

        # Wastage weight
        if is_pt:
            wastage_wgt = 0.0
        elif "wast on net" in cbl:
            wastage_wgt = round(nwt * wastage_pct / 100, 3)
        else:
            wastage_wgt = round(gwt * wastage_pct / 100, 3)

        # Pure weight
        nwt_touch = round(nwt * touch / purity_base, 3)
        if is_pt:
            pure = nwt_touch
        elif is_wxr:
            pure = round(nwt_touch + wastage_wgt, 3)
        else:
            eff = round(nwt + wastage_wgt, 3)
            pure = round(eff * touch / purity_base, 3)

        # MC
        mc_base = gwt if "mc on gross" in cbl else nwt
        if mc_type.lower() == "per gram":
            mc_amount = round(mc_value * mc_base, 2)
        elif mc_type.lower() == "flat":
            mc_amount = round(mc_value, 2)
        elif mc_type.lower() == "per piece":
            mc_amount = round(mc_value * pcs, 2)
        else:
            mc_amount = round(mc_value * mc_base, 2)

        taxable = round(pure * rate + mc_amount, 2)
        tax = round(taxable * 3 / 100, 2)
        total = round(taxable + tax, 2)

        return {
            "nwt": nwt, "wastage_wgt": wastage_wgt, "pure": pure,
            "mc_amount": mc_amount, "taxable": taxable,
            "tax": tax, "total": total,
        }

    # ── Visual Regression ─────────────────────────────────────────────────────

    def assert_form_layout(self):
        expect(self.page).to_have_screenshot(
            "supplier_bill_form.png", full_page=True, max_diff_pixel_ratio=0.01
        )

    def assert_list_layout(self):
        expect(self.page).to_have_screenshot(
            "supplier_bill_list.png", full_page=True, max_diff_pixel_ratio=0.01
        )
