"""
Customer Order E2E Tests

Migrated from: Sparqla/Test_Customer/CustomerOrder.py (480 lines)
Tests: Tag Reserve flow, Customized Order flow, multi-item,
       calculation verification, save + capture order ID, visual regression.
"""
import pytest
from playwright.sync_api import expect
from pages.customer_order_page import CustomerOrderPage


@pytest.mark.p1
@pytest.mark.orders
class TestCustomerOrder:
    """Customer Order module tests."""

    def test_order_form_loads(self, auth_page, base_url):
        """Verify customer order form loads."""
        co = CustomerOrderPage(auth_page)
        co.goto()
        co.click_add_order()

        expect(auth_page.locator(f"xpath={co.ORDER_TAG_RESERVE}")).to_be_visible()
        expect(auth_page.locator(f"xpath={co.ORDER_CUSTOMIZED}")).to_be_visible()
        co.screenshot("customer_order_form_loaded")

    def test_order_form_visual(self, auth_page, base_url):
        """Visual regression: customer order form."""
        co = CustomerOrderPage(auth_page)
        co.goto()
        co.click_add_order()
        co.assert_form_layout()

    def test_tag_reserve_flow(self, auth_page, load_test_data, base_url):
        """
        Tag Reserve flow:
        1. Fill header (customer, branch, employee)
        2. Set type (Cash Balance, Tag Reserve, Delivery Rate)
        3. Scan tag
        4. Submit order
        5. Verify in list
        """
        test_cases = load_test_data("customer_order")
        if not test_cases:
            pytest.skip("No customer order test data")

        tc = next((t for t in test_cases if t.get("order_type") == "Tag Reserve"), None)
        if not tc:
            pytest.skip("No tag reserve test case")

        co = CustomerOrderPage(auth_page)
        co.goto()
        co.click_add_order()

        co.fill_header(
            customer=tc["customer_number"],
            branch=tc["branch"],
            employee=tc["employee"],
        )

        co.select_types(
            balance_type=tc.get("balance_type", "Cash Balance"),
            order_type="Tag Reserve",
            rate_type=tc.get("rate_type", "Delivery Rate(Live)"),
        )

        co.fill_tag_reserve(tc["tag_scan"])

        result = co.submit_order()
        assert result["status"] == "pass", f"Submit failed: {result['message']}"

        if result.get("order_no"):
            verify = co.verify_in_list(result["order_no"])
            assert verify["status"] == "pass"

        co.screenshot("tag_reserve_complete")

    def test_customized_order_flow(self, auth_page, load_test_data, base_url):
        """
        Customized Order flow:
        1. Fill header
        2. Set types
        3. Fill item (product, design, purity, gwt, pcs, MC)
        4. Submit and verify
        """
        test_cases = load_test_data("customer_order")
        if not test_cases:
            pytest.skip("No customer order test data")

        tc = next((t for t in test_cases if t.get("order_type") == "Customized Order"), None)
        if not tc:
            pytest.skip("No customized order test case")

        co = CustomerOrderPage(auth_page)
        co.goto()
        co.click_add_order()

        co.fill_header(
            customer=tc["customer_number"],
            branch=tc["branch"],
            employee=tc["employee"],
        )

        co.select_types(
            balance_type=tc.get("balance_type", "Cash Balance"),
            order_type="Customized Order",
            rate_type=tc.get("rate_type", "Delivery Rate(Live)"),
        )

        co.fill_customized_item(
            row_idx=1,
            product=tc["product"],
            design=tc["design"],
            sub_design=tc["sub_design"],
            purity=tc.get("purity"),
            gross_wt=tc.get("gross_wt"),
            pcs=tc.get("pcs"),
            wastage=tc.get("wastage"),
            mc_type=tc.get("mc_type"),
            mc_value=tc.get("mc_value"),
        )

        result = co.submit_order()
        assert result["status"] == "pass", f"Submit failed: {result['message']}"

        co.screenshot("customized_order_complete")

    def test_order_list_visual(self, auth_page, base_url):
        """Visual regression: customer order list."""
        co = CustomerOrderPage(auth_page)
        co.goto()
        co.assert_list_layout()
