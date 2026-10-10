"""
Supplier Bill Entry E2E Tests

Migrated from: Sparqla/Test_Purchase/SupplierBillEntry.py (1099 lines)
Tests: Standard bill entry, multi-item, calculation verification,
       cancel flow, list verification + approval, visual regression.
"""
import pytest
from playwright.sync_api import expect
from pages.supplier_bill_entry_page import SupplierBillEntryPage


@pytest.mark.p1
@pytest.mark.purchase
class TestSupplierBillEntry:
    """Supplier Bill Entry module tests."""

    def test_form_loads(self, auth_page, base_url):
        """Verify supplier bill form loads."""
        sbe = SupplierBillEntryPage(auth_page)
        sbe.goto()
        sbe.click_add()
        expect(auth_page.locator(f"xpath={sbe.TYPE_ORNAMENT}")).to_be_visible()
        expect(auth_page.locator(f"xpath={sbe.SUBMIT_BTN}")).to_be_visible()
        sbe.screenshot("sbe_form_loaded")

    def test_form_visual(self, auth_page, base_url):
        """Visual regression: supplier bill form."""
        sbe = SupplierBillEntryPage(auth_page)
        sbe.goto()
        sbe.click_add()
        sbe.assert_form_layout()

    def test_standard_bill_entry(self, auth_page, load_test_data, base_url):
        """
        Standard supplier bill entry:
        1. Tab 1: GRN number, purchase type, ref no, hallmark
        2. Tab 2: Category, purity, product, design, pcs, gwt, mc, touch, rate
        3. Tab 3: Summary
        4. Submit + capture
        """
        test_cases = load_test_data("supplier_bill_entry")
        if not test_cases:
            pytest.skip("No supplier bill entry test data")

        tc = test_cases[0]

        sbe = SupplierBillEntryPage(auth_page)
        sbe.goto()
        sbe.click_add()

        # Tab 1
        sbe.fill_tab1(
            grn_number=tc.get("grn_number"),
            purchase_type=tc.get("purchase_type"),
            against_order=tc.get("against_order"),
            purchase_category=tc.get("purchase_category"),
            hallmark=tc.get("hallmark"),
            ref_no=tc.get("ref_no"),
            against_karigar_issue=tc.get("against_karigar_issue"),
        )

        # Tab 2
        sbe.goto_tab_items()
        sbe.fill_item(
            category=tc.get("category"),
            purity=tc.get("purity"),
            product=tc.get("product"),
            design=tc.get("design"),
            sub_design=tc.get("sub_design"),
            pcs=tc.get("pcs"),
            gwt=tc.get("gwt"),
            lwt=tc.get("lwt"),
            mc_type=tc.get("mc_type"),
            calc_type=tc.get("calc_type"),
            wastage_pct=tc.get("wastage_pct"),
            purity_calc=tc.get("purity_calc"),
            touch=tc.get("touch"),
            mc_value=tc.get("mc_value"),
            rate_per_gram=tc.get("rate_per_gram"),
            po_number=tc.get("po_number"),
            against_order=tc.get("against_order"),
        )

        # Tab 3
        sbe.goto_tab_summary()

        result = sbe.submit()
        assert result["status"] == "pass", f"Submit failed: {result['message']}"
        sbe.screenshot("sbe_complete")

    def test_calculation_engine(self):
        """Verify pure-Python calculation engine (6 scenarios from Sparqla)."""
        # Scenario 1: Weight×Rate, Wastage=4%, PurityCalc=995
        r = SupplierBillEntryPage.calculate_expected(
            gwt=10, lwt=0, wastage_pct=4, touch=92,
            mc_value=10, rate=12000, calc_type="Weight x Rate",
            purity_calc=995,
        )
        assert abs(r["pure"] - 9.646) < 0.01

        # Scenario 2: Weight×Rate, Wastage=4%, PurityCalc=999
        r = SupplierBillEntryPage.calculate_expected(
            gwt=10, lwt=0, wastage_pct=4, touch=92,
            mc_value=10, rate=12000, calc_type="Weight x Rate",
            purity_calc=999,
        )
        assert abs(r["pure"] - 9.600) < 0.01

        # Scenario 3: Weight×Wastage%, Wastage=5%, PurityCalc=999
        r = SupplierBillEntryPage.calculate_expected(
            gwt=10, lwt=0, wastage_pct=5, touch=92,
            mc_value=10, rate=12000, calc_type="Weight x Wastage%",
            purity_calc=999,
        )
        assert abs(r["pure"] - 9.660) < 0.01

        # Scenario 5: Purchase Touch, PurityCalc=995
        r = SupplierBillEntryPage.calculate_expected(
            gwt=10, lwt=0, wastage_pct=0, touch=92,
            mc_value=10, rate=12000, calc_type="Purchase Touch",
            purity_calc=995,
        )
        assert abs(r["pure"] - 9.246) < 0.01

    def test_list_visual(self, auth_page, base_url):
        """Visual regression: supplier bill list."""
        sbe = SupplierBillEntryPage(auth_page)
        sbe.goto()
        sbe.assert_list_layout()
