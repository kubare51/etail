"""
Smith Company Opening Balance E2E Tests

Migrated from: Sparqla/Test_Purchase/SmithCompanyOpBal.py (331 lines)
Tests: Metal ornament entry, metal old metal entry, list verify.
"""
import pytest
from playwright.sync_api import expect
from pages.smith_company_op_bal_page import SmithCompanyOpBalPage


@pytest.mark.p3
@pytest.mark.purchase
class TestSmithCompanyOpBal:
    """Smith/Company Opening Balance module tests."""

    def test_list_loads(self, auth_page, base_url):
        ob = SmithCompanyOpBalPage(auth_page)
        ob.goto()
        expect(auth_page.locator(f"xpath={ob.ADD_BTN}")).to_be_visible()
        ob.screenshot("op_bal_list_loaded")

    def test_list_visual(self, auth_page, base_url):
        ob = SmithCompanyOpBalPage(auth_page)
        ob.goto()
        ob.assert_list_layout()

    def test_metal_ornament_entry(self, auth_page, load_test_data, base_url):
        test_cases = load_test_data("smith_company_op_bal")
        if not test_cases:
            pytest.skip("No op bal data")
        tc = next((t for t in test_cases if t.get("metal_type") == "oranment"), None)
        if not tc:
            pytest.skip("No ornament entry test case")

        ob = SmithCompanyOpBalPage(auth_page)
        ob.goto()
        ob.click_add()
        ob.select_smith_type(tc.get("smith_type", "supplier"))
        ob.select_balance_type(tc.get("bal_type", "metal"))
        ob.select_metal_type("oranment")
        ob.fill_dropdowns(
            karigar=tc.get("karigar"), metal=tc.get("metal"),
            category=tc.get("category"), product=tc.get("product"),
        )
        ob.fill_weights(
            gross=tc.get("gross_wgt"), net=tc.get("net_wgt"),
            pure=tc.get("pure_wgt"), wt_type=tc.get("wt_type", "credit"),
        )
        if tc.get("amount"):
            ob.fill_amount(tc["amount"], tc.get("amt_type", "credit"))
        if tc.get("remark"):
            ob.fill_remark(tc["remark"])

        result = ob.submit()
        assert result["status"] == "pass", f"Submit failed: {result['message']}"
        ob.screenshot("op_bal_ornament_complete")

    def test_metal_old_metal_entry(self, auth_page, load_test_data, base_url):
        test_cases = load_test_data("smith_company_op_bal")
        if not test_cases:
            pytest.skip("No op bal data")
        tc = next((t for t in test_cases if t.get("metal_type") == "old metal"), None)
        if not tc:
            pytest.skip("No old metal test case")

        ob = SmithCompanyOpBalPage(auth_page)
        ob.goto()
        ob.click_add()
        ob.select_smith_type(tc.get("smith_type", "supplier"))
        ob.select_balance_type("metal")
        ob.select_metal_type("old metal")
        ob.fill_dropdowns(karigar=tc.get("karigar"), metal=tc.get("metal"),
                          category=tc.get("category"))
        ob.fill_weights(gross=tc.get("gross_wgt"), pure=tc.get("pure_wgt"),
                        wt_type=tc.get("wt_type", "debit"))
        if tc.get("remark"):
            ob.fill_remark(tc["remark"])

        result = ob.submit()
        assert result["status"] == "pass"
        ob.screenshot("op_bal_old_metal_complete")
