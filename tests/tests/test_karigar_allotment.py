"""
Karigar Allotment E2E Tests

Migrated from: Sparqla/Test_Customer/KarigarAllotment.py (170 lines)
Tests: Assign order to karigar, assign to employee, due date,
       approve/reject, visual regression.
"""
import pytest
from playwright.sync_api import expect
from pages.karigar_allotment_page import KarigarAllotmentPage


@pytest.mark.p1
@pytest.mark.orders
class TestKarigarAllotment:
    """Karigar Allotment module tests."""

    def test_page_loads(self, auth_page, base_url):
        """Verify allotment page loads."""
        ka = KarigarAllotmentPage(auth_page)
        ka.goto()

        expect(auth_page.locator(f"xpath={ka.DT_SEARCH}")).to_be_visible()
        ka.screenshot("karigar_allotment_loaded")

    def test_page_visual(self, auth_page, base_url):
        """Visual regression: karigar allotment page."""
        ka = KarigarAllotmentPage(auth_page)
        ka.goto()
        ka.assert_page_layout()

    def test_assign_to_karigar(self, auth_page, load_test_data, base_url):
        """
        Assign order to karigar:
        1. Search order
        2. Select row
        3. Assign to karigar
        4. Set due date
        5. Approve
        """
        test_cases = load_test_data("karigar_allotment")
        if not test_cases:
            pytest.skip("No karigar allotment test data")

        tc = next((t for t in test_cases if t.get("assign_to") == "karigar"), None)
        if not tc:
            pytest.skip("No karigar assign test case")

        ka = KarigarAllotmentPage(auth_page)
        ka.goto()

        ka.search_order(tc["order_no"])
        found = ka.select_order_row(tc["order_no"])
        assert found, f"Order {tc['order_no']} not found in list"

        ka.assign_to("karigar", tc["assign_name"])

        if tc.get("due_date"):
            ka.set_due_date(tc["order_no"], tc["due_date"])

        result = ka.approve()
        assert result["status"] == "pass", f"Approve failed: {result['message']}"
        ka.screenshot("karigar_assign_complete")

    def test_assign_to_employee(self, auth_page, load_test_data, base_url):
        """Assign order to employee."""
        test_cases = load_test_data("karigar_allotment")
        if not test_cases:
            pytest.skip("No karigar allotment test data")

        tc = next((t for t in test_cases if t.get("assign_to") == "employee"), None)
        if not tc:
            pytest.skip("No employee assign test case")

        ka = KarigarAllotmentPage(auth_page)
        ka.goto()

        ka.search_order(tc["order_no"])
        ka.select_order_row(tc["order_no"])
        ka.assign_to("employee", tc["assign_name"])

        result = ka.approve()
        assert result["status"] == "pass"
        ka.screenshot("employee_assign_complete")
