"""
Approval To Invoice E2E Tests

Migrated from: Sparqla/Test_Purchase/ApprovalToInvoice.py (300 lines)
Tests: Pure to Amount conversion, Amount conversion, list verification.
"""
import pytest
from playwright.sync_api import expect
from pages.approval_to_invoice_page import ApprovalToInvoicePage


@pytest.mark.p3
@pytest.mark.purchase
class TestApprovalToInvoice:
    """Approval To Invoice module tests."""

    def test_form_loads(self, auth_page, base_url):
        ati = ApprovalToInvoicePage(auth_page)
        ati.goto()
        ati.click_add()
        expect(auth_page.locator(f"xpath={ati.SUBMIT_BTN}")).to_be_visible()
        ati.screenshot("approval_invoice_form_loaded")

    def test_form_visual(self, auth_page, base_url):
        ati = ApprovalToInvoicePage(auth_page)
        ati.goto()
        ati.click_add()
        ati.assert_add_layout()

    def test_pure_to_amount(self, auth_page, load_test_data, base_url):
        test_cases = load_test_data("approval_to_invoice")
        if not test_cases:
            pytest.skip("No approval invoice data")
        tc = next((t for t in test_cases
                    if t.get("rate_cut_type") == "pure to amount"), None)
        if not tc:
            pytest.skip("No pure to amount test case")

        ati = ApprovalToInvoicePage(auth_page)
        ati.goto()
        ati.click_add()

        ati.set_opening_balance(tc.get("opening_bal", "no").lower() == "yes")
        ati.set_rate_cut_type("pure to amount")
        ati.set_convert_to(tc.get("convert_to", "supplier"))
        ati.set_conversion_type(tc.get("conv_type", "fix"))
        ati.fill_supplier(tc["supplier"])
        ati.fill_ref_no(tc["ref_no"],
                        is_opening=tc.get("opening_bal", "no").lower() == "yes")
        ati.fill_dropdowns(
            metal=tc.get("metal"), category=tc.get("category"),
            product=tc.get("product"),
        )
        ati.fill_values(pure_weight=tc.get("pure_weight"), rate=tc.get("rate"))
        if tc.get("remark"):
            ati.fill_remark(tc["remark"])

        result = ati.submit()
        assert result["status"] == "pass", f"Submit failed: {result['message']}"
        ati.screenshot("pure_to_amount_complete")

    def test_amount_conversion(self, auth_page, load_test_data, base_url):
        test_cases = load_test_data("approval_to_invoice")
        if not test_cases:
            pytest.skip("No approval invoice data")
        tc = next((t for t in test_cases
                    if t.get("rate_cut_type") == "amount"), None)
        if not tc:
            pytest.skip("No amount test case")

        ati = ApprovalToInvoicePage(auth_page)
        ati.goto()
        ati.click_add()
        ati.set_rate_cut_type("amount")
        ati.set_convert_to(tc.get("convert_to", "supplier"))
        ati.fill_supplier(tc["supplier"])
        ati.fill_values(amount=tc["amount"])
        if tc.get("remark"):
            ati.fill_remark(tc["remark"])

        result = ati.submit()
        assert result["status"] == "pass"
        ati.screenshot("amount_conversion_complete")
