"""
EstimationPage — Page object for the Estimation module.

Extracted from: Sparqla/Test_EST/EST.py, EST_Tag.py, EST_Nontag.py
Covers: Add estimation (header), tag scan, non-tag grid, old metal,
        total summary verification, print/save, new tab ID capture.
"""
import math
import logging
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class EstimationPage(BasePage):
    """Page object for add/edit estimation."""

    # ── Navigation ────────────────────────────────────────────────────────────
    MENU_ESTIMATION = "//span[contains(text(), 'Estimation')]"
    MENU_ADD = "//span[contains(text(), 'Add Estimation')]"
    ADD_ESTIMATION_BTN = '//a[@id="add_estimation"]'

    # ── Header ────────────────────────────────────────────────────────────────
    BRANCH_SELECT2 = '//span[@id="select2-branch_select-container"]'
    EMPLOYEE_SELECT2 = "//span[@id='select2-emp_select-container']"
    ESTI_FOR = {
        "Customer": '//input[@id="type1"]',
        "Branch Transfer": '//input[@id="type2"]',
        "Company": '//input[@id="type3"]',
    }
    CUSTOMER_AUTOCOMPLETE = "est_cus_name"
    CLOSE_MODAL = '(//button[@class="btn btn-close btn-warning "])[6]'

    # ── Tag Section ───────────────────────────────────────────────────────────
    TAG_CHECKBOX = '//input[@id="select_tag_details"]'
    TAG_SCAN_INPUT = '//input[@id="est_tag_scan"]'
    TAG_SEARCH_BTN = '//button[@id="tag_search"]'
    TAG_TABLE = '//table[@id="estimation_tag_details"]'

    # Tag row fields (use {row} for dynamic row index)
    TAG_CODE = '//input[@name="est_tag[tag_name][]"]'
    TAG_PRODUCT = '//div[@class="prodct_name"]'
    TAG_DESIGN = '//div[@class="design_name"]'
    TAG_SUBDESIGN = '//div[@class="sub_design_name"]'
    TAG_PIECES = '//input[@name="est_tag[piece][]"]'
    TAG_GWT = '(//input[@name="est_tag[gwt][]"])[{row}]'
    TAG_LWT = '(//input[@name="est_tag[lwt][]"])[{row}]'
    TAG_NWT = '(//input[@name="est_tag[nwt][]"])[{row}]'
    TAG_WASTAGE = '(//input[@name="est_tag[wastage][]"])[{row}]'
    TAG_WASTAGE_WT = '(//input[@name="est_tag[est_wastage_wt][]"])[{row}]'
    TAG_MC = '(//input[@name="est_tag[mc][]"])[{row}]'
    TAG_MC_TYPE = '//select[@class="form-control est_mc_type"]'
    TAG_PURITY = "//span[@class='tag_purity_name']"
    TAG_TAXABLE = '(//span[@class="cost"])[{row}]'

    # Stone details
    STONE_TABLE = '//table[@id="estimation_stone_cus_item_details"]'
    STONE_CLOSE_BTN = '(//button[@id="close_stone_details"])[2]'

    # ── Non-Tag Section ───────────────────────────────────────────────────────
    NONTAG_TAB = '//a[@id="non_tag_tab"]'
    NONTAG_ADD_BTN = '//button[@id="add_nontag_row"]'
    NONTAG_PRODUCT = '//select[@name="est_nontag[product][]"]'
    NONTAG_DESIGN = '//select[@name="est_nontag[design][]"]'
    NONTAG_SUBDESIGN = '//select[@name="est_nontag[sub_design][]"]'
    NONTAG_PCS = '//input[@name="est_nontag[piece][]"]'
    NONTAG_GWT = '//input[@name="est_nontag[gwt][]"]'
    NONTAG_NWT = '//input[@name="est_nontag[nwt][]"]'

    # ── Summary ───────────────────────────────────────────────────────────────
    SALE_AMT = "//span[@class='summary_lbl summary_sale_amt']"
    PURCHASE_AMT = "//span[@class='summary_lbl summary_pur_amt']"

    # ── Actions ───────────────────────────────────────────────────────────────
    PRINT_BTN = '//button[@id="est_print"]'
    CANCEL_BTN = '//button[@class="btn btn-default btn-cancel"]'

    # ── Board Rate ────────────────────────────────────────────────────────────
    RATE_HEADER = "//span[@class='header_rate']/b[contains(text(),'INR')]"
    GOLD_22KT = "//li[@class='user-body rate_block_body']//tr[th[contains(text(),'Gold 22KT 1gm')]]/td"
    GOLD_18KT = "//li[@class='user-body rate_block_body']//tr[th[contains(text(),'Gold 18KT 1gm')]]/td"
    SILVER = "//li[@class='user-body rate_block_body']//tr[th[contains(text(),'Silver 1gm')]]/td"

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto_add_estimation(self):
        """Navigate to Add Estimation page."""
        self.navigate_via_menu(
            "Estimation", "Add Estimation",
            fallback_path="index.php/admin_ret_estimation/estimation/add"
        )
        return self

    def click_add_new(self):
        """Click Add Estimation button (for subsequent estimations)."""
        self.click(self.ADD_ESTIMATION_BTN)

    def get_board_rates(self) -> dict:
        """Fetch live gold/silver rates from header widget."""
        self.click(self.RATE_HEADER)
        self.page.wait_for_timeout(1000)

        rates = {}
        for key, xpath in [("gold_22kt", self.GOLD_22KT), ("gold_18kt", self.GOLD_18KT), ("silver", self.SILVER)]:
            text = self.get_text(xpath)
            rates[key] = int(float(text.replace("INR", "").strip()))
        logger.info(f"Board rates: {rates}")
        return rates

    def fill_header(self, branch: str, employee: str, esti_for: str, customer: str):
        """Fill the estimation header section."""
        if branch:
            self.select2(self.BRANCH_SELECT2, branch)

        if employee:
            self.select2(self.EMPLOYEE_SELECT2, employee)

        if esti_for and esti_for in self.ESTI_FOR:
            self.click(self.ESTI_FOR[esti_for])

        if customer:
            self.autocomplete(self.CUSTOMER_AUTOCOMPLETE, str(customer))

        self.page.wait_for_timeout(1000)
        try:
            self.click(self.CLOSE_MODAL)
        except Exception:
            pass

    def scan_tag(self, tag_no: str):
        """Scan a tag barcode and verify it loaded."""
        self.click(self.TAG_CHECKBOX)
        self.fill(self.TAG_SCAN_INPUT, tag_no)
        self.click(self.TAG_SEARCH_BTN)
        self.page.wait_for_timeout(3000)

    def get_tag_web_data(self) -> dict:
        """Read all tag item values from the form after scan."""
        return {
            "tag_code": self.get_value(self.TAG_CODE),
            "product": self.get_text(self.TAG_PRODUCT),
            "design": self.get_text(self.TAG_DESIGN),
            "sub_design": self.get_text(self.TAG_SUBDESIGN),
            "pieces": self.get_value(self.TAG_PIECES),
        }

    def get_tag_calculation_data(self, row: int = 1) -> dict:
        """Read numeric fields for calculation verification."""
        return {
            "gwt": self.get_float(self.TAG_GWT.format(row=row)),
            "lwt": self.get_float(self.TAG_LWT.format(row=row)),
            "nwt": self.get_float(self.TAG_NWT.format(row=row)),
            "wastage_pct": self.get_float(self.TAG_WASTAGE.format(row=row)),
            "wastage_wt": self.get_float(self.TAG_WASTAGE_WT.format(row=row)),
            "mc": self.get_float(self.TAG_MC.format(row=row)),
            "taxable_amt": self.get_text(self.TAG_TAXABLE.format(row=row)),
        }

    def get_mc_type(self) -> str:
        """Get selected MC type (Gram/Piece)."""
        locator = self.page.locator(f"xpath={self.TAG_MC_TYPE}")
        return locator.evaluate("el => el.options[el.selectedIndex].text").strip()

    def get_purity(self) -> float:
        """Get purity value from tag display."""
        text = self.get_text(self.TAG_PURITY)
        return float(text)

    def get_stone_amount(self, row: int = 1) -> float:
        """Open stone details popup and sum stone amounts."""
        self.click(self.TAG_LWT.format(row=row))
        self.page.wait_for_timeout(1000)

        stone_table = self.page.locator(f"xpath={self.STONE_TABLE}")
        stone_inputs = stone_table.locator("tr td:nth-child(8) input")
        total = 0.0
        for i in range(stone_inputs.count()):
            val = stone_inputs.nth(i).input_value()
            try:
                total += float(val)
            except (ValueError, TypeError):
                pass

        self.click(self.STONE_CLOSE_BTN)
        logger.info(f"Stone total: {total}")
        return total

    def get_gold_rate_for_purity(self, purity: float, board_rates: dict) -> int:
        """Map purity to the correct board rate."""
        purity_map = {
            91.6: board_rates.get("gold_22kt", 0),
            75.0: board_rates.get("gold_18kt", 0),
            92.5: board_rates.get("silver", 0),
        }
        return purity_map.get(purity, 0)

    def get_summary_values(self) -> dict:
        """Read sale and purchase amounts from summary."""
        return {
            "sale_amount": self.get_float(self.SALE_AMT),
            "purchase_amount": self.get_float(self.PURCHASE_AMT),
        }

    def save_and_capture_id(self) -> str:
        """Click print/save and capture estimation number from new tab PDF."""
        return self.click_and_capture_new_tab(self.PRINT_BTN)

    def cancel(self):
        """Cancel estimation."""
        self.click(self.CANCEL_BTN)

    # ── Visual Regression ─────────────────────────────────────────────────────

    def assert_form_layout(self):
        """Visual regression: verify estimation form layout."""
        expect(self.page).to_have_screenshot(
            "estimation_form.png",
            full_page=True,
            max_diff_pixel_ratio=0.01,
        )

    def assert_tag_table_layout(self):
        """Visual regression: verify tag items table layout."""
        tag_table = self.page.locator(f"xpath={self.TAG_TABLE}")
        expect(tag_table).to_have_screenshot("estimation_tag_table.png")

    # ── Calculation Engine (from Sparqla EST_Tag.calculation) ──────────────────

    @staticmethod
    def calculate_taxable(
        calc_type: str,
        gold_rate: int,
        gwt: float,
        nwt: float,
        wastage_pct: float,
        mc: float,
        stone: float,
        other_amt: float,
        mc_type: str,
        tax_pct: float = 3.0,
    ) -> dict:
        """
        Business logic: calculate expected taxable amount.
        Migrated from Sparqla's ESTIMATION_TAG.calculation().

        Returns dict with: taxable, igst, sgst, total
        """
        ceil_value = 0.0

        if calc_type == "Mc on Gross,Wast On Net":
            mc_amt = mc if mc_type == "Piece" else float(f"{math.ceil(mc * gwt):.2f}")
            va = round((wastage_pct / 100) * nwt, 3)
            total_wt = round(nwt + va, 3)
            ceil_value = float(f"{math.ceil(total_wt * gold_rate + stone + mc_amt + other_amt):.2f}")

        elif calc_type == "Mc & Wast On Net":
            mc_amt = mc if mc_type == "Piece" else float(f"{math.ceil(mc * nwt):.2f}")
            va = round((wastage_pct / 100) * nwt, 3)
            total_wt = round(nwt + va, 3)
            ceil_value = float(f"{math.ceil(total_wt * gold_rate + mc_amt + stone + other_amt):.2f}")

        elif calc_type == "Mc & Wast On Gross":
            mc_amt = mc if mc_type == "Piece" else float(f"{math.ceil(mc * gwt):.2f}")
            va = round((wastage_pct / 100) * gwt, 3)
            total_wt = round(nwt + va, 3)
            ceil_value = float(f"{math.ceil(total_wt * gold_rate + mc_amt + stone + other_amt):.2f}")

        elif calc_type == "Fixed Rate based on Weight":
            mc_amt = mc if mc_type == "Piece" else float(f"{math.ceil(mc * gwt):.2f}")
            va = round((wastage_pct / 100) * gwt, 3)
            total_wt = round(nwt + va, 3)
            ceil_value = float(f"{math.ceil(total_wt * gold_rate + mc_amt + stone + other_amt):.2f}")

        elif calc_type == "Fixed Rate":
            ceil_value = mc  # Fixed rate uses MC as the total

        # Apply GST
        tax_amount = (ceil_value * tax_pct) / 100
        total_with_tax = ceil_value + tax_amount
        gst_half = float(f"{math.ceil(tax_amount / 2):.2f}")

        return {
            "taxable": f"{ceil_value:.2f}",
            "igst": f"{gst_half:.2f}",
            "sgst": f"{gst_half:.2f}",
            "total": f"{total_with_tax:.2f}",
        }
