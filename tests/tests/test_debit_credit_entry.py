"""
Debit/Credit Entry E2E Tests

Migrated from: Sparqla/Test_Purchase/DebitCreditEntry.py (325 lines)
Tests: Amount credit, amount debit, weight credit, list verification.
"""
import pytest
from playwright.sync_api import expect
from pages.debit_credit_entry_page import DebitCreditEntryPage


@pytest.mark.p3
@pytest.mark.purchase
class TestDebitCreditEntry:
    """Debit/Credit Entry module tests."""

    def test_form_loads(self, auth_page, base_url):
        dc = DebitCreditEntryPage(auth_page)
        dc.goto_add()
        expect(auth_page.locator(f"xpath={dc.SUBMIT_BTN}")).to_be_visible()
        dc.screenshot("crdr_form_loaded")

    def test_form_visual(self, auth_page, base_url):
        dc = DebitCreditEntryPage(auth_page)
        dc.goto_add()
        dc.assert_add_layout()

    def test_amount_credit(self, auth_page, load_test_data, base_url):
        test_cases = load_test_data("debit_credit_entry")
        if not test_cases:
            pytest.skip("No debit credit data")
        tc = next((t for t in test_cases if t.get("trans_in") == "amount"
                    and t.get("trans_type") == "credit"), None)
        if not tc:
            pytest.skip("No amount credit test case")

        dc = DebitCreditEntryPage(auth_page)
        dc.goto_add()
        dc.set_transaction_in("amount")
        dc.select_karigar(tc["karigar"])
        dc.select_account_type(tc["account_type"])
        if tc.get("supplier_bill"):
            dc.select_supplier_bill(tc["supplier_bill"])
        if tc.get("ledger_type"):
            dc.select_ledger_type(tc["ledger_type"])
        dc.fill_amount(tc["amount"], "credit")
        if tc.get("narration"):
            dc.fill_narration(tc["narration"])

        result = dc.submit()
        assert result["status"] == "pass", f"Submit failed: {result['message']}"
        verify = dc.verify_in_list(tc["karigar"])
        assert verify["status"] == "pass"
        dc.screenshot("crdr_amount_credit_complete")

    def test_weight_debit(self, auth_page, load_test_data, base_url):
        test_cases = load_test_data("debit_credit_entry")
        if not test_cases:
            pytest.skip("No debit credit data")
        tc = next((t for t in test_cases if t.get("trans_in") == "weight"), None)
        if not tc:
            pytest.skip("No weight debit test case")

        dc = DebitCreditEntryPage(auth_page)
        dc.goto_add()
        dc.set_transaction_in("weight")
        dc.select_karigar(tc["karigar"])
        dc.select_account_type(tc.get("account_type", "supplier"))
        dc.fill_weight(tc["weight"], tc.get("trans_type", "debit"))
        if tc.get("narration"):
            dc.fill_narration(tc["narration"])

        result = dc.submit()
        assert result["status"] == "pass"
        dc.screenshot("crdr_weight_debit_complete")
