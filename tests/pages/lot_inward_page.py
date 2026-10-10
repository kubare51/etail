"""
LotInwardPage — Page object for Lot Inward module.

Extracted from: Sparqla/Test_lot/Lot.py (739 lines)
Covers: Add lot (tagged/non-tagged), multi-product accumulation,
        charges section, purchase MC/Wastage/Rate, stone/other-metal sub-flows,
        save + capture lot ID from print tab, list verification.
"""
import logging
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class LotInwardPage(BasePage):
    """Page object for Lot Inward (inventory intake)."""

    # ── Navigation ────────────────────────────────────────────────────────────
    MENU_INVENTORY = "//span[contains(text(), 'Inventory')]"
    MENU_LOT_INWARD = "//span[contains(normalize-space(), 'Lot Inward')]"
    ADD_LOT_BTN = "#add_lot"

    # ── Header ────────────────────────────────────────────────────────────────
    BRANCH_SELECT2 = '//span[@id="select2-lt_rcvd_branch_sel-container"]'
    SMITH_SELECT2 = "//span[@id='select2-lt_gold_smith-container']/span"
    SEARCH_INPUT = "//input[@type='search']"

    STOCK_TYPE_TAGGED = "//form[@id='lot_form']/div/div/div[3]/div/input[1]"
    STOCK_TYPE_NONTAG = "//form[@id='lot_form']/div/div/div[3]/div/input[2]"

    CATEGORY_SELECT2 = '//span[@id="select2-category-container"]/following-sibling::span'
    PURITY_SELECT2 = '//span[@id="select2-purity-container"]/following-sibling::span'
    SECTION_SELECT2 = '//span[@id="select2-select_section-container"]/following-sibling::span'
    PRODUCT_SELECT2 = '//span[@id="select2-select_product-container"]/following-sibling::span'
    DESIGN_SELECT2 = '//span[@id="select2-select_design-container"]/following-sibling::span'
    SUB_DESIGN_SELECT2 = '//span[@id="select2-select_sub_design-container"]/following-sibling::span'
    SELECT2_DROPDOWN_SEARCH = '//span[@class="select2-search select2-search--dropdown"]/input'

    # ── Item Details ──────────────────────────────────────────────────────────
    LOT_PCS = "#lot_pcs"
    LOT_GROSS_WT = "#lot_gross_wt"
    LWT_BUTTON = '//span[@class="input-group-addon input-sm add_tag_lwt"]'
    OTHER_METAL_BUTTON = "#other_metal_amount"

    # ── Charges ───────────────────────────────────────────────────────────────
    CHARGES_TOGGLE = "//div[@id='item_details']/div[2]/div/div[6]/div/div/span"
    CHARGE_NAME_SELECT = "//table[@id='table_charges']/tbody/tr/td[2]/select"
    CHARGE_TYPE_SELECT = "//table[@id='table_charges']/tbody/tr/td[3]/select"
    CHARGE_VALUE_INPUT = "//table[@id='table_charges']/tbody/tr/td[4]/input"
    UPDATE_CHARGE_BTN = "#update_charge_details"

    # ── Purchase Details ──────────────────────────────────────────────────────
    MC_VALUE = "#mc_value"
    MC_TYPE = "#mc_type"
    LOT_WASTAGE = "#lot_wastage"
    RATE_PER_GRAM = "#rate_per_gram"
    RATE_CALC_TYPE = "#rate_calc_type"
    ADD_LOT_ITEMS_BTN = "#add_lot_items"

    # ── Save ──────────────────────────────────────────────────────────────────
    SAVE_ALL_BTN = "#save_all"

    # ── List ──────────────────────────────────────────────────────────────────
    LOT_INWARD_LIST = "#lot_inward_list"
    LIST_DT_BTN = "#ltInward-dt-btn"
    METAL_SELECT2 = "#select2-metal-container"
    LOT_SEARCH_BTN = "//button[@id='lot_inward_search']/i"
    DT_SEARCH = "//input[@type='search']"

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto(self):
        """Navigate to Lot Inward list page."""
        self.navigate_via_menu(
            "Inventory", "Lot Inward",
            fallback_path="index.php/admin_ret_lot/lot_inward/list"
        )
        return self

    def click_add_lot(self):
        """Click Add Lot button."""
        self.click(self.ADD_LOT_BTN)
        self.page.wait_for_timeout(2000)

    def fill_header(self, branch: str, smith: str, stock_type: str,
                    category: str, purity: str):
        """Fill lot header — branch, smith, stock type, category, purity."""
        self.select2(self.BRANCH_SELECT2, branch, self.SEARCH_INPUT)

        self.click(self.SMITH_SELECT2)
        search = self.page.locator(f"xpath={self.SEARCH_INPUT}")
        search.clear()
        search.fill(smith)
        search.press("Enter")

        if stock_type == "Tagged":
            self.click(self.STOCK_TYPE_TAGGED)
        else:
            self.click(self.STOCK_TYPE_NONTAG)

        self.select2(self.CATEGORY_SELECT2, category, self.SELECT2_DROPDOWN_SEARCH)
        self.select2(self.PURITY_SELECT2, purity, self.SELECT2_DROPDOWN_SEARCH)
        self.page.wait_for_timeout(1000)

    def fill_item(self, section: str = None, product: str = None,
                  design: str = None, sub_design: str = None,
                  pcs: str = None, gwt: str = None, stock_type: str = "Tagged"):
        """Fill a single lot item line."""
        if stock_type != "Tagged" and section:
            self.select2(self.SECTION_SELECT2, section, self.SELECT2_DROPDOWN_SEARCH)

        if product:
            self.select2(self.PRODUCT_SELECT2, product, self.SELECT2_DROPDOWN_SEARCH)
            self.page.wait_for_timeout(2000)

        if design:
            self.select2(self.DESIGN_SELECT2, design, self.SELECT2_DROPDOWN_SEARCH)
            self.page.wait_for_timeout(2000)

        if sub_design:
            self.select2(self.SUB_DESIGN_SELECT2, sub_design, self.SELECT2_DROPDOWN_SEARCH)
            self.page.wait_for_timeout(2000)

        if pcs:
            self.page.locator(self.LOT_PCS).click()
            self.page.locator(self.LOT_PCS).clear()
            self.page.locator(self.LOT_PCS).fill(str(pcs))

        if gwt:
            self.page.locator(self.LOT_GROSS_WT).click()
            self.page.locator(self.LOT_GROSS_WT).clear()
            self.page.locator(self.LOT_GROSS_WT).fill(str(gwt))

    def fill_charges(self, charge_name: str, charge_type: str, charge_value: str):
        """Fill the charges section."""
        self.click(self.CHARGES_TOGGLE)
        self.page.wait_for_timeout(1000)

        self.select_native(self.CHARGE_NAME_SELECT, charge_name)
        self.select_native(self.CHARGE_TYPE_SELECT, charge_type)
        self.fill(self.CHARGE_VALUE_INPUT, str(charge_value))
        self.click(self.UPDATE_CHARGE_BTN)
        self.page.wait_for_timeout(2000)

    def fill_purchase_details(self, mc: str, mc_type: str, wastage: str,
                              rate: str, rate_type: str):
        """Fill MC, wastage, rate per gram, rate calc type."""
        self.page.locator(self.MC_VALUE).click()
        self.page.locator(self.MC_VALUE).clear()
        self.page.locator(self.MC_VALUE).fill(str(mc))

        self.click("//form[@id='lot_form']/div/div[3]/div/div")
        self.select_native(self.MC_TYPE, mc_type)

        self.page.locator(self.LOT_WASTAGE).clear()
        self.page.locator(self.LOT_WASTAGE).fill(str(wastage))

        self.page.locator(self.RATE_PER_GRAM).click()
        self.page.locator(self.RATE_PER_GRAM).clear()
        self.page.locator(self.RATE_PER_GRAM).fill(str(rate))

        self.select_native(self.RATE_CALC_TYPE, rate_type)

    def add_item_to_lot(self):
        """Click Add Lot Items — adds current item to the lot."""
        self.click(self.ADD_LOT_ITEMS_BTN)
        self.page.wait_for_timeout(2000)

    def save_lot(self) -> str:
        """Click Save All and capture lot ID from new tab."""
        return self.click_and_capture_new_tab(self.SAVE_ALL_BTN)

    def verify_in_list(self, product: str, metal: str = None) -> dict:
        """Verify lot in list page."""
        if "/lot_inward/list" not in self.page.url:
            self.navigate("index.php/admin_ret_lot/lot_inward/list")

        self.page.wait_for_timeout(2000)

        # Search by product
        search = self.page.locator(f"xpath={self.DT_SEARCH}")
        search.fill(product)
        self.page.wait_for_timeout(3000)

        first_row = self.page.locator(f"{self.LOT_INWARD_LIST} tbody tr:first-child")
        row_text = first_row.inner_text()
        found = product.lower() in row_text.lower()

        if found:
            lot_id = first_row.locator("td:first-child").inner_text()
            return {"status": "pass", "lot_id": lot_id, "message": f"Lot found: {lot_id}"}
        return {"status": "fail", "message": f"Lot not found for product: {product}"}

    # ── Visual Regression ─────────────────────────────────────────────────────

    def assert_form_layout(self):
        """Visual regression: lot inward add form."""
        expect(self.page).to_have_screenshot(
            "lot_inward_form.png", full_page=True, max_diff_pixel_ratio=0.01
        )

    def assert_list_layout(self):
        """Visual regression: lot inward list page."""
        expect(self.page).to_have_screenshot(
            "lot_inward_list.png", full_page=True, max_diff_pixel_ratio=0.01
        )
