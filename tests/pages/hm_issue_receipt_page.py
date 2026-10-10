"""
HMIssueReceiptPage — Page object for HM Issue/Receipt module.

Extracted from: Sparqla/Test_Purchase/HMIssueReceipt.py (319 lines)
Covers: Issue flow (PO Ref → select all → issue submit),
        Receipt flow (HM Ref → vendor ref → charges → rejected → submit),
        list verification with status check.
"""
import logging
import random
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class HMIssueReceiptPage(BasePage):
    """Page object for HM Issue/Receipt (Purchase Module)."""

    # ── Navigation Buttons ────────────────────────────────────────────────────
    ISSUE_BTN = '//a[@id="add_Order" and contains(@class, "btn-success")]'
    RECEIPT_BTN = '//a[@id="add_Order" and contains(@class, "btn-primary")]'

    # ── Issue Form ────────────────────────────────────────────────────────────
    PO_REF_SELECT2 = '//select[@id="select_po_ref_no"]/following-sibling::span'
    KARIGAR_SELECT2 = '//select[@id="select_karigar"]/following-sibling::span'
    SELECT2_SEARCH = '//span[@class="select2-search select2-search--dropdown"]/input'
    SELECT_ALL = '//input[@id="select_all"]'
    ISSUE_SUBMIT = '//button[@id="halmarking_issue"]'

    # ── Receipt Form ──────────────────────────────────────────────────────────
    HM_REF_SELECT2 = '//select[@id="select_hm_ref_no"]/following-sibling::span'
    VENDOR_REF_INPUT = "#hm_vendor_ref_id"
    ITEM_DETAIL_TABLE = '//table[@id="item_detail"]'
    RECEIPT_SUBMIT = '//button[@id="hm_receipt_submit"]'

    # ── List Page ─────────────────────────────────────────────────────────────
    LIST_SEARCH = '//div[@id="item_list_filter"]//input'
    LIST_TABLE = '//table[@id="item_list"]'

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto(self):
        self.navigate_via_menu(
            "Purchase Module", "HM Issue/ Receipt",
            fallback_path="index.php/admin_ret_purchase/hallmarking/list"
        )
        return self

    # ── Issue Flow ────────────────────────────────────────────────────────────

    def click_issue(self):
        self.click(self.ISSUE_BTN)
        self.page.wait_for_timeout(3000)

    def fill_issue(self, po_ref_no: str, karigar: str = None):
        if po_ref_no:
            self.select2(self.PO_REF_SELECT2, po_ref_no, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(2000)
        if karigar:
            self.select2(self.KARIGAR_SELECT2, karigar, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(1000)

        self.click(self.SELECT_ALL)
        self.page.wait_for_timeout(1000)

    def submit_issue(self) -> dict:
        self.screenshot("before_hm_issue")
        self.click(self.ISSUE_SUBMIT)
        self.page.wait_for_timeout(5000)

        try:
            success = self.page.locator("div.alert-success")
            success.wait_for(state="visible", timeout=5000)
            msg = success.inner_text().strip()

            if "Issued successfully" in msg:
                hm_ref = ""
                try:
                    hm_ref = self.get_text(f"{self.LIST_TABLE}/tbody/tr[1]/td[1]")
                except Exception:
                    pass
                return {"status": "pass", "message": msg, "hm_ref": hm_ref}
            return {"status": "fail", "message": msg}
        except Exception:
            return {"status": "fail", "message": "No success message"}

    # ── Receipt Flow ──────────────────────────────────────────────────────────

    def click_receipt(self):
        self.click(self.RECEIPT_BTN)
        self.page.wait_for_timeout(5000)

    def fill_receipt(self, hm_ref_no: str, vendor_ref: str = None,
                     hm_charge: str = "10",
                     rejected_pcs: str = None, rejected_gwt: str = None):
        self.select2(self.HM_REF_SELECT2, hm_ref_no, self.SELECT2_SEARCH)
        self.page.wait_for_timeout(2000)

        # Vendor Ref
        ref = vendor_ref or f"VRN{random.randint(1000, 9999)}"
        self.fill(self.VENDOR_REF_INPUT, ref)

        # Fill charges and rejections per row
        rows = self.page.locator(f"xpath={self.ITEM_DETAIL_TABLE}/tbody/tr")
        for i in range(rows.count()):
            idx = i + 1
            if rejected_pcs is not None:
                inp = self.page.locator(
                    f"xpath=(//input[contains(@class, 'hm_rejected_pcs')])[{idx}]"
                )
                inp.fill(str(rejected_pcs))
            if rejected_gwt is not None:
                inp = self.page.locator(
                    f"xpath=(//input[contains(@class, 'hm_rejected_gwt')])[{idx}]"
                )
                inp.fill(str(rejected_gwt))
            # HM Charge
            charge_inp = self.page.locator(
                f"xpath=(//input[contains(@class, 'hm_charges')])[{idx}]"
            )
            charge_inp.fill(str(hm_charge))
            charge_inp.press("Tab")
            self.page.wait_for_timeout(500)

    def submit_receipt(self) -> dict:
        self.screenshot("before_hm_receipt")
        self.click(self.RECEIPT_SUBMIT)
        self.page.wait_for_timeout(5000)

        try:
            success = self.page.locator("div.alert-success")
            success.wait_for(state="visible", timeout=5000)
            msg = success.inner_text().strip()
            if "Receipt successfully" in msg:
                return {"status": "pass", "message": msg}
            return {"status": "fail", "message": msg}
        except Exception:
            return {"status": "fail", "message": "No success message"}

    # ── List Verification ─────────────────────────────────────────────────────

    def verify_in_list(self, search_term: str,
                       expected_status: str = "Issued") -> dict:
        search = self.page.locator(f"xpath={self.LIST_SEARCH}")
        search.fill(str(search_term))
        self.page.wait_for_timeout(2000)

        status_xpath = (f'{self.LIST_TABLE}/tbody/tr[contains(., "{search_term}")]'
                        f'/td[9][contains(., "{expected_status}")]')
        matched = self.page.locator(f"xpath={status_xpath}")
        if matched.count() > 0:
            return {"status": "pass",
                    "message": f"Verified: {search_term} = {expected_status}"}
        return {"status": "fail",
                "message": f"{expected_status} not found for {search_term}"}

    # ── Visual Regression ─────────────────────────────────────────────────────

    def assert_list_layout(self):
        expect(self.page).to_have_screenshot(
            "hm_issue_receipt_list.png", full_page=True, max_diff_pixel_ratio=0.01
        )
