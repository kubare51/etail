"""
HM Issue/Receipt E2E Tests

Migrated from: Sparqla/Test_Purchase/HMIssueReceipt.py (319 lines)
Tests: Issue flow (PO Ref → select all → submit),
       Receipt flow (HM Ref → vendor ref → charges → submit),
       Issue→Receipt linked flow, list verification.
"""
import pytest
from playwright.sync_api import expect
from pages.hm_issue_receipt_page import HMIssueReceiptPage


@pytest.mark.p2
@pytest.mark.purchase
class TestHMIssueReceipt:
    """HM Issue/Receipt module tests."""

    def test_list_loads(self, auth_page, base_url):
        """Verify HM Issue/Receipt list loads."""
        hm = HMIssueReceiptPage(auth_page)
        hm.goto()
        expect(auth_page.locator(f"xpath={hm.ISSUE_BTN}")).to_be_visible()
        expect(auth_page.locator(f"xpath={hm.RECEIPT_BTN}")).to_be_visible()
        hm.screenshot("hm_list_loaded")

    def test_list_visual(self, auth_page, base_url):
        """Visual regression: HM Issue/Receipt list."""
        hm = HMIssueReceiptPage(auth_page)
        hm.goto()
        hm.assert_list_layout()

    def test_issue_flow(self, auth_page, load_test_data, base_url):
        """
        HM Issue flow:
        1. Click Issue button
        2. Select PO Ref No
        3. Select karigar
        4. Select all items
        5. Submit → capture HM Ref No
        """
        test_cases = load_test_data("hm_issue_receipt")
        if not test_cases:
            pytest.skip("No HM issue/receipt test data")

        tc = next((t for t in test_cases
                    if t.get("flow_type") == "issue"), None)
        if not tc:
            pytest.skip("No issue flow test case")

        hm = HMIssueReceiptPage(auth_page)
        hm.goto()
        hm.click_issue()

        hm.fill_issue(
            po_ref_no=tc["po_ref_no"],
            karigar=tc.get("karigar"),
        )

        result = hm.submit_issue()
        assert result["status"] == "pass", f"Issue failed: {result['message']}"
        assert result.get("hm_ref"), "Should capture HM Ref No"

        # Verify in list
        verify = hm.verify_in_list(result["hm_ref"], "Issued")
        assert verify["status"] == "pass"
        hm.screenshot("hm_issue_complete")

    def test_receipt_flow(self, auth_page, load_test_data, base_url):
        """
        HM Receipt flow:
        1. Click Receipt button
        2. Select HM Ref No
        3. Fill vendor ref, charges, rejected pcs/gwt
        4. Submit
        """
        test_cases = load_test_data("hm_issue_receipt")
        if not test_cases:
            pytest.skip("No HM issue/receipt test data")

        tc = next((t for t in test_cases
                    if t.get("flow_type") == "receipt"), None)
        if not tc:
            pytest.skip("No receipt flow test case")

        hm = HMIssueReceiptPage(auth_page)
        hm.goto()
        hm.click_receipt()

        hm.fill_receipt(
            hm_ref_no=tc["hm_ref_no"],
            vendor_ref=tc.get("vendor_ref"),
            hm_charge=tc.get("hm_charge", "10"),
            rejected_pcs=tc.get("rejected_pcs"),
            rejected_gwt=tc.get("rejected_gwt"),
        )

        result = hm.submit_receipt()
        assert result["status"] == "pass", f"Receipt failed: {result['message']}"

        # Verify in list
        verify = hm.verify_in_list(tc["hm_ref_no"], "Completed")
        assert verify["status"] == "pass"
        hm.screenshot("hm_receipt_complete")
