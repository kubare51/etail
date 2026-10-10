"""
Old Metal Process E2E Tests

Migrated from: Sparqla/Test_OldMetalProcess/OldMetalProcess.py (267 lines)
Tests: Melting issue, melting receipt + modal, testing issue,
       save + capture process ID, list verification, visual regression.
"""
import pytest
from playwright.sync_api import expect
from pages.old_metal_process_page import OldMetalProcessPage


@pytest.mark.p2
@pytest.mark.inventory
class TestOldMetalProcess:
    """Old Metal Process module tests."""

    def test_form_loads(self, auth_page, base_url):
        """Verify metal process form loads."""
        omp = OldMetalProcessPage(auth_page)
        omp.goto_add()

        expect(auth_page.locator(f"xpath={omp.ISSUE_RADIO}")).to_be_visible()
        expect(auth_page.locator(f"xpath={omp.SUBMIT_BTN}")).to_be_visible()
        omp.screenshot("metal_process_form_loaded")

    def test_form_visual(self, auth_page, base_url):
        """Visual regression: metal process add page."""
        omp = OldMetalProcessPage(auth_page)
        omp.goto_add()
        omp.assert_add_layout()

    def test_melting_issue_flow(self, auth_page, load_test_data, base_url):
        """
        Melting Issue flow:
        1. Select Issue
        2. Set process type (Melting), vendor
        3. Search
        4. Select melting type, pocket
        5. Save + capture ID
        """
        test_cases = load_test_data("old_metal_process")
        if not test_cases:
            pytest.skip("No old metal process test data")

        tc = next((t for t in test_cases
                    if t.get("process_for") == "ISSUE" and t.get("process_type") == "Melting"), None)
        if not tc:
            pytest.skip("No melting issue test case")

        omp = OldMetalProcessPage(auth_page)
        omp.goto_add()

        omp.select_process_for("ISSUE")
        omp.fill_filters(process_type="Melting", vendor=tc["vendor"])
        omp.click_search()

        omp.fill_melting_issue(tc["melting_type"], tc.get("pocket_no", ""))

        if tc.get("remark"):
            omp.fill_remark(tc["remark"])

        result = omp.save()
        assert result["status"] == "pass", f"Save failed: {result['message']}"

        # Capture ID
        process_no = omp.capture_process_no(tc["vendor"])
        assert process_no, "Should capture process number"

        omp.screenshot("melting_issue_complete")

    def test_melting_receipt_flow(self, auth_page, load_test_data, base_url):
        """Melting Receipt with modal interaction."""
        test_cases = load_test_data("old_metal_process")
        if not test_cases:
            pytest.skip("No old metal process test data")

        tc = next((t for t in test_cases
                    if t.get("process_for") == "RECEIPT" and t.get("process_type") == "Melting"), None)
        if not tc:
            pytest.skip("No melting receipt test case")

        omp = OldMetalProcessPage(auth_page)
        omp.goto_add()

        omp.select_process_for("RECEIPT")
        omp.fill_filters(process_type="Melting", vendor=tc["vendor"])
        omp.click_search()

        if tc.get("process_no"):
            omp.fill_melting_receipt(
                tc["process_no"],
                modal_data={
                    "category": tc.get("modal_category"),
                    "section": tc.get("modal_section"),
                    "product": tc.get("modal_product"),
                    "design": tc.get("modal_design"),
                    "pcs": tc.get("modal_pcs"),
                    "weight": tc.get("modal_weight"),
                }
            )

        if tc.get("remark"):
            omp.fill_remark(tc["remark"])

        result = omp.save()
        assert result["status"] == "pass"
        omp.screenshot("melting_receipt_complete")

    def test_list_visual(self, auth_page, base_url):
        """Visual regression: metal process list page."""
        omp = OldMetalProcessPage(auth_page)
        omp.goto_list()
        omp.assert_list_layout()
