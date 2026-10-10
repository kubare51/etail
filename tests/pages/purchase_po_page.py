"""
PurchasePOPage — Page object for Purchase Order module.

Extracted from: Sparqla/Test_Purchase/PurchasePO.py (1124 lines)
Covers: Stock Order, Customer Order, Stock Repair Order,
        single-item & multi-item creation, cancel flow,
        list verification, board rate integration.
"""
import logging
import re
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class PurchasePOPage(BasePage):
    """Page object for Purchase Order (Purchase Module)."""

    # ── Navigation ────────────────────────────────────────────────────────────
    ADD_ORDER_BTN = '//a[@id="add_Order"]'

    # ── Order For ─────────────────────────────────────────────────────────────
    STOCK_ORDER = '//input[@id="stock_order"]'
    CUSTOMER_ORDER = '//input[@id="cus_order"]'
    STOCK_REPAIR = '//input[@id="stock_repair_order"]'

    # ── Header ────────────────────────────────────────────────────────────────
    KARIGAR_SELECT2 = '//select[@id="select_karigar"]/following-sibling::span'
    BRANCH_SELECT2 = '//select[@id="branch_select"]/following-sibling::span'
    ORDER_NO_SELECT2 = '//select[@id="select_order_no"]/following-sibling::span'
    SELECT2_SEARCH = '//span[@class="select2-search select2-search--dropdown"]/input'
    RATE_FIXED = '//input[@id="rate_fixed"]'
    RATE_VALUE = "#rate_value"
    DUE_DATE = '//input[@name="order[smith_due_dt]"]'

    # ── Stock Order Items ─────────────────────────────────────────────────────
    PRODUCT_SELECT2 = '//select[@id="select_product"]/following-sibling::span'
    DESIGN_SELECT2 = '//select[@id="select_design"]/following-sibling::span'
    SUB_DESIGN_SELECT2 = '//select[@id="select_sub_design"]/following-sibling::span'
    WEIGHT_RANGE_SELECT2 = '//select[@id="select_weight_range"]/following-sibling::span'
    SIZE_SELECT2 = '//select[@id="select_size"]/following-sibling::span'
    PIECES_INPUT = "#tot_pcs"
    REMARK_INPUT = "#remark"
    ADD_ITEM_BTN = '//button[@id="add_order_item"]'

    # ── Submit ────────────────────────────────────────────────────────────────
    CREATE_ORDER_BTN = '//button[@id="create_order"]'
    CANCEL_BTN = '(//button[@class="btn btn-default btn-cancel"])[2]'

    # ── List Page ─────────────────────────────────────────────────────────────
    ORDER_LIST = '//table[@id="order_list"]'
    LIST_SEARCH = '//div[@id="order_list_filter"]//input'
    DATE_PICKER = '//button[@id="rpt_date_picker"]'
    ORDER_CANCEL_BTN = '//button[@id="order_cancel"]'
    ORDER_CLOSE_BTN = '//button[@id="order_close"]'
    PURCHASE_TABLE = '//table[@id="purchase_details"]'

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto(self):
        """Navigate to Purchase Order page."""
        self.navigate_via_menu(
            "Purchase Module", "Purchase Order",
            fallback_path="index.php/admin_ret_purchase/purchase/pur_order"
        )
        return self

    def click_add(self):
        self.click(self.ADD_ORDER_BTN)
        self.page.wait_for_timeout(3000)

    def select_order_for(self, order_for: str):
        """Stock Order, Customer Order, or Stock Repair Order."""
        order_map = {
            "Stock Order": self.STOCK_ORDER,
            "Customer Order": self.CUSTOMER_ORDER,
            "Stock Repair Order": self.STOCK_REPAIR,
        }
        xpath = order_map.get(order_for, self.STOCK_ORDER)
        self.click(xpath)
        self.page.wait_for_timeout(2000)

    def fill_header(self, karigar: str, due_date: str,
                    branch: str = None, rate_type: str = None,
                    rate_value: str = None, order_no: str = None):
        """Fill PO header fields."""
        self.select2(self.KARIGAR_SELECT2, karigar, self.SELECT2_SEARCH)

        if branch:
            self.select2(self.BRANCH_SELECT2, branch, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(2000)

        if rate_type == "Fixed":
            self.click(self.RATE_FIXED)
            self.page.wait_for_timeout(1000)

        if rate_value:
            self.fill(self.RATE_VALUE, str(rate_value))

        # Due date with JS bypass
        date_el = self.page.locator(f"xpath={self.DUE_DATE}")
        date_el.evaluate(
            """(el, val) => {
                el.value = val;
                el.dispatchEvent(new Event('change'));
            }""",
            str(due_date)
        )
        self.page.keyboard.press("Tab")
        self.page.wait_for_timeout(500)

        if order_no:
            self.select2(self.ORDER_NO_SELECT2, str(order_no), self.SELECT2_SEARCH)
            self.page.wait_for_timeout(3000)

    def fill_stock_item(self, product: str, design: str, sub_design: str,
                        weight_range: str = None, size: str = None,
                        pieces: str = "1", description: str = None):
        """Fill item details for Stock Order."""
        self.select2(self.PRODUCT_SELECT2, product, self.SELECT2_SEARCH)
        self.page.wait_for_timeout(500)
        self.select2(self.DESIGN_SELECT2, design, self.SELECT2_SEARCH)
        self.page.wait_for_timeout(500)
        self.select2(self.SUB_DESIGN_SELECT2, sub_design, self.SELECT2_SEARCH)
        self.page.wait_for_timeout(500)

        if weight_range:
            self.select2(self.WEIGHT_RANGE_SELECT2, weight_range, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(500)

        if size:
            self.select2(self.SIZE_SELECT2, size, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(500)

        self.fill(self.PIECES_INPUT, str(pieces))

        if description:
            self.fill(self.REMARK_INPUT, description)

        # Add item to table
        self.click(self.ADD_ITEM_BTN)
        self.page.wait_for_timeout(2000)

    def fill_multiple_items(self, items: list):
        """Add multiple items (each item is a dict with product/design/etc)."""
        for item in items:
            self.fill_stock_item(
                product=item["product"],
                design=item["design"],
                sub_design=item["sub_design"],
                weight_range=item.get("weight_range"),
                size=item.get("size"),
                pieces=item.get("pieces", "1"),
            )

    def submit(self) -> dict:
        """Click create order and capture result."""
        self.screenshot("before_po_submit")
        self.click(self.CREATE_ORDER_BTN)
        self.page.wait_for_timeout(3000)

        try:
            success = self.page.locator("div.alert-success")
            success.wait_for(state="visible", timeout=5000)
            msg = success.inner_text().strip()

            if "successfully" in msg.lower():
                # Capture PO number from first row
                po_no = ""
                try:
                    po_no = self.get_text(f"{self.ORDER_LIST}/tbody/tr[1]/td[2]")
                except Exception:
                    match = re.search(r'(\d{2}-\d{5,6}-?\d*)', msg)
                    po_no = match.group(1) if match else ""

                return {"status": "pass", "message": msg, "po_no": po_no}
            return {"status": "fail", "message": msg}
        except Exception:
            return {"status": "fail", "message": "No success message"}

    def cancel_form(self):
        try:
            self.click(self.CANCEL_BTN)
            self.page.wait_for_timeout(1000)
        except Exception:
            pass

    # ── List Page ─────────────────────────────────────────────────────────────

    def verify_in_list(self, po_no: str) -> dict:
        """Search for PO in list page."""
        search = self.page.locator(f"xpath={self.LIST_SEARCH}")
        search.clear()
        search.fill(po_no)
        self.page.wait_for_timeout(2000)

        rows = self.page.locator(f"xpath={self.ORDER_LIST}//tr[contains(., '{po_no}')]")
        if rows.count() > 0:
            return {"status": "pass", "message": f"PO {po_no} found in list"}
        return {"status": "fail", "message": f"PO {po_no} not found"}

    def cancel_po(self, po_no: str) -> dict:
        """Cancel a PO from the list page."""
        # Search
        search = self.page.locator(f"xpath={self.LIST_SEARCH}")
        search.clear()
        search.fill(po_no)
        self.page.wait_for_timeout(2000)

        # Checkbox
        checkbox = self.page.locator(
            f"xpath=//tr[contains(., '{po_no}')]//input[@name='id_customerorder[]']"
        )
        if checkbox.count() > 0 and not checkbox.first.is_checked():
            checkbox.first.click()
            self.page.wait_for_timeout(1000)

        # Cancel
        self.click(self.ORDER_CANCEL_BTN)
        self.page.wait_for_timeout(2000)

        # Handle alert
        try:
            self.page.on("dialog", lambda d: d.accept())
        except Exception:
            pass

        self.page.wait_for_timeout(3000)
        return {"status": "pass", "message": f"Cancel command sent for {po_no}"}

    # ── Visual Regression ─────────────────────────────────────────────────────

    def assert_form_layout(self):
        expect(self.page).to_have_screenshot(
            "purchase_po_form.png", full_page=True, max_diff_pixel_ratio=0.01
        )

    def assert_list_layout(self):
        expect(self.page).to_have_screenshot(
            "purchase_po_list.png", full_page=True, max_diff_pixel_ratio=0.01
        )
