"""
GRN Entry E2E Tests

Migrated from: Sparqla/Test_Purchase/GRNEntry.py (1083 lines)
Tests: Bill GRN, Receipt GRN, Charges GRN, cancel flow + modal,
       list verification, visual regression.
"""
import pytest
from playwright.sync_api import expect
from pages.grn_entry_page import GRNEntryPage


@pytest.mark.p1
@pytest.mark.purchase
class TestGRNEntry:
    """GRN Entry module tests."""

    def test_form_loads(self, auth_page, base_url):
        """Verify GRN form loads."""
        grn = GRNEntryPage(auth_page)
        grn.goto()
        grn.click_add()
        expect(auth_page.locator(f"xpath={grn.TYPE_BILL}")).to_be_visible()
        expect(auth_page.locator(f"xpath={grn.SUBMIT_BTN}")).to_be_visible()
        grn.screenshot("grn_form_loaded")

    def test_form_visual(self, auth_page, base_url):
        """Visual regression: GRN form."""
        grn = GRNEntryPage(auth_page)
        grn.goto()
        grn.click_add()
        grn.assert_form_layout()

    def test_bill_grn_creation(self, auth_page, load_test_data, base_url):
        """
        Bill GRN creation:
        1. Select Bill type
        2. Fill karigar, ref no, purchase type, ref date, dispatch
        3. Add item (category, pcs, gwt, rate)
        4. Save + capture GRN no
        """
        test_cases = load_test_data("grn_entry")
        if not test_cases:
            pytest.skip("No GRN entry test data")

        tc = next((t for t in test_cases if t.get("grn_type") == "Bill"), None)
        if not tc:
            pytest.skip("No Bill GRN test case")

        grn = GRNEntryPage(auth_page)
        grn.goto()
        grn.click_add()

        grn.select_grn_type("Bill")
        grn.fill_header(
            karigar=tc["karigar"],
            ref_no=tc["ref_no"],
            purchase_type=tc["purchase_type"],
            ref_date=tc["ref_date"],
            dispatch_through=tc["dispatch_through"],
            eway_bill=tc.get("eway_bill"),
        )

        grn.add_item(
            category=tc["category"],
            pcs=tc["pcs"],
            gross_wt=tc["gross_wt"],
            rate_per_gram=tc.get("rate_per_gram"),
            rate_type=tc.get("rate_type"),
            grn_type="Bill",
        )

        if tc.get("tds"):
            grn.fill_tax(tds=tc["tds"])

        result = grn.save()
        assert result["status"] == "pass", f"Save failed: {result['message']}"
        assert result.get("grn_no"), "Should capture GRN number"

        # Verify
        verify = grn.verify_in_list(result["grn_no"])
        assert verify["status"] == "pass"
        grn.screenshot("bill_grn_complete")

    def test_receipt_grn_creation(self, auth_page, load_test_data, base_url):
        """Receipt GRN creation with wastage."""
        test_cases = load_test_data("grn_entry")
        if not test_cases:
            pytest.skip("No GRN entry test data")

        tc = next((t for t in test_cases if t.get("grn_type") == "Receipt"), None)
        if not tc:
            pytest.skip("No Receipt GRN test case")

        grn = GRNEntryPage(auth_page)
        grn.goto()
        grn.click_add()

        grn.select_grn_type("Receipt")
        grn.fill_header(
            karigar=tc["karigar"],
            ref_no=tc["ref_no"],
            purchase_type=tc["purchase_type"],
            ref_date=tc["ref_date"],
            dispatch_through=tc["dispatch_through"],
        )

        grn.add_item(
            category=tc["category"],
            pcs=tc["pcs"],
            gross_wt=tc["gross_wt"],
            wastage=tc.get("wastage"),
            rate_type=tc.get("rate_type"),
            grn_type="Receipt",
        )

        result = grn.save()
        assert result["status"] == "pass"
        grn.screenshot("receipt_grn_complete")

    def test_cancel_grn(self, auth_page, load_test_data, base_url):
        """Cancel GRN: create then cancel with modal."""
        test_cases = load_test_data("grn_entry")
        if not test_cases:
            pytest.skip("No GRN entry test data")

        tc = next((t for t in test_cases if t.get("grn_type") == "Bill"), None)
        if not tc:
            pytest.skip("No Bill GRN for cancel test")

        grn = GRNEntryPage(auth_page)
        grn.goto()
        grn.click_add()

        grn.select_grn_type("Bill")
        grn.fill_header(
            karigar=tc["karigar"],
            ref_no=tc["ref_no"],
            purchase_type=tc["purchase_type"],
            ref_date=tc["ref_date"],
            dispatch_through=tc["dispatch_through"],
        )
        grn.add_item(
            category=tc["category"], pcs=tc["pcs"],
            gross_wt=tc["gross_wt"], rate_per_gram=tc.get("rate_per_gram"),
            grn_type="Bill",
        )

        result = grn.save()
        assert result["status"] == "pass"

        cancel_result = grn.cancel_grn(
            result["grn_no"],
            reason=tc.get("cancel_reason", "Automation cancel test")
        )
        assert cancel_result["status"] == "pass"
        grn.screenshot("grn_cancel_complete")

    def test_list_visual(self, auth_page, base_url):
        """Visual regression: GRN list page."""
        grn = GRNEntryPage(auth_page)
        grn.goto()
        grn.assert_list_layout()
