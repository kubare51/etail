"""
Lot Inward E2E Tests

Migrated from: Sparqla/Test_lot/Lot.py (739 lines)
Tests: Tagged lot, non-tagged lot, multi-product lot,
       charges, purchase details, save + list verification, visual regression.
"""
import pytest
from playwright.sync_api import expect
from pages.lot_inward_page import LotInwardPage


@pytest.mark.p0
@pytest.mark.inventory
class TestLotInward:
    """Lot Inward module tests."""

    def test_lot_form_loads(self, auth_page, base_url):
        """Verify lot inward form loads with all required fields."""
        lot = LotInwardPage(auth_page)
        lot.goto()
        lot.click_add_lot()

        expect(auth_page.locator(f"xpath={lot.BRANCH_SELECT2}")).to_be_visible()
        expect(auth_page.locator(lot.LOT_PCS)).to_be_visible()

        lot.screenshot("lot_form_loaded")

    def test_lot_form_visual(self, auth_page, base_url):
        """Visual regression: lot inward form."""
        lot = LotInwardPage(auth_page)
        lot.goto()
        lot.click_add_lot()
        lot.assert_form_layout()

    def test_tagged_lot_flow(self, auth_page, load_test_data, base_url):
        """
        Full tagged lot inward flow:
        1. Fill header (branch, smith, tagged, category, purity)
        2. Fill item (product, design, sub design, pcs, gwt)
        3. Fill purchase details (MC, wastage, rate)
        4. Add item to lot
        5. Save and capture lot ID
        6. Verify in list
        """
        test_cases = load_test_data("lot_inward")
        if not test_cases:
            pytest.skip("No lot inward test data")

        tc = next((t for t in test_cases if t.get("stock_type") == "Tagged"), None)
        if not tc:
            pytest.skip("No tagged lot test case")

        lot = LotInwardPage(auth_page)
        lot.goto()
        lot.click_add_lot()

        # Header
        lot.fill_header(
            branch=tc["branch"],
            smith=tc["smith"],
            stock_type=tc["stock_type"],
            category=tc["category"],
            purity=str(tc["purity"]),
        )

        # Item
        lot.fill_item(
            product=tc["product"],
            design=tc["design"],
            sub_design=tc["sub_design"],
            pcs=str(tc["pcs"]),
            gwt=str(tc["gwt"]),
            stock_type=tc["stock_type"],
        )

        # Purchase
        lot.fill_purchase_details(
            mc=str(tc["purchase_mc"]),
            mc_type=tc["purchase_mc_type"],
            wastage=str(tc["purchase_wastage"]),
            rate=str(tc["purchase_rate"]),
            rate_type=tc["purchase_rate_type"],
        )

        # Add and Save
        lot.add_item_to_lot()
        lot_id = lot.save_lot()
        assert lot_id, "Should capture lot ID from new tab"

        # Verify
        result = lot.verify_in_list(tc["product"])
        assert result["status"] == "pass", f"Verification failed: {result['message']}"

        lot.screenshot("tagged_lot_complete")

    def test_nontag_lot_flow(self, auth_page, load_test_data, base_url):
        """Non-tagged lot inward with section selection."""
        test_cases = load_test_data("lot_inward")
        if not test_cases:
            pytest.skip("No lot inward test data")

        tc = next((t for t in test_cases if t.get("stock_type") == "Non-Tagged"), None)
        if not tc:
            pytest.skip("No non-tagged lot test case")

        lot = LotInwardPage(auth_page)
        lot.goto()
        lot.click_add_lot()

        lot.fill_header(
            branch=tc["branch"],
            smith=tc["smith"],
            stock_type=tc["stock_type"],
            category=tc["category"],
            purity=str(tc["purity"]),
        )

        lot.fill_item(
            section=tc.get("section"),
            product=tc["product"],
            design=tc["design"],
            sub_design=tc["sub_design"],
            pcs=str(tc["pcs"]),
            gwt=str(tc["gwt"]),
            stock_type=tc["stock_type"],
        )

        lot.fill_purchase_details(
            mc=str(tc["purchase_mc"]),
            mc_type=tc["purchase_mc_type"],
            wastage=str(tc["purchase_wastage"]),
            rate=str(tc["purchase_rate"]),
            rate_type=tc["purchase_rate_type"],
        )

        lot.add_item_to_lot()
        lot_id = lot.save_lot()
        assert lot_id, "Should capture lot ID"

        lot.screenshot("nontag_lot_complete")

    def test_lot_list_visual(self, auth_page, base_url):
        """Visual regression: lot inward list page."""
        lot = LotInwardPage(auth_page)
        lot.goto()
        lot.assert_list_layout()
