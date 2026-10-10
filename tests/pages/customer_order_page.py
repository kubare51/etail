"""
CustomerOrderPage — Page object for Customer Order module.

Extracted from: Sparqla/Test_Customer/CustomerOrder.py (480 lines)
Covers: Tag Reserve and Customized Order flows, multi-item,
        calculation verification, other charges, description modal,
        save + capture order ID from listing, billing sheet auto-populate.
"""
import math
import logging
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class CustomerOrderPage(BasePage):
    """Page object for Customer Order (Tag Reserve + Customized Order)."""

    # ── Navigation ────────────────────────────────────────────────────────────
    ADD_ORDER_BTN = '//a[@id="add_Order"]'

    # ── Header ────────────────────────────────────────────────────────────────
    CUSTOMER_AUTOCOMPLETE = "cus_name"
    BRANCH_SELECT2 = "//span[@id='select2-branch_select-container']"
    EMPLOYEE_SELECT2 = "//span[@id='select2-issue_employee-container']"
    SELECT2_SEARCH = "//span[@class='select2-search select2-search--dropdown']/input"

    # ── Type Selection ────────────────────────────────────────────────────────
    BALANCE_METAL = "//input[@id='metal_bal_type']"
    BALANCE_CASH = "//input[@id='cash_bal_type']"
    ORDER_TAG_RESERVE = "//input[@id='tag_order']"
    ORDER_CUSTOMIZED = "//input[@id='customer_order']"
    RATE_ORDER = "//input[@id='order_rate']"
    RATE_DELIVERY = "//input[@id='delivery_rate']"

    # ── Tag Reserve ───────────────────────────────────────────────────────────
    TAG_SCAN_INPUT = "//input[@id='est_tag_scan']"
    TAG_SEARCH_BTN = "//button[@id='tag_search']"

    # ── Customized Order ──────────────────────────────────────────────────────
    ADD_ITEM_BTN = "//button[@id='add_order_item']"

    # ── Other Charges ─────────────────────────────────────────────────────────
    ADD_CHARGE_BTN = "//button[@id='add_new_charge']"
    UPDATE_CHARGE_BTN = "//button[@id='update_charge_details']"

    # ── Actions ───────────────────────────────────────────────────────────────
    CREATE_ORDER_BTN = "//button[@id='create_order']"

    # ── List Page ─────────────────────────────────────────────────────────────
    ORDER_LIST = "//table[@id='order_list']"
    SEARCH_BTN = "//button[@id='search' or text()='Search']"
    DT_SEARCH = "//input[@type='search']"

    # ── Board Rate ────────────────────────────────────────────────────────────
    RATE_HEADER = "//span[@class='header_rate']/b[contains(text(),'INR')]"
    GOLD_22KT = "//li[@class='user-body rate_block_body']//tr[th[contains(text(),'Gold 22KT 1gm')]]/td"
    GOLD_18KT = "//li[@class='user-body rate_block_body']//tr[th[contains(text(),'Gold 18KT 1gm')]]/td"
    SILVER = "//li[@class='user-body rate_block_body']//tr[th[contains(text(),'Silver 1gm')]]/td"

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto(self):
        """Navigate to Customer Order page."""
        self.navigate_via_menu(
            "Customer Orders", "Create Order",
            fallback_path="index.php/admin_ret_customer_order/customer_order/add"
        )
        return self

    def get_board_rates(self) -> list:
        """Get board rates as [22kt, 18kt, silver]."""
        self.click(self.RATE_HEADER)
        self.page.wait_for_timeout(500)

        r1 = self.get_text(self.GOLD_22KT)
        r2 = self.get_text(self.GOLD_18KT)
        r3 = self.get_text(self.SILVER)

        rates = [
            int(float(r1.replace("INR", "").strip())),
            int(float(r2.replace("INR", "").strip())),
            int(float(r3.replace("INR", "").strip())),
        ]
        logger.info(f"Board rates: {rates}")
        return rates

    def click_add_order(self):
        """Click Add Order button."""
        self.click(self.ADD_ORDER_BTN)
        self.page.wait_for_timeout(2000)

    def fill_header(self, customer: str, branch: str, employee: str):
        """Fill order header."""
        if customer:
            self.autocomplete(self.CUSTOMER_AUTOCOMPLETE, str(customer))
        if branch:
            self.select2(self.BRANCH_SELECT2, branch, self.SELECT2_SEARCH)
        if employee:
            self.select2(self.EMPLOYEE_SELECT2, employee, self.SELECT2_SEARCH)

    def select_types(self, balance_type: str = "Cash Balance",
                     order_type: str = "Tag Reserve",
                     rate_type: str = "Delivery Rate(Live)"):
        """Set balance/order/rate type radio buttons."""
        if balance_type == "Metal Balance":
            self.click(self.BALANCE_METAL)
        else:
            self.click(self.BALANCE_CASH)

        if order_type == "Tag Reserve":
            self.click(self.ORDER_TAG_RESERVE)
        else:
            self.click(self.ORDER_CUSTOMIZED)

        if rate_type == "Order Rate(Fixed)":
            self.click(self.RATE_ORDER)
        else:
            self.click(self.RATE_DELIVERY)

    def fill_tag_reserve(self, tag_code: str):
        """Scan a tag for reservation."""
        self.fill(self.TAG_SCAN_INPUT, tag_code)
        self.click(self.TAG_SEARCH_BTN)
        self.page.wait_for_timeout(1000)

    def fill_customized_item(self, row_idx: int, product: str, design: str,
                              sub_design: str, purity: str = None,
                              gross_wt: str = None, pcs: str = None,
                              wastage: str = None, mc_type: str = None,
                              mc_value: str = None):
        """Fill a single customized order item row."""
        self.click(self.ADD_ITEM_BTN)
        self.page.wait_for_timeout(1000)

        # Dropdowns
        if product:
            self.select2(
                f"//span[@id='select2-prod_{row_idx}-container']",
                product, self.SELECT2_SEARCH
            )
        if design:
            self.select2(
                f"//span[@id='select2-dsgn_{row_idx}-container']",
                design, self.SELECT2_SEARCH
            )
        if sub_design:
            self.select2(
                f"//span[@id='select2-sub_design_{row_idx}-container']",
                sub_design, self.SELECT2_SEARCH
            )

        if purity:
            self.select_native(f"#purity{row_idx}", purity)

        if gross_wt:
            self.fill(f"#weight_{row_idx}", str(gross_wt))
        if pcs:
            self.fill(f"#qty_{row_idx}", str(pcs))

        if wastage:
            self.fill(f"//input[@name='o_item[{row_idx}][wast_percent]']", str(wastage))

        if mc_type:
            self.click(f"(//span[contains(@id, 'id_mc_type')])[{row_idx}]")
            self.page.wait_for_timeout(500)
            mc_search = self.page.locator("xpath=/html/body/span[2]/span/span[1]/input")
            mc_search.fill(mc_type)
            mc_search.press("Enter")

        if mc_value:
            self.fill(f"//input[@name='o_item[{row_idx}][mc]']", str(mc_value))

    def submit_order(self) -> dict:
        """Submit order and capture result."""
        self.screenshot("before_order_submit")
        self.click(self.CREATE_ORDER_BTN)
        self.page.wait_for_timeout(2000)

        # Check for success message
        try:
            msg_el = self.page.locator("div.alert-success, #toast-container")
            msg_el.wait_for(state="visible", timeout=5000)
            msg = msg_el.inner_text().replace("×", "").strip()

            if "successfully" in msg.lower():
                order_no = self._capture_order_no()
                return {"status": "pass", "message": msg, "order_no": order_no}
            return {"status": "fail", "message": msg}
        except Exception as e:
            return {"status": "fail", "message": f"Submit failed: {str(e)}"}

    def _capture_order_no(self) -> str:
        """Capture order number from list page."""
        try:
            self.page.wait_for_timeout(2000)
            order_no = self.get_text(f"{self.ORDER_LIST}//tbody/tr[1]/td[2]")
            logger.info(f"Captured Order NO: {order_no}")
            return order_no
        except Exception as e:
            logger.warning(f"Could not capture order no: {e}")
            return ""

    def verify_in_list(self, order_no: str) -> dict:
        """Verify order in listing."""
        search = self.page.locator(f"xpath={self.DT_SEARCH}")
        search.fill(str(order_no))
        self.page.wait_for_timeout(2000)

        first_row = self.page.locator(f"xpath={self.ORDER_LIST}/tbody/tr[1]")
        row_text = first_row.inner_text()
        found = str(order_no) in row_text

        if found:
            return {"status": "pass", "message": f"Order {order_no} verified"}
        return {"status": "fail", "message": f"Not found: {order_no}"}

    # ── Visual Regression ─────────────────────────────────────────────────────

    def assert_form_layout(self):
        """Visual regression: customer order form."""
        expect(self.page).to_have_screenshot(
            "customer_order_form.png", full_page=True, max_diff_pixel_ratio=0.01
        )

    def assert_list_layout(self):
        """Visual regression: customer order list."""
        expect(self.page).to_have_screenshot(
            "customer_order_list.png", full_page=True, max_diff_pixel_ratio=0.01
        )

    # ── Calculation Engine (from Sparqla CustomerOrder._calculation) ──────────

    @staticmethod
    def calculate_taxable(
        calc_type_idx: str,
        gold_rate: int,
        gwt: float,
        nwt: float,
        wastage_pct: float,
        mc_rate: float,
        mc_type: str,
        stone_amt: float = 0.0,
        other_charge: float = 0.0,
    ) -> float:
        """
        Ported from Sparqla CustomerOrder._calculation().
        calc_type_idx: "0"=Gross, "1"=Net, "2"=GrossNet, "3"=Fixed, "4"=FixedWeight
        """
        calc_map = {
            "0": "Mc & Wast On Gross",
            "1": "Mc & Wast On Net",
            "2": "Mc on Gross, Wast On Net",
            "3": "Fixed Rate",
            "4": "Fixed Rate based on Weight",
        }
        calc_type = calc_map.get(str(calc_type_idx), "Unknown")
        is_piece = mc_type.lower() == "piece"

        if calc_type == "Mc on Gross, Wast On Net":
            mc = mc_rate if is_piece else math.ceil(mc_rate * gwt)
            va = round((wastage_pct / 100) * nwt, 3)
            return math.ceil((nwt + va) * gold_rate + stone_amt + mc + other_charge)

        elif calc_type == "Mc & Wast On Net":
            mc = mc_rate if is_piece else math.ceil(mc_rate * nwt)
            va = round((wastage_pct / 100) * nwt, 3)
            return math.ceil((nwt + va) * gold_rate + mc + stone_amt + other_charge)

        elif calc_type == "Mc & Wast On Gross":
            mc = mc_rate if is_piece else math.ceil(mc_rate * gwt)
            va = round((wastage_pct / 100) * gwt, 3)
            return math.ceil((nwt + va) * gold_rate + mc + stone_amt + other_charge)

        elif calc_type == "Fixed Rate based on Weight":
            mc = mc_rate if is_piece else math.ceil(mc_rate * gwt)
            va = round((wastage_pct / 100) * gwt, 3)
            return math.ceil((nwt + va) * gold_rate + mc + stone_amt + other_charge)

        return 0.0
