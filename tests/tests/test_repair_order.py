"""
Repair Order E2E Tests

Migrated from: Sparqla/Test_RepairOrder/RepairOrder.py (391 lines)
Tests: Customer repair, stock repair, multi-item, stone modal,
       save + capture ID, list verification, visual regression.
"""
import pytest
from playwright.sync_api import expect
from pages.repair_order_page import RepairOrderPage


@pytest.mark.p1
@pytest.mark.orders
class TestRepairOrder:
    """Repair Order module tests."""

    def test_repair_form_loads(self, auth_page, base_url):
        """Verify repair order form loads."""
        ro = RepairOrderPage(auth_page)
        ro.goto()
        ro.start_order("Customer", "In House")

        expect(auth_page.locator(ro.TYPE_CUSTOMER)).to_be_checked()
        ro.screenshot("repair_form_loaded")

    def test_repair_form_visual(self, auth_page, base_url):
        """Visual regression: repair order form."""
        ro = RepairOrderPage(auth_page)
        ro.goto()
        ro.start_order("Customer", "In House")
        ro.assert_form_layout()

    def test_customer_repair_flow(self, auth_page, load_test_data, base_url):
        """
        Full customer repair flow:
        1. Start order (Customer, In House)
        2. Fill header (branch, employee, customer)
        3. Fill items (metal, product, design, weight, pcs, repair type)
        4. Save and capture ID
        5. Verify in list
        """
        test_cases = load_test_data("repair_order")
        if not test_cases:
            pytest.skip("No repair order test data")

        tc = next((t for t in test_cases if t.get("order_type") == "Customer"), None)
        if not tc:
            pytest.skip("No customer repair test case")

        ro = RepairOrderPage(auth_page)
        ro.goto()
        ro.start_order(
            order_type=tc["order_type"],
            work_at=tc.get("work_at", "In House"),
        )

        ro.fill_header(
            branch=tc.get("order_from"),
            employee=tc.get("employee"),
            customer=tc.get("customer"),
        )

        # Build items list
        items = []
        metals = str(tc.get("metal", "")).split("|")
        products = str(tc.get("product", "")).split("|")
        designs = str(tc.get("design", "")).split("|")
        sub_designs = str(tc.get("sub_design", "")).split("|")
        gross_wts = str(tc.get("gross_wt", "")).split("|")
        pcs_list = str(tc.get("pcs", "")).split("|")
        due_dates = str(tc.get("due_date", "")).split("|")
        repair_types = str(tc.get("repair_type", "")).split("|")

        for i in range(len(products)):
            items.append({
                "metal": metals[i].strip() if i < len(metals) else "",
                "product": products[i].strip(),
                "design": designs[i].strip() if i < len(designs) else "",
                "sub_design": sub_designs[i].strip() if i < len(sub_designs) else "",
                "gross_wt": gross_wts[i].strip() if i < len(gross_wts) else "",
                "pcs": pcs_list[i].strip() if i < len(pcs_list) else "1",
                "due_date": due_dates[i].strip() if i < len(due_dates) else "",
                "repair_type": repair_types[i].strip() if i < len(repair_types) else "",
            })

        ro.fill_customer_items(items)

        # Save
        order_id = ro.save_order()
        assert order_id, "Should capture repair order ID"

        # Verify
        result = ro.verify_in_list(order_id)
        assert result["status"] == "pass", f"Verification failed: {result['message']}"

        ro.screenshot("customer_repair_complete")

    def test_stock_repair_flow(self, auth_page, load_test_data, base_url):
        """Stock repair — scan tag, set repair type, save."""
        test_cases = load_test_data("repair_order")
        if not test_cases:
            pytest.skip("No repair order test data")

        tc = next((t for t in test_cases if t.get("order_type") == "Stock"), None)
        if not tc:
            pytest.skip("No stock repair test case")

        ro = RepairOrderPage(auth_page)
        ro.goto()
        ro.start_order("Stock", tc.get("work_at", "In House"))

        ro.fill_header(
            branch=tc.get("order_from"),
            employee=tc.get("employee"),
        )

        # Tag scan
        tags = str(tc.get("tag_code", "")).split("|")
        for tag in tags:
            if tag.strip():
                ro.fill_stock_tag(tag.strip())

        order_id = ro.save_order()
        assert order_id, "Should capture stock repair order ID"

        ro.screenshot("stock_repair_complete")

    def test_repair_list_visual(self, auth_page, base_url):
        """Visual regression: repair order list page."""
        ro = RepairOrderPage(auth_page)
        ro.goto()
        ro.assert_list_layout()
