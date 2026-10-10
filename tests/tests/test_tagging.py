"""
Tagging E2E Tests

Migrated from: Sparqla/Test_Tag/Tag.py (701 lines)
Tests: Tag creation from lot, design/sub-design selection,
       tag field fill (pcs, items, gwt, wastage, MC),
       calculation verification, add/add+copy, preview table, visual regression.
"""
import pytest
from playwright.sync_api import expect
from pages.tagging_page import TaggingPage


@pytest.mark.p0
@pytest.mark.inventory
class TestTagging:
    """Tagging module tests."""

    def test_tagging_form_loads(self, auth_page, base_url):
        """Verify tagging form loads."""
        tag = TaggingPage(auth_page)
        tag.goto()

        expect(auth_page.locator(tag.BRANCH_SELECT2)).to_be_visible()
        expect(auth_page.locator(tag.LOT_SELECT2)).to_be_visible()

        tag.screenshot("tagging_form_loaded")

    def test_tagging_form_visual(self, auth_page, base_url):
        """Visual regression: tagging form layout."""
        tag = TaggingPage(auth_page)
        tag.goto()
        tag.assert_form_layout()

    def test_tag_creation_flow(self, auth_page, load_test_data, base_url):
        """
        Full tagging flow:
        1. Get board rates
        2. Select branch + lot
        3. Select product/section
        4. Verify lot detail table
        5. Fill tag details
        6. Verify calculation
        7. Add tag
        """
        test_cases = load_test_data("tagging")
        if not test_cases:
            pytest.skip("No tagging test data")

        tc = test_cases[0]
        tag = TaggingPage(auth_page)
        tag.goto()

        # Rates
        rates = tag.get_board_rates()
        assert rates["gold_22kt"] > 0

        # Branch + Lot
        tag.select_branch_and_lot(tc["branch"], tc["lot_no"])

        # Product + Section
        tag.select_product_and_section(tc["product"], tc.get("section"))

        # Verify lot detail
        lot_detail = tag.get_lot_detail()
        assert lot_detail["pieces"] > 0, f"Lot should have pieces, got: {lot_detail}"

        # Fill tag
        tag.fill_tag_details(
            design=tc["design"],
            sub_design=tc["sub_design"],
            pcs=str(tc["pcs"]),
            items=str(tc["no_of_items"]),
            gwt=str(tc["gwt"]),
            wastage=str(tc.get("wastage", "")),
            mc_type=tc.get("mc_type"),
            mc=str(tc.get("mc", "")),
            size=tc.get("size"),
            calc_type=tc.get("calc_type"),
            rate_mrp=tc.get("rate_mrp"),
            huid1=tc.get("huid1"),
            huid2=tc.get("huid2"),
        )

        # Verify calculation (non-MRP types)
        calc_type = tc.get("calc_type")
        if calc_type and calc_type not in ["MRP", "Fixed Rate"]:
            metal_text = tag.get_metal_text()
            board_rate = tag.get_board_rate_for_metal(metal_text, rates)

            expected = TaggingPage.calculate_sale_value(
                calc_type=calc_type,
                board_rate=board_rate,
                gwt=float(tc["gwt"]),
                nwt=tag.get_float(tag.TAG_NWT),
                wastage_pct=float(tc.get("wastage", 0)),
                mc=float(tc.get("mc", 0)),
                mc_type_value=tc.get("mc_type_raw", "2"),
            )

            actual = tag.get_sale_value()
            assert expected == actual, (
                f"Calculation mismatch: expected={expected}, actual={actual}"
            )

        # Add tag
        if tc.get("button", "Add") == "Add":
            tag.click_add()
        else:
            tag.click_add_and_copy()

        # Verify preview
        row_count = tag.get_preview_row_count()
        assert row_count > 0, "Preview table should have at least 1 row after add"

        tag.screenshot("tagging_complete")

    def test_tag_mandatory_fields(self, auth_page, base_url):
        """Verify mandatory field behavior when adding without lot."""
        tag = TaggingPage(auth_page)
        tag.goto()

        # Try adding without selecting anything
        tag.click_add()
        tag.screenshot("tagging_mandatory_check")
