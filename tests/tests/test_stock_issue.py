"""
Stock Issue / Receipt E2E Tests

Migrated from: Sparqla/Test_StockIssue/StockIssue.py (572 lines)
Tests: Tagged issue, non-tagged issue, receipt, list verification,
       visual regression for issue form and list page.
"""
import pytest
from playwright.sync_api import expect
from pages.stock_issue_page import StockIssuePage


@pytest.mark.p0
@pytest.mark.inventory
class TestStockIssue:
    """Stock Issue module tests."""

    def test_issue_form_loads(self, auth_page, base_url):
        """Verify stock issue form loads with all required fields."""
        si = StockIssuePage(auth_page)
        si.goto()
        si.start_issue()

        # Verify key elements
        expect(auth_page.locator(f"xpath={si.TYPE_ISSUE}")).to_be_visible()
        expect(auth_page.locator(f"xpath={si.BRANCH_SELECT2}")).to_be_visible()

        si.screenshot("stock_issue_form_loaded")

    def test_issue_form_visual(self, auth_page, base_url):
        """Visual regression: stock issue form layout."""
        si = StockIssuePage(auth_page)
        si.goto()
        si.start_issue()
        si.assert_issue_form_layout()

    def test_tagged_issue_flow(self, auth_page, load_test_data, base_url):
        """
        Full tagged stock issue flow:
        1. Select Issue type
        2. Fill header (branch, employee, issue type, stock type, metal)
        3. Fill recipient
        4. Scan tag
        5. Submit
        6. Verify in list
        """
        test_cases = load_test_data("stock_issue")
        if not test_cases:
            pytest.skip("No stock issue test data")

        tc = next((t for t in test_cases if t.get("stock_type") == "Taged" and t.get("process_type") == "Issue"), None)
        if not tc:
            pytest.skip("No tagged issue test case found")

        si = StockIssuePage(auth_page)
        si.goto()

        # Start issue
        si.start_issue()

        # Fill header
        si.fill_issue_header(
            issue_from=tc.get("issue_from"),
            employee=tc.get("sel_emp"),
            issue_type=tc.get("issue_type"),
            stock_type=tc.get("stock_type"),
            metal=tc.get("metal"),
        )

        # Fill recipient
        si.fill_recipient(
            issued_to=tc["issued_to"],
            customer=tc.get("cus_name"),
            employee=tc.get("issue_employee"),
            karigar=tc.get("karigar"),
        )

        # Scan tag
        si.scan_tag(
            tag_code=tc["tag_code"],
            section=tc.get("section"),
        )

        # Rate and remark
        si.fill_rate_and_remark(
            rate=tc.get("rate_per_gram"),
            remark=tc.get("remark"),
        )

        # Submit
        issue_id = si.submit_issue()
        assert issue_id, "Should capture issue ID from new tab"

        # Verify in list
        result = si.verify_in_list(issue_id, status="Issued")
        assert result["status"] == "pass", f"List verification failed: {result['message']}"

        si.screenshot("tagged_issue_complete")

    def test_nontag_issue_flow(self, auth_page, load_test_data, base_url):
        """Non-tagged stock issue flow with multi-item grid fill."""
        test_cases = load_test_data("stock_issue")
        if not test_cases:
            pytest.skip("No stock issue test data")

        tc = next((t for t in test_cases if t.get("stock_type") != "Taged" and t.get("process_type") == "Issue"), None)
        if not tc:
            pytest.skip("No non-tagged issue test case found")

        si = StockIssuePage(auth_page)
        si.goto()
        si.start_issue()

        si.fill_issue_header(
            issue_from=tc.get("issue_from"),
            employee=tc.get("sel_emp"),
            issue_type=tc.get("issue_type"),
            stock_type=tc.get("stock_type"),
            metal=tc.get("metal"),
        )

        si.fill_recipient(
            issued_to=tc["issued_to"],
            customer=tc.get("cus_name"),
        )

        # Fill non-tag grid
        products = [p.strip() for p in str(tc.get("nt_product", "")).split("|")]
        pcs = [p.strip() for p in str(tc.get("nt_pcs", "")).split("|")]
        gwt = [p.strip() for p in str(tc.get("nt_gross_wt", "")).split("|")]
        nwt = [p.strip() for p in str(tc.get("nt_net_wt", "")).split("|")]

        si.fill_nontag_items(products, pcs, gwt, nwt)
        si.fill_rate_and_remark(rate=tc.get("rate_per_gram"))

        issue_id = si.submit_issue()
        assert issue_id, "Should capture issue ID"

        result = si.verify_in_list(issue_id, status="Issued")
        assert result["status"] == "pass", f"List verification failed: {result['message']}"

        si.screenshot("nontag_issue_complete")

    def test_issue_list_visual(self, auth_page, base_url):
        """Visual regression: stock issue list page."""
        si = StockIssuePage(auth_page)
        si.goto()
        si.assert_list_layout()


@pytest.mark.p0
@pytest.mark.inventory
class TestStockReceipt:
    """Stock Receipt module tests."""

    def test_receipt_form_loads(self, auth_page, base_url):
        """Verify receipt form loads."""
        si = StockIssuePage(auth_page)
        si.goto()
        si.start_receipt()

        expect(auth_page.locator(f"xpath={si.TYPE_RECEIPT}")).to_be_checked()
        si.screenshot("stock_receipt_form_loaded")

    def test_receipt_against_issue(self, auth_page, load_test_data, base_url):
        """
        Receipt flow:
        1. Select Receipt type
        2. Fill header
        3. Select Issue No to receive against
        4. Select all items
        5. Submit
        """
        test_cases = load_test_data("stock_issue")
        if not test_cases:
            pytest.skip("No stock issue test data")

        tc = next((t for t in test_cases if t.get("process_type") == "Receipt"), None)
        if not tc:
            pytest.skip("No receipt test case found")

        si = StockIssuePage(auth_page)
        si.goto()
        si.start_receipt()

        si.fill_receipt_header(
            issue_from=tc.get("issue_from"),
            employee=tc.get("sel_emp"),
            stock_type=tc.get("stock_type"),
        )

        if tc.get("receipt_issue_no"):
            si.select_receipt_issue_no(tc["receipt_issue_no"])

            stock_type = str(tc.get("stock_type", "")).strip()
            if stock_type != "Taged":
                si.select_all_receipt_items()

        success = si.submit_receipt()
        assert success, "Receipt should save and redirect to list"

        # Verify in list
        result = si.verify_in_list("", status="Received")
        si.screenshot("receipt_complete")
