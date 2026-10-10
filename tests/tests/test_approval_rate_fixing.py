"""
Approval Rate Fixing E2E Tests

Migrated from: Sparqla/Test_Purchase/ApprovalRateFixing.py (525 lines)
Tests: Standard entry, approve, cancel, GST calculation, list verify.
"""
import pytest
from playwright.sync_api import expect
from pages.approval_rate_fixing_page import ApprovalRateFixingPage


@pytest.mark.p3
@pytest.mark.purchase
class TestApprovalRateFixing:
    """Approval Rate Fixing module tests."""

    def test_form_loads(self, auth_page, base_url):
        arf = ApprovalRateFixingPage(auth_page)
        arf.goto_add()
        expect(auth_page.locator(f"xpath={arf.SUBMIT_BTN}")).to_be_visible()
        arf.screenshot("rate_fixing_form_loaded")

    def test_form_visual(self, auth_page, base_url):
        arf = ApprovalRateFixingPage(auth_page)
        arf.goto_add()
        arf.assert_add_layout()

    def test_standard_entry(self, auth_page, load_test_data, base_url):
        test_cases = load_test_data("approval_rate_fixing")
        if not test_cases:
            pytest.skip("No rate fixing data")
        tc = next((t for t in test_cases if t.get("scenario") == "standard"), None)
        if not tc:
            pytest.skip("No standard entry test case")

        arf = ApprovalRateFixingPage(auth_page)
        arf.goto_add()
        arf.fill_header(tc["karigar"], tc["approval_ref"])
        arf.fill_item_rows(tc["fix_wt"], tc["rate"])
        if tc.get("remark"):
            arf.fill_remark(tc["remark"])

        result = arf.submit()
        assert result["status"] == "pass", f"Submit failed: {result['message']}"
        arf.screenshot("rate_fix_complete")

    def test_gst_calculation(self, auth_page, base_url):
        """Verify Python-side GST calculation engine."""
        result = ApprovalRateFixingPage.calc_gst(10.0, 6500.0, 3.0)
        assert result["taxable"] == 65000.0
        assert result["tax"] == 1950.0
        assert result["payable"] == 66950.0

    def test_list_visual(self, auth_page, base_url):
        arf = ApprovalRateFixingPage(auth_page)
        arf.goto_list()
        arf.assert_list_layout()
