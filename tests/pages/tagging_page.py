"""
TaggingPage — Page object for Tagging module.

Extracted from: Sparqla/Test_Tag/Tag.py (701 lines)
Covers: Select branch + lot, product/section/design,
        fill tag details (pcs, items, gwt, wastage, MC, size, calc type, HUID),
        less weight / other metal sub-flows, attribute/certification,
        add/add+copy, calculation verification, visual regression.
"""
import math
import logging
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class TaggingPage(BasePage):
    """Page object for Tag creation (from lots)."""

    # ── Navigation ────────────────────────────────────────────────────────────
    ADD_TAGGING_BTN = "#add_tagging"

    # ── Header ────────────────────────────────────────────────────────────────
    BRANCH_SELECT2 = "#select2-branch_select-container"
    LOT_SELECT2 = "#select2-tag_lot_received_id-container"
    PRODUCT_SELECT2 = "#select2-tag_lt_prod-container"
    SECTION_SELECT2 = "#select2-section_select-container"
    DESIGN_SELECT2 = "#select2-des_select-container"
    SUB_DESIGN_SELECT2 = "#select2-sub_des_select-container"
    SEARCH_INPUT = "//input[@type='search']"

    # ── Lot Detail Table ──────────────────────────────────────────────────────
    LOT_DETAIL_PIECES = "//table[@id='lt-det']/tbody/tr/td[2]"
    LOT_DETAIL_GWT = "//table[@id='lt-det']/tbody/tr/td[3]"
    LOT_DETAIL_NWT = "//table[@id='lt-det']/tbody/tr/td[4]"
    LOT_DETAIL_DWT = "//table[@id='lt-det']/tbody/tr/td[5]"
    METAL_TEXT = "//*[@id='lt_metal']"

    # ── Tag Fields ────────────────────────────────────────────────────────────
    TAG_PCS = "#tag_pcs"
    BULK_TAG = "#bulk_tag"
    TAG_GWT = "#tag_gwt"
    TAG_NWT = "#tag_nwt"
    TAG_WASTAGE = "#tag_wast_perc"
    TAG_MC_TYPE = '#tag_id_mc_type'
    TAG_MC_VALUE = "#tag_mc_value"
    TAG_SIZE_SELECT2 = "#select2-tag_size-container"
    TAG_CALC_TYPE = "#tag_calculation_based_on"
    TAG_SELL_RATE = "#tag_sell_rate"
    TAG_SALE_VALUE = "#tag_sale_value"
    TAG_HUID1 = "#tag_huid"
    TAG_HUID2 = "#tag_huid2"

    # ── Less Weight ───────────────────────────────────────────────────────────
    LWT_TOGGLE = '//form[@id="tag_form"]/div/div/div[2]/div/div/div[8]/div[2]/div/div/span'
    CLOSE_STONE_BTN = '(//button[@id="close_stone_details"])[1]'

    # ── Other Metal ───────────────────────────────────────────────────────────
    OTHER_METAL_TOGGLE = "//form[@id='tag_form']/div/div/div[2]/div/div/div[9]/div[2]/div/div/span"

    # ── Attribute ─────────────────────────────────────────────────────────────
    ATTRIBUTE_TOGGLE = "#tag_attribute"
    ATTR_NAME_SELECT2 = "//span[text()='Select Attribute']"
    ATTR_VALUE_SELECT2 = "//span[text()='Select Attribute Value']"
    ATTR_UPDATE_BTN = "#update_attribute_details"

    # ── Certification ─────────────────────────────────────────────────────────
    CERT_NO = "#cert_no"
    CERT_IMG = "#cert_img"

    # ── Actions ───────────────────────────────────────────────────────────────
    ADD_TAG_BTN = "#addTagToPreview"
    ADD_COPY_BTN = "#addTagToPreviewAndCopy"

    # ── Preview Table ─────────────────────────────────────────────────────────
    TAG_PREVIEW_TABLE = '//table[@id="lt_item_tag_preview"]'

    # ── Board Rate (same as estimation) ───────────────────────────────────────
    RATE_HEADER = "//span[@class='header_rate']/b[contains(text(),'INR')]"
    GOLD_22KT = "//li[@class='user-body rate_block_body']//tr[th[contains(text(),'Gold 22KT 1gm')]]/td"
    GOLD_18KT = "//li[@class='user-body rate_block_body']//tr[th[contains(text(),'Gold 18KT 1gm')]]/td"
    GOLD_24KT = "//li[@class='user-body rate_block_body']//tr[th[contains(text(),'Gold 24KT 1gm')]]/td"
    SILVER = "//li[@class='user-body rate_block_body']//tr[th[contains(text(),'Silver 1gm')]]/td"

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto(self):
        """Navigate to Tagging page."""
        self.navigate_via_menu(
            "Inventory", "Tagging",
            fallback_path="index.php/admin_ret_tagging/tagging/add"
        )
        self.click(self.ADD_TAGGING_BTN)
        return self

    def get_board_rates(self) -> dict:
        """Fetch live board rates."""
        self.click(self.RATE_HEADER)
        self.page.wait_for_timeout(1000)

        rates = {}
        for key, xpath in [
            ("gold_22kt", self.GOLD_22KT),
            ("gold_18kt", self.GOLD_18KT),
            ("gold_24kt", self.GOLD_24KT),
            ("silver", self.SILVER),
        ]:
            text = self.get_text(xpath)
            rates[key] = int(float(text.replace("INR", "").strip()))
        logger.info(f"Board rates: {rates}")
        return rates

    def select_branch_and_lot(self, branch: str, lot_no: str):
        """Select branch and lot number."""
        if branch:
            self.click(self.BRANCH_SELECT2)
            search = self.page.locator(f"xpath={self.SEARCH_INPUT}")
            search.clear()
            search.fill(branch)
            search.press("Enter")
            self.page.wait_for_timeout(5000)

        self.click(self.LOT_SELECT2)
        lot_search = self.page.locator(f"xpath={self.SEARCH_INPUT}")
        lot_search.clear()
        lot_search.fill(str(lot_no))
        self.page.locator(f"//li[normalize-space()='{lot_no}']").click()
        self.page.wait_for_timeout(2000)

    def select_product_and_section(self, product: str, section: str = None):
        """Select product and optional section."""
        self.click(self.PRODUCT_SELECT2)
        search = self.page.locator(f"xpath={self.SEARCH_INPUT}")
        search.fill(product)
        search.press("Enter")

        # Handle possible alert
        try:
            self.page.on("dialog", lambda d: d.accept())
        except Exception:
            pass

        if section:
            self.click(self.SECTION_SELECT2)
            sec_search = self.page.locator(f"xpath={self.SEARCH_INPUT}")
            sec_search.clear()
            sec_search.fill(section)
            sec_search.press("Enter")

    def get_lot_detail(self) -> dict:
        """Read lot detail table values (pcs, gwt, nwt, dwt)."""
        return {
            "pieces": int(float(self.get_text(self.LOT_DETAIL_PIECES))),
            "gwt": float(self.get_text(self.LOT_DETAIL_GWT)),
            "nwt": float(self.get_text(self.LOT_DETAIL_NWT)),
            "dwt": float(self.get_text(self.LOT_DETAIL_DWT)),
        }

    def fill_tag_details(self, design: str, sub_design: str, pcs: str,
                         items: str, gwt: str, wastage: str = None,
                         mc_type: str = None, mc: str = None,
                         size: str = None, calc_type: str = None,
                         rate_mrp: str = None, huid1: str = None,
                         huid2: str = None):
        """Fill all tag detail fields."""
        # Design / Sub Design
        design_el = self.page.locator(self.DESIGN_SELECT2)
        try:
            design_el.click()
        except Exception:
            design_el.evaluate("el => el.click()")

        search = self.page.locator(f"xpath={self.SEARCH_INPUT}")
        search.clear()
        search.fill(str(design or ""))
        search.press("Enter")

        self.click(self.SUB_DESIGN_SELECT2)
        sub_search = self.page.locator(f"xpath={self.SEARCH_INPUT}")
        sub_search.clear()
        sub_search.fill(str(sub_design or ""))
        sub_search.press("Enter")

        # Numeric fields
        self.page.locator(self.TAG_PCS).clear()
        self.page.locator(self.TAG_PCS).fill(str(pcs))

        self.page.locator(self.BULK_TAG).clear()
        self.page.locator(self.BULK_TAG).fill(str(items))

        gwt_el = self.page.locator(self.TAG_GWT)
        gwt_el.clear()
        gwt_el.fill(str(gwt))
        gwt_el.press("Tab")

        # Close auto-opened stone modal
        try:
            self.click(self.CLOSE_STONE_BTN)
        except Exception:
            pass
        self.page.wait_for_timeout(2000)

        # Wastage
        if wastage is not None:
            self.page.locator(self.TAG_WASTAGE).clear()
            self.page.locator(self.TAG_WASTAGE).fill(str(wastage))

        # MC
        if mc_type:
            self.select_native(self.TAG_MC_TYPE, mc_type)
        if mc is not None:
            self.page.locator(self.TAG_MC_VALUE).clear()
            self.page.locator(self.TAG_MC_VALUE).fill(str(mc))

        # Size
        if size:
            self.click(self.TAG_SIZE_SELECT2)
            s = self.page.locator(f"xpath={self.SEARCH_INPUT}")
            s.clear()
            s.fill(size)
            s.press("Enter")

        # Calc Type
        if calc_type:
            self.select_native(self.TAG_CALC_TYPE, calc_type)

        # Rate / MRP (for Fixed Rate / Fixed Rate based on Weight)
        if rate_mrp:
            self.page.locator(self.TAG_SELL_RATE).click()
            self.page.locator(self.TAG_SELL_RATE).fill(str(rate_mrp))

        # HUID
        if huid1:
            self.page.locator(self.TAG_HUID1).click()
            self.page.locator(self.TAG_HUID1).fill(str(huid1))
        if huid2:
            self.page.locator(self.TAG_HUID2).click()
            self.page.locator(self.TAG_HUID2).fill(str(huid2))

    def click_add(self):
        """Click Add Tag to Preview."""
        self.click(self.ADD_TAG_BTN)
        self.page.wait_for_timeout(2000)
        self._dismiss_alert()

    def click_add_and_copy(self):
        """Click Add & Copy Tag."""
        self.click(self.ADD_COPY_BTN)
        self.page.wait_for_timeout(2000)
        self._dismiss_alert()

    def get_sale_value(self) -> str:
        """Get calculated sale value from UI."""
        return self.get_value(self.TAG_SALE_VALUE)

    def get_preview_row_count(self) -> int:
        """Count rows in the preview table."""
        table = self.page.locator(f"xpath={self.TAG_PREVIEW_TABLE}")
        return table.locator("tbody tr").count()

    def get_metal_text(self) -> str:
        """Get metal text for rate mapping."""
        return self.get_text(self.METAL_TEXT)

    def get_board_rate_for_metal(self, metal_text: str, rates: dict) -> int:
        """Map metal text to correct rate."""
        if "GOLD" in metal_text:
            if "75.0000" in metal_text:
                return rates.get("gold_18kt", 0)
            elif "100.0000" in metal_text or "999.0000" in metal_text:
                return rates.get("gold_24kt", 0)
            else:
                return rates.get("gold_22kt", 0)
        elif "SILVER" in metal_text:
            return rates.get("silver", 0)
        return rates.get("gold_22kt", 0)

    def _dismiss_alert(self):
        """Handle alert after add."""
        try:
            self.page.on("dialog", lambda d: d.accept())
            self.page.wait_for_timeout(1000)
        except Exception:
            pass

    # ── Visual Regression ─────────────────────────────────────────────────────

    def assert_form_layout(self):
        """Visual regression: tagging form."""
        expect(self.page).to_have_screenshot(
            "tagging_form.png", full_page=True, max_diff_pixel_ratio=0.01
        )

    # ── Calculation Engine (from Sparqla Tag.calculation) ─────────────────────

    @staticmethod
    def calculate_sale_value(
        calc_type: str,
        board_rate: int,
        gwt: float,
        nwt: float,
        wastage_pct: float,
        mc: float,
        mc_type_value: str,
        stone_amt: float = 0.0,
        other_metal_amt: float = 0.0,
    ) -> str:
        """
        Business logic: calculate expected tag sale value.
        Migrated from Sparqla's Tag.calculation().

        mc_type_value: '1' = Piece, '2' or other = Gram
        """
        ceil_value = 0.0

        is_piece = mc_type_value == "1" or mc_type_value.lower() == "piece"

        if calc_type == "Mc on Gross,Wast On Net":
            mc_amt = mc if is_piece else float(f"{mc * gwt:.2f}")
            va = round((wastage_pct / 100) * nwt, 3)
            total = round(nwt + va, 3)
            ceil_value = math.ceil(total * board_rate + stone_amt + mc_amt)

        elif calc_type == "Mc & Wast On Net":
            mc_amt = mc if is_piece else float(f"{math.ceil(mc * nwt):.2f}")
            va = round((wastage_pct / 100) * nwt, 3)
            total = round(nwt + va, 3)
            ceil_value = math.ceil(total * board_rate + mc_amt + stone_amt)

        elif calc_type == "Mc & Wast On Gross":
            mc_amt = mc if is_piece else float(f"{math.ceil(mc * gwt):.2f}")
            va = round((wastage_pct / 100) * gwt, 3)
            total = round(nwt + va, 3)
            ceil_value = math.ceil(total * board_rate + mc_amt + stone_amt)

        elif calc_type == "Fixed Rate based on Weight":
            mc_amt = mc if is_piece else float(f"{math.ceil(mc * gwt):.2f}")
            va = round((wastage_pct / 100) * gwt, 3)
            total = round(nwt + va, 3)
            ceil_value = math.ceil(total * board_rate + mc_amt + stone_amt)

        elif calc_type == "Fixed Rate":
            pass  # Fixed Rate: no calculation, use MRP directly

        if other_metal_amt > 0:
            ceil_value = float(ceil_value) + float(other_metal_amt)

        return f"{ceil_value:.2f}"
