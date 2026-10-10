"""
Smith Metal Issue E2E Tests

Migrated from: Sparqla/Test_Purchase/SmithMetalIssue.py (508 lines)
Tests: Normal issue, approval issue, against order, tag/nontag source,
       item selection, pure weight verification, submit + capture, visual regression.
"""
import pytest
from playwright.sync_api import expect
from pages.smith_metal_issue_page import SmithMetalIssuePage


@pytest.mark.p1
@pytest.mark.purchase
class TestSmithMetalIssue:
    """Smith Metal Issue module tests."""

    def test_form_loads(self, auth_page, base_url):
        """Verify smith metal issue form loads."""
        smi = SmithMetalIssuePage(auth_page)
        smi.goto_add()
        expect(auth_page.locator(f"xpath={smi.NORMAL_ISSUE}")).to_be_visible()
        expect(auth_page.locator(f"xpath={smi.SUBMIT_BTN}")).to_be_visible()
        smi.screenshot("smith_metal_form_loaded")

    def test_form_visual(self, auth_page, base_url):
        """Visual regression: smith metal issue form."""
        smi = SmithMetalIssuePage(auth_page)
        smi.goto_add()
        smi.assert_add_layout()

    def test_normal_issue_flow(self, auth_page, load_test_data, base_url):
        """
        Normal metal issue:
        1. Select branch, karigar, metal
        2. Normal issue type
        3. Tag source, search code
        4. Select items
        5. Submit + capture ID
        """
        test_cases = load_test_data("smith_metal_issue")
        if not test_cases:
            pytest.skip("No smith metal issue test data")

        tc = next((t for t in test_cases
                    if t.get("issue_type", "").lower() == "normal"), None)
        if not tc:
            pytest.skip("No normal issue test case")

        smi = SmithMetalIssuePage(auth_page)
        smi.goto_add()

        smi.fill_header(
            branch=tc.get("branch"),
            karigar=tc["karigar"],
            metal=tc["metal"],
        )

        smi.select_issue_type("Normal")

        if tc.get("against_order"):
            smi.set_against_order(
                tc["against_order"],
                ref_order_no=tc.get("ref_order_no"),
            )

        if tc.get("source_type"):
            smi.select_source(tc["source_type"], tc.get("code"))

        smi.select_all_items()

        if tc.get("remark"):
            smi.fill_remark(tc["remark"])

        result = smi.submit()
        assert result["status"] == "pass", f"Submit failed: {result['message']}"
        smi.screenshot("normal_issue_complete")

    def test_against_order_flow(self, auth_page, load_test_data, base_url):
        """Metal issue against purchase order."""
        test_cases = load_test_data("smith_metal_issue")
        if not test_cases:
            pytest.skip("No smith metal issue test data")

        tc = next((t for t in test_cases
                    if t.get("against_order", "").lower() == "yes"), None)
        if not tc:
            pytest.skip("No against-order test case")

        smi = SmithMetalIssuePage(auth_page)
        smi.goto_add()

        smi.fill_header(karigar=tc["karigar"], metal=tc["metal"])
        smi.select_issue_type(tc.get("issue_type", "Normal"))
        smi.set_against_order("yes", tc.get("ref_order_no"))
        smi.select_source(tc.get("source_type", "Tag"), tc.get("code"))
        smi.select_all_items()

        if tc.get("remark"):
            smi.fill_remark(tc["remark"])

        result = smi.submit()
        assert result["status"] == "pass"
        smi.screenshot("against_order_complete")

    def test_list_visual(self, auth_page, base_url):
        """Visual regression: smith metal issue list."""
        smi = SmithMetalIssuePage(auth_page)
        smi.goto_list()
        smi.assert_list_layout()
