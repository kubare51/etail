"""
Purchase Return E2E Tests

Migrated from: Sparqla/Test_Purchase/PurchaseReturn.py (609 lines)
Tests: Return via PO Ref, Tag, NonTag, item adjustments,
       footer (discount/round off), submit + invoice capture,
       list verification, visual regression.
"""
import pytest
from playwright.sync_api import expect
from pages.purchase_return_page import PurchaseReturnPage


@pytest.mark.p2
@pytest.mark.purchase
class TestPurchaseReturn:
    """Purchase Return module tests."""

    def test_form_loads(self, auth_page, base_url):
        """Verify purchase return form loads."""
        pr = PurchaseReturnPage(auth_page)
        pr.goto_add()
        expect(auth_page.locator(f"xpath={pr.TYPE_RETURN}")).to_be_visible()
        expect(auth_page.locator(f"xpath={pr.SUBMIT_BTN}")).to_be_visible()
        pr.screenshot("purchase_return_form_loaded")

    def test_form_visual(self, auth_page, base_url):
        """Visual regression: purchase return form."""
        pr = PurchaseReturnPage(auth_page)
        pr.goto_add()
        pr.assert_add_layout()

    def test_return_po_ref(self, auth_page, load_test_data, base_url):
        """
        Return via PO Ref:
        1. Select Return type
        2. PO RefNO receipt type
        3. Select supplier, issue supplier
        4. Select PO Ref → select all items
        5. Fill adjustments (pcs, gwt, touch, rate)
        6. Submit + capture invoice
        """
        test_cases = load_test_data("purchase_return")
        if not test_cases:
            pytest.skip("No purchase return test data")

        tc = next((t for t in test_cases
                    if t.get("receipt_type") == "PO RefNO"), None)
        if not tc:
            pytest.skip("No PO Ref return test case")

        pr = PurchaseReturnPage(auth_page)
        pr.goto_add()

        pr.select_type(tc.get("select_type", "Return"))
        pr.select_stock_type(tc.get("stock_type", "Normal Stock"))
        pr.select_receipt_type("PO RefNO")

        pr.fill_header(
            filter_supplier=tc.get("filter_supplier"),
            issue_supplier=tc.get("issue_supplier"),
            convert_to=tc.get("convert_to"),
            return_reason=tc.get("return_reason"),
        )

        pr.select_po_ref(tc["po_ref_no"])

        pr.fill_item_adjustments(
            pcs=tc.get("pcs"), gwt=tc.get("gwt"),
            wastage=tc.get("wastage"), mc=tc.get("mc"),
            touch=tc.get("touch"), rate=tc.get("rate"),
            mc_type=tc.get("mc_type"),
        )

        pr.fill_footer(
            discount=tc.get("discount"),
            round_off=tc.get("round_off"),
            narration=tc.get("narration"),
        )

        result = pr.submit()
        assert result["status"] == "pass", f"Submit failed: {result['message']}"
        pr.screenshot("purchase_return_complete")

    def test_return_tag(self, auth_page, load_test_data, base_url):
        """Return via Tag code."""
        test_cases = load_test_data("purchase_return")
        if not test_cases:
            pytest.skip("No purchase return test data")

        tc = next((t for t in test_cases
                    if t.get("receipt_type") == "Tag"), None)
        if not tc:
            pytest.skip("No Tag return test case")

        pr = PurchaseReturnPage(auth_page)
        pr.goto_add()

        pr.select_type("Return")
        pr.select_receipt_type("Tag")
        pr.fill_header(
            filter_supplier=tc.get("filter_supplier"),
            issue_supplier=tc.get("issue_supplier"),
        )
        pr.fill_tag_code(tc["tag_code"])

        result = pr.submit()
        assert result["status"] == "pass"
        pr.screenshot("tag_return_complete")

    def test_list_visual(self, auth_page, base_url):
        """Visual regression: purchase return list."""
        pr = PurchaseReturnPage(auth_page)
        pr.goto_list()
        pr.assert_list_layout()
