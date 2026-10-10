"""
RepairOrderPage — Page object for Repair Order module.

Extracted from: Sparqla/Test_RepairOrder/RepairOrder.py (391 lines)
Covers: Customer & Stock repair, multi-item entry, stone modal,
        save + capture ID from print tab, list verification.
"""
import logging
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class RepairOrderPage(BasePage):
    """Page object for Repair Order (Customer + Stock)."""

    # ── Navigation ────────────────────────────────────────────────────────────
    ADD_ORDER_BTN = '//a[@id="add_Order"]'

    # ── Order Type ────────────────────────────────────────────────────────────
    TYPE_CUSTOMER = "#cus_repair_order"
    TYPE_STOCK = "#stock_repair_order"

    # ── Work At ───────────────────────────────────────────────────────────────
    WORK_INHOUSE = "#cus_repair_at_inhouse"
    WORK_OUTSOURCE = "#cus_repair_at_outsource"

    # ── Header ────────────────────────────────────────────────────────────────
    BRANCH_SELECT2 = '//select[@id="branch_select"]/following-sibling::span'
    EMPLOYEE_SELECT2 = '//select[@id="employee_sel"]/following-sibling::span'
    CUSTOMER_AUTOCOMPLETE = "cus_name"
    SELECT2_SEARCH = '//span[@class="select2-search select2-search--dropdown"]/input'

    # ── Stock Repair ──────────────────────────────────────────────────────────
    TAG_CODE_INPUT = "#issue_tag_code"
    TAG_SEARCH_BTN = '//button[@id="issue_tag_search"]'

    # ── Customer Item Table ───────────────────────────────────────────────────
    ITEM_SECTION_BTN = "#repaid_order_items"
    ITEM_TABLE = '//table[@id="custrepair_item_detail"]'
    ADD_ITEM_ROW = "//table[@id='custrepair_item_detail']/tbody/tr[last()]//a[@id='create_order_item']"

    # ── Stone Modal ───────────────────────────────────────────────────────────
    STONE_TABLE = '//table[@id="estimation_stone_cus_item_details"]'
    ADD_STONE_BTN = '//button[@id="create_stone_item_details"]'
    STONE_SAVE_BTN = '//button[@id="update_stone_details"]'

    # ── Save ──────────────────────────────────────────────────────────────────
    SAVE_BTN = '//button[@id="create_repair_order"]'

    # ── List ──────────────────────────────────────────────────────────────────
    REPAIR_ORDER_LIST = '//table[@id="repair_order_list"]'
    LIST_SEARCH_BTN = '//button[@id="reorder_search"]'
    DT_SEARCH = "//input[@type='search']"

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto(self):
        """Navigate to Repair Order list page."""
        self.navigate_via_menu(
            "Repair Orders", "Create Order",
            fallback_path="index.php/admin_ret_order/repair_order/list"
        )
        return self

    def start_order(self, order_type: str = "Customer", work_at: str = "In House"):
        """Click Add and set order type + work at."""
        self.click(self.ADD_ORDER_BTN)
        self.page.wait_for_timeout(2000)

        if order_type == "Customer":
            self.click(self.TYPE_CUSTOMER)
        else:
            self.click(self.TYPE_STOCK)

        self.page.wait_for_timeout(1000)

        if work_at == "In House":
            self.click(self.WORK_INHOUSE)
        else:
            self.click(self.WORK_OUTSOURCE)

        self.page.wait_for_timeout(1000)

    def fill_header(self, branch: str = None, employee: str = None,
                    customer: str = None):
        """Fill order header."""
        if branch:
            branch_el = self.page.locator("#branch_select")
            if branch_el.is_enabled():
                self.select2(self.BRANCH_SELECT2, branch, self.SELECT2_SEARCH)
                self.page.wait_for_timeout(1000)

        if employee:
            self.select2(self.EMPLOYEE_SELECT2, employee, self.SELECT2_SEARCH)
            self.page.wait_for_timeout(1000)

        if customer:
            self.autocomplete(self.CUSTOMER_AUTOCOMPLETE, str(customer))
            self.page.wait_for_timeout(1000)

    def fill_stock_tag(self, tag_code: str):
        """Fill tag code for stock repair."""
        self.fill(self.TAG_CODE_INPUT, tag_code)
        self.click(self.TAG_SEARCH_BTN)
        self.page.wait_for_timeout(2000)

    def fill_customer_items(self, items: list):
        """
        Fill customer repair items.

        items: list of dicts with keys:
            metal, product, design, sub_design, gross_wt, pcs,
            due_date, repair_type, stones (optional), description (optional)
        """
        self.click(self.ITEM_SECTION_BTN)
        self.page.wait_for_timeout(1000)

        for i, item in enumerate(items):
            row_idx = i + 1
            prefix = f"(//table[@id='custrepair_item_detail']/tbody/tr)[{row_idx}]"

            if i > 0:
                self.click(self.ADD_ITEM_ROW)
                self.page.wait_for_timeout(1000)

            # Dropdowns
            if item.get("metal"):
                self.select2(
                    f"{prefix}//span[contains(@id, 'select2-metal-container')]/following-sibling::span",
                    item["metal"], self.SELECT2_SEARCH
                )

            if item.get("product"):
                self.select2(
                    f"{prefix}//span[@id='select2-product-container']",
                    item["product"], self.SELECT2_SEARCH
                )

            if item.get("design"):
                self.select2(
                    f"{prefix}//span[contains(@id,'order_item[id_design]')]",
                    item["design"], self.SELECT2_SEARCH
                )

            if item.get("sub_design"):
                self.select2(
                    f"{prefix}//span[contains(@id,'order_item[id_sub_design]')]",
                    item["sub_design"], self.SELECT2_SEARCH
                )

            # Weight and Pcs
            if item.get("gross_wt"):
                self.fill(f"{prefix}//input[@name='order_item[weight][]']", str(item["gross_wt"]))

            if item.get("pcs"):
                self.fill(f"{prefix}//input[@name='order_item[piece][]']", str(item["pcs"]))

            # Due Date
            if item.get("due_date"):
                due_el = self.page.locator(f"xpath={prefix}//input[@name='order_item[cus_due_days][]']")
                due_el.evaluate(
                    """(el, val) => {
                        el.value = val;
                        el.dispatchEvent(new Event('change', {bubbles: true}));
                        el.dispatchEvent(new Event('blur', {bubbles: true}));
                    }""",
                    item["due_date"]
                )
                self.page.wait_for_timeout(1000)

            # Repair Type
            if item.get("repair_type"):
                self.select2(
                    f"{prefix}//span[@id='select2-repair-container']/following-sibling::span",
                    item["repair_type"], self.SELECT2_SEARCH
                )

            # Description
            if item.get("description"):
                self.click(f"{prefix}//a[contains(@onclick,'update_order_description')]")
                self.page.wait_for_timeout(1000)
                self.fill("#description", item["description"])
                self.click("//a[@class='btn btn-success add_order_desc']")
                self.page.wait_for_timeout(1000)

    def save_order(self) -> str:
        """Save repair order and capture ID from new tab."""
        self.screenshot("before_repair_save")
        return self.click_and_capture_new_tab(self.SAVE_BTN)

    def verify_in_list(self, captured_id: str, branch: str = None) -> dict:
        """Verify repair order in listing."""
        if "/repair_order/list" not in self.page.url:
            self.navigate("index.php/admin_ret_order/repair_order/list")

        self.page.wait_for_timeout(2000)

        # Search
        search = self.page.locator(f"xpath={self.DT_SEARCH}")
        search.fill(str(captured_id))
        self.page.wait_for_timeout(2000)

        first_row = self.page.locator(f"xpath={self.REPAIR_ORDER_LIST}/tbody/tr[1]")
        row_text = first_row.inner_text()
        found = str(captured_id) in row_text

        if found:
            order_no = first_row.locator("td:nth-child(2)").inner_text()
            return {"status": "pass", "order_no": order_no,
                    "message": f"Order {captured_id} verified ({order_no})"}
        return {"status": "fail", "message": f"Not found: {captured_id}"}

    # ── Visual Regression ─────────────────────────────────────────────────────

    def assert_form_layout(self):
        """Visual regression: repair order form."""
        expect(self.page).to_have_screenshot(
            "repair_order_form.png", full_page=True, max_diff_pixel_ratio=0.01
        )

    def assert_list_layout(self):
        """Visual regression: repair order list page."""
        expect(self.page).to_have_screenshot(
            "repair_order_list.png", full_page=True, max_diff_pixel_ratio=0.01
        )
