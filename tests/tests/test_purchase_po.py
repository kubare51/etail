"""
Purchase Order E2E Tests

Migrated from: Sparqla/Test_Purchase/PurchasePO.py (1124 lines)
Tests: Stock order, customer order, stock repair order,
       multi-item PO, cancel flow, list verification, visual regression.
"""
import pytest
from playwright.sync_api import expect
from pages.purchase_po_page import PurchasePOPage


@pytest.mark.p1
@pytest.mark.purchase
class TestPurchasePO:
    """Purchase Order module tests."""

    def test_form_loads(self, auth_page, base_url):
        """Verify PO form loads with all required fields."""
        po = PurchasePOPage(auth_page)
        po.goto()
        po.click_add()
        expect(auth_page.locator(f"xpath={po.STOCK_ORDER}")).to_be_visible()
        expect(auth_page.locator(f"xpath={po.CREATE_ORDER_BTN}")).to_be_visible()
        po.screenshot("po_form_loaded")

    def test_form_visual(self, auth_page, base_url):
        """Visual regression: PO form."""
        po = PurchasePOPage(auth_page)
        po.goto()
        po.click_add()
        po.assert_form_layout()

    def test_stock_order_creation(self, auth_page, load_test_data, base_url):
        """
        Stock Order PO:
        1. Select Stock Order
        2. Select karigar, due date
        3. Fill product/design/sub-design/weight range
        4. Add item
        5. Submit + capture PO number
        """
        test_cases = load_test_data("purchase_po")
        if not test_cases:
            pytest.skip("No purchase PO test data")

        tc = next((t for t in test_cases if t.get("order_for") == "Stock Order"), None)
        if not tc:
            pytest.skip("No stock order test case")

        po = PurchasePOPage(auth_page)
        po.goto()
        po.click_add()

        po.select_order_for("Stock Order")
        po.fill_header(
            karigar=tc["karigar"],
            due_date=tc["due_date"],
            rate_type=tc.get("rate_type"),
            rate_value=tc.get("rate_value"),
        )

        po.fill_stock_item(
            product=tc["product"],
            design=tc["design"],
            sub_design=tc["sub_design"],
            weight_range=tc.get("weight_range"),
            size=tc.get("size"),
            pieces=tc.get("pieces", "1"),
            description=tc.get("description"),
        )

        result = po.submit()
        assert result["status"] == "pass", f"Submit failed: {result['message']}"
        assert result.get("po_no"), "Should capture PO number"

        # Verify in list
        list_result = po.verify_in_list(result["po_no"])
        assert list_result["status"] == "pass"
        po.screenshot("stock_order_complete")

    def test_customer_order_po(self, auth_page, load_test_data, base_url):
        """Customer Order PO."""
        test_cases = load_test_data("purchase_po")
        if not test_cases:
            pytest.skip("No purchase PO test data")

        tc = next((t for t in test_cases if t.get("order_for") == "Customer Order"), None)
        if not tc:
            pytest.skip("No customer order test case")

        po = PurchasePOPage(auth_page)
        po.goto()
        po.click_add()

        po.select_order_for("Customer Order")
        po.fill_header(
            karigar=tc["karigar"],
            due_date=tc["due_date"],
            order_no=tc.get("order_no"),
        )

        result = po.submit()
        assert result["status"] == "pass"
        po.screenshot("customer_order_po_complete")

    def test_multi_item_po(self, auth_page, load_test_data, base_url):
        """Multi-item Stock Order PO."""
        test_cases = load_test_data("purchase_po")
        if not test_cases:
            pytest.skip("No purchase PO test data")

        tc = next((t for t in test_cases if t.get("id", "").upper().find("MULTI") >= 0), None)
        if not tc:
            pytest.skip("No multi-item test case")

        po = PurchasePOPage(auth_page)
        po.goto()
        po.click_add()

        po.select_order_for(tc.get("order_for", "Stock Order"))
        po.fill_header(karigar=tc["karigar"], due_date=tc["due_date"])

        items = tc.get("items", [])
        po.fill_multiple_items(items)

        result = po.submit()
        assert result["status"] == "pass"
        po.screenshot("multi_item_po_complete")

    def test_cancel_flow(self, auth_page, load_test_data, base_url):
        """Cancel PO: create then cancel from list."""
        test_cases = load_test_data("purchase_po")
        if not test_cases:
            pytest.skip("No purchase PO test data")

        tc = next((t for t in test_cases if t.get("order_for") == "Stock Order"), None)
        if not tc:
            pytest.skip("No stock order for cancel test")

        po = PurchasePOPage(auth_page)
        po.goto()
        po.click_add()

        po.select_order_for("Stock Order")
        po.fill_header(karigar=tc["karigar"], due_date=tc["due_date"])
        po.fill_stock_item(
            product=tc["product"], design=tc["design"],
            sub_design=tc["sub_design"], pieces=tc.get("pieces", "1"),
        )

        result = po.submit()
        assert result["status"] == "pass"

        cancel_result = po.cancel_po(result["po_no"])
        assert cancel_result["status"] == "pass"
        po.screenshot("po_cancel_complete")

    def test_list_visual(self, auth_page, base_url):
        """Visual regression: PO list page."""
        po = PurchasePOPage(auth_page)
        po.goto()
        po.assert_list_layout()
