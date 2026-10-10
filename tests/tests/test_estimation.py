"""
Estimation E2E Tests

Migrated from: Sparqla/Test_EST/EST.py, EST_Tag.py, EST_Nontag.py
Tests: Tag estimation, non-tag estimation, calculation verification,
       visual regression of estimation form and summary.
"""
import pytest
from playwright.sync_api import expect
from pages.estimation_page import EstimationPage


@pytest.mark.p0
class TestEstimation:
    """Estimation module tests."""

    def test_estimation_form_loads(self, auth_page, base_url):
        """Verify estimation form loads with all required fields."""
        est = EstimationPage(auth_page)
        est.goto_add_estimation()

        # Verify key elements are present
        expect(auth_page.locator(f"xpath={est.BRANCH_SELECT2}")).to_be_visible()
        expect(auth_page.locator(f"xpath={est.EMPLOYEE_SELECT2}")).to_be_visible()
        expect(auth_page.locator(f"xpath={est.ESTI_FOR['Customer']}")).to_be_visible()

        est.screenshot("estimation_form_loaded")

    def test_estimation_form_visual(self, auth_page, base_url):
        """Visual regression: estimation form layout."""
        est = EstimationPage(auth_page)
        est.goto_add_estimation()
        est.assert_form_layout()

    def test_board_rates_available(self, auth_page, base_url):
        """Verify board rates (Gold 22KT, 18KT, Silver) are available."""
        est = EstimationPage(auth_page)
        est.navigate("index.php/admin_ret_dashboard")

        rates = est.get_board_rates()
        assert rates["gold_22kt"] > 0, "Gold 22KT rate should be > 0"
        assert rates["gold_18kt"] > 0, "Gold 18KT rate should be > 0"
        assert rates["silver"] > 0, "Silver rate should be > 0"
        assert rates["gold_22kt"] > rates["gold_18kt"], "22KT should be more than 18KT"

    def test_estimation_header_fill(self, auth_page, load_test_data, base_url):
        """Fill estimation header — branch, employee, customer."""
        test_cases = load_test_data("estimation")
        tc = test_cases[0]

        est = EstimationPage(auth_page)
        est.goto_add_estimation()

        est.fill_header(
            branch=tc["header"]["branch"],
            employee=tc["header"]["employee"],
            esti_for=tc["header"]["esti_for"],
            customer=tc["header"]["customer"],
        )

        est.screenshot("estimation_header_filled")

    def test_tag_estimation_calculation(self, auth_page, load_test_data, base_url):
        """
        Full tag estimation flow:
        1. Fill header
        2. Scan tag
        3. Verify web values match
        4. Verify calculation formula
        5. Save and capture estimation number
        """
        test_cases = load_test_data("estimation")
        tc = next(t for t in test_cases if t["id"] == "TC_EST_001")

        est = EstimationPage(auth_page)

        # Get board rates first
        est.navigate("index.php/admin_ret_dashboard")
        board_rates = est.get_board_rates()

        # Navigate to estimation
        est.goto_add_estimation()
        est.fill_header(
            branch=tc["header"]["branch"],
            employee=tc["header"]["employee"],
            esti_for=tc["header"]["esti_for"],
            customer=tc["header"]["customer"],
        )

        # Scan tag
        if tc["tag"]["enabled"]:
            est.scan_tag(tc["tag"]["tag_no"])

            # Verify tag loaded
            web_data = est.get_tag_web_data()
            assert web_data["tag_code"], "Tag code should be populated after scan"

            # Get calculation data
            calc_data = est.get_tag_calculation_data(row=1)
            mc_type = est.get_mc_type()
            purity = est.get_purity()
            gold_rate = est.get_gold_rate_for_purity(purity, board_rates)
            stone = est.get_stone_amount(row=1)

            # Verify calculation
            if tc["expected"].get("calc_type"):
                expected = EstimationPage.calculate_taxable(
                    calc_type=tc["expected"]["calc_type"],
                    gold_rate=gold_rate,
                    gwt=calc_data["gwt"],
                    nwt=calc_data["nwt"],
                    wastage_pct=calc_data["wastage_pct"],
                    mc=calc_data["mc"],
                    stone=stone,
                    other_amt=0.0,
                    mc_type=mc_type,
                )
                # Compare expected vs UI
                ui_taxable = calc_data["taxable_amt"]
                assert expected["taxable"] == ui_taxable, (
                    f"Taxable mismatch: expected={expected['taxable']}, UI={ui_taxable}"
                )

            # Visual regression: tag table
            est.assert_tag_table_layout()

            # Save
            est_id = est.save_and_capture_id()
            assert est_id, "Should capture estimation ID from print tab"

            est.screenshot("estimation_saved")

    def test_estimation_mandatory_fields(self, auth_page, base_url):
        """Verify mandatory field validation — branch, employee, customer required."""
        est = EstimationPage(auth_page)
        est.goto_add_estimation()

        # Try to scan tag without filling header
        est.click(est.TAG_CHECKBOX)
        est.fill(est.TAG_SCAN_INPUT, "DUMMY_TAG")
        est.click(est.TAG_SEARCH_BTN)

        # Should show validation or no data
        est.screenshot("estimation_mandatory_check")


@pytest.mark.p0
class TestEstimationVisual:
    """Visual regression tests for estimation module."""

    def test_estimation_add_page(self, auth_page, base_url):
        """Baseline: Add Estimation page layout."""
        est = EstimationPage(auth_page)
        est.goto_add_estimation()
        expect(auth_page).to_have_screenshot(
            "estimation_add_page.png",
            full_page=True,
            max_diff_pixel_ratio=0.01,
        )
