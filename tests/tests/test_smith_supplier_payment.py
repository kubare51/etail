"""
Smith Supplier Payment E2E Tests

Migrated from: Sparqla/Test_Purchase/SmithSupplierPayment.py (477 lines)
Tests: Cash payment, net banking, cheque modal, cash >10k validation,
       bill selection, list verification, visual regression.
"""
import pytest
from playwright.sync_api import expect
from pages.smith_supplier_payment_page import SmithSupplierPaymentPage


@pytest.mark.p2
@pytest.mark.purchase
class TestSmithSupplierPayment:
    """Smith Supplier Payment module tests."""

    def test_form_loads(self, auth_page, base_url):
        """Verify supplier payment form loads."""
        sp = SmithSupplierPaymentPage(auth_page)
        sp.goto_add()
        expect(auth_page.locator(f"xpath={sp.BILL_TYPE_BILL}")).to_be_visible()
        expect(auth_page.locator(f"xpath={sp.SUBMIT_BTN}")).to_be_visible()
        sp.screenshot("supplier_payment_form_loaded")

    def test_form_visual(self, auth_page, base_url):
        """Visual regression: supplier payment form."""
        sp = SmithSupplierPaymentPage(auth_page)
        sp.goto_add()
        sp.assert_add_layout()

    def test_cash_payment(self, auth_page, load_test_data, base_url):
        """
        Cash payment flow:
        1. Select BILL type
        2. Select karigar → bills auto-load
        3. Enter cash amount
        4. Submit
        """
        test_cases = load_test_data("smith_supplier_payment")
        if not test_cases:
            pytest.skip("No supplier payment test data")

        tc = next((t for t in test_cases
                    if t.get("payment_mode") == "Cash"), None)
        if not tc:
            pytest.skip("No cash payment test case")

        sp = SmithSupplierPaymentPage(auth_page)
        sp.goto_add()

        sp.select_bill_type(tc.get("bill_type", "BILL"))
        sp.select_karigar(tc["karigar"])
        sp.select_bills(tc.get("bill_selection", "All"))
        sp.fill_cash_amount(tc["cash_amount"])

        if tc.get("remark"):
            sp.fill_remark(tc["remark"])

        result = sp.submit()
        assert result["status"] == "pass", f"Submit failed: {result['message']}"

        verify = sp.verify_in_list(tc["karigar"])
        assert verify["status"] == "pass"
        sp.screenshot("cash_payment_complete")

    def test_cash_over_10k_blocked(self, auth_page, load_test_data, base_url):
        """Cash > ₹10,000 should be blocked by validation."""
        test_cases = load_test_data("smith_supplier_payment")
        if not test_cases:
            pytest.skip("No supplier payment test data")

        tc = next((t for t in test_cases
                    if t.get("payment_mode") == "Cash_Over_10k"), None)
        if not tc:
            pytest.skip("No cash >10k test case")

        sp = SmithSupplierPaymentPage(auth_page)
        sp.goto_add()
        sp.select_bill_type("BILL")
        sp.select_karigar(tc["karigar"])
        sp.fill_cash_amount(tc["cash_amount"])

        result = sp.submit()
        assert result["status"] == "blocked"
        sp.screenshot("cash_over_10k_blocked")

    def test_net_banking_payment(self, auth_page, load_test_data, base_url):
        """Net banking payment via modal."""
        test_cases = load_test_data("smith_supplier_payment")
        if not test_cases:
            pytest.skip("No supplier payment test data")

        tc = next((t for t in test_cases
                    if t.get("payment_mode") == "NetBanking"), None)
        if not tc:
            pytest.skip("No net banking test case")

        sp = SmithSupplierPaymentPage(auth_page)
        sp.goto_add()
        sp.select_bill_type("BILL")
        sp.select_karigar(tc["karigar"])
        sp.fill_net_banking(
            nb_type=tc["nb_type"], bank=tc["nb_bank"],
            amount=tc["nb_amount"], ref_no=tc["nb_ref_no"],
            date=tc.get("nb_date", ""),
        )

        result = sp.submit()
        assert result["status"] == "pass"
        sp.screenshot("net_banking_complete")

    def test_list_visual(self, auth_page, base_url):
        """Visual regression: supplier payment list."""
        sp = SmithSupplierPaymentPage(auth_page)
        sp.goto_list()
        sp.assert_list_layout()
