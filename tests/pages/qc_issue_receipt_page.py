"""
QCIssueReceiptPage — Page object for QC Issue/Receipt module.

Extracted from: Sparqla/Test_Purchase/QCIssueReceipt.py (317 lines)
Covers: Issue flow (PO Ref → employee → select all → submit),
        Receipt flow (QC Ref → search → rejected pcs/gwt/lwt → submit),
        list verification with action button check.
"""
import logging
from playwright.sync_api import Page, expect
from pages.base_page import BasePage, BASE_URL

logger = logging.getLogger(__name__)


class QCIssueReceiptPage(BasePage):
    """Page object for QC Issue/Receipt (Purchase Module)."""

    # ── Navigation ────────────────────────────────────────────────────────────
    ISSUE_BTN = '//a[@id="add_Order" and contains(@class, "btn-success")]'
    RECEIPT_BTN = '//a[@id="add_Order" and contains(@class, "btn-primary")]'

    # ── Issue Form ────────────────────────────────────────────────────────────
    PO_REF_SELECT2 = '//select[@id="select_po_ref_no"]/following-sibling::span'
    EMP_SELECT2 = '//select[@id="emp_select"]/following-sibling::span'
    SELECT2_SEARCH = '//span[@class="select2-search select2-search--dropdown"]/input'
    SELECT_ALL = '//input[@id="select_all"]'
    ISSUE_SUBMIT = '//button[@id="qc_issue_submit"]'

    # ── Receipt Form ──────────────────────────────────────────────────────────
    QC_REF_SELECT2 = '//select[@id="select_ref_no"]/following-sibling::span'
    QC_SEARCH_BTN = '//button[@id="po_item_search"]'
    ITEM_TABLE = '//table[@id="item_detail"]'
    RECEIPT_SUBMIT = '//button[@id="update_qc_status"]'

    # ── List Page ─────────────────────────────────────────────────────────────
    LIST_SEARCH = '//div[@id="item_list_filter"]//input'
    LIST_TABLE = '//table[@id="item_list"]'

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto(self):
        self.navigate("index.php/admin_ret_purchase/qc_issue_receipt/list")
        return self

    def click_issue(self):
        self.click(self.ISSUE_BTN)
        self.page.wait_for_timeout(3000)

    def click_receipt(self):
        self.click(self.RECEIPT_BTN)
        self.page.wait_for_timeout(2000)

    def fill_issue(self, po_ref_no: str, employee: str = None):
        if po_ref_no:
            self.select2(self.PO_REF_SELECT2, po_ref_no, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(2000)
        if employee:
            self.select2(self.EMP_SELECT2, employee, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(1000)
        self.click(self.SELECT_ALL)
        self.page.wait_for_timeout(1000)

    def submit_issue(self) -> dict:
        self.screenshot("before_qc_issue")
        self.click(self.ISSUE_SUBMIT)
        self.page.wait_for_timeout(5000)
        try:
            success = self.page.locator("div.alert-success")
            success.wait_for(state="visible", timeout=5000)
            msg = success.inner_text().strip()
            if "QC Issued successfully" in msg:
                ref = ""
                try:
                    ref = self.get_text(f"{self.LIST_TABLE}/tbody/tr[1]/td[2]")
                except Exception:
                    pass
                return {"status": "pass", "message": msg, "qc_ref": ref}
            return {"status": "fail", "message": msg}
        except Exception:
            return {"status": "fail", "message": "No success message"}

    def fill_receipt(self, qc_ref_no: str, rejected_pcs: str = None,
                     rejected_gwt: str = None, rejected_lwt: str = None):
        self.select2(self.QC_REF_SELECT2, qc_ref_no, self.SELECT2_SEARCH)
        self.page.wait_for_timeout(500)
        self.click(self.QC_SEARCH_BTN)
        self.page.wait_for_timeout(5000)

        if rejected_pcs is not None:
            self.page.locator("xpath=(//input[contains(@class, 'failed_pcs')])[1]").fill(str(rejected_pcs))
        if rejected_gwt is not None:
            self.page.locator("xpath=(//input[contains(@class, 'failed_gwt')])[1]").fill(str(rejected_gwt))
        if rejected_lwt is not None:
            self.page.locator("xpath=(//input[contains(@class, 'failed_lwt')])[1]").fill(str(rejected_lwt))

        self.page.locator("xpath=(//input[contains(@class, 'failed_pcs')])[1]").press("Tab")
        self.page.wait_for_timeout(2000)

    def submit_receipt(self) -> dict:
        self.screenshot("before_qc_receipt")
        self.click(self.RECEIPT_SUBMIT)
        self.page.wait_for_timeout(5000)
        try:
            success = self.page.locator("div.alert-success")
            success.wait_for(state="visible", timeout=5000)
            msg = success.inner_text().strip()
            if "Updated successfully" in msg or "Issued successfully" in msg:
                return {"status": "pass", "message": msg}
            return {"status": "fail", "message": msg}
        except Exception:
            return {"status": "fail", "message": "No success message"}

    def verify_in_list(self, search_term: str, expected_flow: str = "Issue") -> dict:
        search = self.page.locator(f"xpath={self.LIST_SEARCH}")
        search.fill(str(search_term))
        self.page.wait_for_timeout(2000)
        action_html = self.page.locator(
            f"xpath={self.LIST_TABLE}/tbody/tr[contains(., '{search_term}')]/td[15]"
        ).inner_html()
        if expected_flow == "Issue":
            if "QC Issue Edit" in action_html:
                return {"status": "pass", "message": "Issued — QC Issue Edit visible"}
            return {"status": "fail", "message": "QC Issue Edit not found"}
        else:
            if "QC Issue Edit" not in action_html:
                return {"status": "pass", "message": "Receipt complete"}
            return {"status": "fail", "message": "QC Issue Edit still visible"}

    def assert_list_layout(self):
        expect(self.page).to_have_screenshot(
            "qc_issue_receipt_list.png", full_page=True, max_diff_pixel_ratio=0.01
        )

    # ── Single-piece partial-weight restriction ─────────────────────────────────
    # Support for restrict_partial_wt_single_pcs (see database/migrations/
    # 20260731_000001_restrict_partial_wt_single_pcs.sql). "Row" below always means
    # a <tr> in #item_detail — QC Issue rows expose .qc_issue_pcs/.qc_issue_gross_wt,
    # QC Receipt rows expose .failed_pcs/.failed_gwt.

    def is_locked(self, css_class: str, row: int = 1) -> bool:
        """Whether the given input class on the Nth (1-based) #item_detail row is readonly."""
        locator = self.page.locator(f"xpath=({self.ITEM_TABLE}/tbody/tr)[{row}]//input[contains(@class, '{css_class}')]")
        return locator.get_attribute("readonly") is not None

    def is_issue_row_locked(self, row: int = 1) -> dict:
        return {
            "pcs": self.is_locked("qc_issue_pcs", row),
            "gwt": self.is_locked("qc_issue_gross_wt", row),
        }

    def is_receipt_gwt_locked(self, row: int = 1) -> bool:
        return self.is_locked("failed_gwt", row)

    def force_set(self, css_class: str, value: str, row: int = 1):
        """Strip readonly and set a value — simulates a devtools tamper of a locked input."""
        locator = self.page.locator(f"xpath=({self.ITEM_TABLE}/tbody/tr)[{row}]//input[contains(@class, '{css_class}')]")
        locator.evaluate("el => el.removeAttribute('readonly')")
        locator.fill(str(value))
        locator.dispatch_event("change")

    def get_toaster_message(self, timeout: int = 5000) -> str:
        """Read the $.toaster popup text (see base_page.get_toast_message — same #toaster markup)."""
        return self.get_toast_message(timeout)

    def is_overlay_hidden(self) -> bool:
        overlay = self.page.locator("div.overlay")
        style = overlay.get_attribute("style") or ""
        return "display: none" in style or "display:none" in style

    def is_submit_enabled(self, selector: str) -> bool:
        return not self.page.locator(f"xpath={selector}").is_disabled()

    def api_update_qc_issue(self, payload: dict) -> dict:
        """POST directly to update_qc_issue, bypassing the JS/UI entirely — proves backend enforcement."""
        resp = self.page.request.post(f"{BASE_URL}/index.php/admin_ret_purchase/update_qc_issue", form=payload)
        return resp.json()

    def api_qc_issue_update(self, payload: dict) -> dict:
        """POST directly to qc_issue_update (edit-mode QC Issue save)."""
        resp = self.page.request.post(f"{BASE_URL}/index.php/admin_ret_purchase/qc_issue_update", form=payload)
        return resp.json()

    def api_update_qc_status(self, payload: dict) -> dict:
        """POST directly to update_qc_status (QC Receipt save), bypassing the JS/UI."""
        resp = self.page.request.post(f"{BASE_URL}/index.php/admin_ret_purchase/update_qc_status", form=payload)
        return resp.json()

    def api_resolve_po_id(self, po_ref_no: str) -> dict:
        """Resolve a PO Ref No to {po_id, fin_year_code} via qc_issue_receipt/purchase_issue (the same
        AJAX call the QC Issue PO dropdown uses). Returns {} if not found among pending-QC POs."""
        resp = self.page.request.post(
            f"{BASE_URL}/index.php/admin_ret_purchase/qc_issue_receipt/purchase_issue", form={"fin_year": ""}
        )
        for row in resp.json():
            if str(row.get("po_ref_no")) == str(po_ref_no):
                return row
        return {}

    def api_get_issue_item(self, po_id, fin_year="") -> dict:
        """First pending QC-Issue line item balance for a po_id, via qc_issue_receipt/qc_item_details
        (same AJAX call the QC Issue grid uses). Returns {} if the PO has no pending balance."""
        resp = self.page.request.post(
            f"{BASE_URL}/index.php/admin_ret_purchase/qc_issue_receipt/qc_item_details",
            form={"po_id": po_id, "fin_year": fin_year},
        )
        rows = resp.json()
        return rows[0] if rows else {}

    def api_get_receipt_item(self, qc_ref_no) -> dict:
        """First pending QC-Receipt line item for a qc_process_id, via get_purchase_issue_entry_items
        (same AJAX call the QC Receipt grid uses). Returns {} if none pending."""
        resp = self.page.request.post(
            f"{BASE_URL}/index.php/admin_ret_purchase/get_purchase_issue_entry_items",
            form={"qc_ref_no": qc_ref_no},
        )
        rows = resp.json()
        return rows[0] if rows else {}
