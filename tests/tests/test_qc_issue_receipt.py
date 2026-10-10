"""
QC Issue/Receipt E2E Tests

Migrated from: Sparqla/Test_Purchase/QCIssueReceipt.py (317 lines)
Tests: Issue flow, receipt flow with rejections, list verification.
"""
import pytest
from playwright.sync_api import expect
from pages.qc_issue_receipt_page import QCIssueReceiptPage


@pytest.mark.p3
@pytest.mark.purchase
class TestQCIssueReceipt:
    """QC Issue/Receipt module tests."""

    def test_list_loads(self, auth_page, base_url):
        qc = QCIssueReceiptPage(auth_page)
        qc.goto()
        expect(auth_page.locator(f"xpath={qc.ISSUE_BTN}")).to_be_visible()
        expect(auth_page.locator(f"xpath={qc.RECEIPT_BTN}")).to_be_visible()
        qc.screenshot("qc_list_loaded")

    def test_list_visual(self, auth_page, base_url):
        qc = QCIssueReceiptPage(auth_page)
        qc.goto()
        qc.assert_list_layout()

    def test_issue_flow(self, auth_page, load_test_data, base_url):
        test_cases = load_test_data("qc_issue_receipt")
        if not test_cases:
            pytest.skip("No QC test data")
        tc = next((t for t in test_cases if t.get("flow_type") == "issue"), None)
        if not tc:
            pytest.skip("No issue test case")

        qc = QCIssueReceiptPage(auth_page)
        qc.goto()
        qc.click_issue()
        qc.fill_issue(po_ref_no=tc["po_ref_no"], employee=tc.get("employee"))
        result = qc.submit_issue()
        assert result["status"] == "pass", f"Issue failed: {result['message']}"
        qc.screenshot("qc_issue_complete")

    def test_receipt_flow(self, auth_page, load_test_data, base_url):
        test_cases = load_test_data("qc_issue_receipt")
        if not test_cases:
            pytest.skip("No QC test data")
        tc = next((t for t in test_cases if t.get("flow_type") == "receipt"), None)
        if not tc:
            pytest.skip("No receipt test case")

        qc = QCIssueReceiptPage(auth_page)
        qc.goto()
        qc.click_receipt()
        qc.fill_receipt(
            qc_ref_no=tc["qc_ref_no"],
            rejected_pcs=tc.get("rejected_pcs"),
            rejected_gwt=tc.get("rejected_gwt"),
            rejected_lwt=tc.get("rejected_lwt"),
        )
        result = qc.submit_receipt()
        assert result["status"] == "pass", f"Receipt failed: {result['message']}"
        qc.screenshot("qc_receipt_complete")
