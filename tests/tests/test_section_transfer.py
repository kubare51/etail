"""
Section Transfer E2E Tests

Migrated from: Sparqla/Test_SectionTransfer/SectionTransfer.py (555 lines)
Tests: Tagged transfer, non-tagged packet transfer, partial transfer,
       filter verification, visual regression.
"""
import pytest
from playwright.sync_api import expect
from pages.section_transfer_page import SectionTransferPage


@pytest.mark.p1
@pytest.mark.inventory
class TestSectionTransfer:
    """Section Transfer module tests."""

    def test_page_loads(self, auth_page, base_url):
        """Verify section transfer page loads."""
        st = SectionTransferPage(auth_page)
        st.goto()

        expect(auth_page.locator(f"xpath={st.TYPE_TAGGED}")).to_be_visible()
        expect(auth_page.locator(f"xpath={st.TRANSFER_BTN}")).to_be_visible()
        st.screenshot("section_transfer_loaded")

    def test_page_visual(self, auth_page, base_url):
        """Visual regression: section transfer page."""
        st = SectionTransferPage(auth_page)
        st.goto()
        st.assert_page_layout()

    def test_tagged_transfer_flow(self, auth_page, load_test_data, base_url):
        """
        Tagged section transfer:
        1. Select Tagged type
        2. Fill filters (branch, product, from section)
        3. Search by tag code
        4. Select row checkbox
        5. Select to section
        6. Transfer + verify
        """
        test_cases = load_test_data("section_transfer")
        if not test_cases:
            pytest.skip("No section transfer test data")

        tc = next((t for t in test_cases if t.get("type") == "Tagged"), None)
        if not tc:
            pytest.skip("No tagged transfer test case")

        st = SectionTransferPage(auth_page)
        st.goto()

        st.select_type("Tagged")
        st.fill_filters(
            branch=tc.get("branch"),
            product=tc.get("product"),
            from_section=tc.get("from_section"),
        )

        st.search_tagged(
            tag_code=tc.get("tag_code"),
            old_tag_id=tc.get("old_tag_id"),
            est_no=tc.get("estimation_no"),
        )

        st.select_first_row()
        st.select_to_section(tc["to_section"])

        result = st.transfer()
        assert result["status"] == "pass", f"Transfer failed: {result['message']}"
        st.screenshot("tagged_transfer_complete")

    def test_nontag_packet_transfer(self, auth_page, load_test_data, base_url):
        """Non-tagged packet scan transfer."""
        test_cases = load_test_data("section_transfer")
        if not test_cases:
            pytest.skip("No section transfer test data")

        tc = next((t for t in test_cases if t.get("type") == "Non Tagged"), None)
        if not tc:
            pytest.skip("No non-tagged transfer test case")

        st = SectionTransferPage(auth_page)
        st.goto()

        st.select_type("Non Tagged")
        st.fill_filters(
            branch=tc.get("branch"),
            product=tc.get("product"),
            from_section=tc.get("from_section"),
        )

        scanned = st.scan_packet(tc["packet_code"])
        assert scanned > 0, "At least one packet should be scanned"

        # Partial transfer
        if tc.get("pcs") or tc.get("gwt"):
            st.edit_packet_qty(pcs=tc.get("pcs"), gwt=tc.get("gwt"))

        st.select_to_section(tc["to_section"])

        result = st.transfer()
        assert result["status"] == "pass", f"Transfer failed: {result['message']}"
        st.screenshot("nontag_transfer_complete")
