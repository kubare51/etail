"""
Vendor Registration E2E Tests

Migrated from: Sparqla/Test_vendor/Vendor.py (526 lines)
Tests: Company vendor, individual vendor, mandatory fields, TCS/TDS,
       address flow, save + verification, visual regression.
"""
import pytest
from playwright.sync_api import expect
from pages.vendor_page import VendorPage


@pytest.mark.p1
@pytest.mark.masters
class TestVendor:
    """Vendor Registration module tests."""

    def test_vendor_form_loads(self, auth_page, base_url):
        """Verify vendor form loads with all required fields."""
        vp = VendorPage(auth_page)
        vp.goto()
        vp.click_add()

        expect(auth_page.locator(f"xpath={vp.FIRST_NAME}")).to_be_visible()
        expect(auth_page.locator(f"xpath={vp.MOBILE}")).to_be_visible()
        vp.screenshot("vendor_form_loaded")

    def test_vendor_form_visual(self, auth_page, base_url):
        """Visual regression: vendor form."""
        vp = VendorPage(auth_page)
        vp.goto()
        vp.click_add()
        vp.assert_form_layout()

    def test_company_vendor_registration(self, auth_page, load_test_data, base_url):
        """
        Full company vendor registration:
        1. Select Company type
        2. Fill used for, TCS/TDS, financial
        3. Fill personal + company details
        4. Fill address
        5. Save
        """
        test_cases = load_test_data("vendor")
        if not test_cases:
            pytest.skip("No vendor test data")

        tc = next((t for t in test_cases if t.get("user_type") == "company"), None)
        if not tc:
            pytest.skip("No company vendor test case")

        vp = VendorPage(auth_page)
        vp.goto()
        vp.click_add()

        vp.select_user_type(tc["user_type"])
        vp.fill_used_for(tc["used_for"])
        vp.fill_tax_details(
            tcs=tc.get("tcs", "No"), tcs_pct=tc.get("tcs_pct"),
            tds=tc.get("tds", "No"), tds_pct=tc.get("tds_pct"),
        )
        vp.fill_financial(tc["opening_balance"], tc["financial_year"])
        vp.fill_personal(
            first_name=tc["first_name"], last_name=tc["last_name"],
            mobile=tc["mobile"], email=tc.get("email"),
            phone=tc.get("phone"), username=tc.get("username"),
            password=tc.get("password"),
        )
        vp.fill_company(tc["company_name"], tc.get("gst_number"))
        vp.fill_address(
            addr1=tc["address1"], addr2=tc.get("address2"),
            country=tc.get("country"), state=tc.get("state"),
            city=tc.get("city"), pincode=tc.get("pincode"),
        )
        if tc.get("remarks"):
            vp.fill_remarks(tc["remarks"])

        result = vp.save()
        assert result["status"] == "pass", f"Save failed: {result['message']}"
        vp.screenshot("company_vendor_complete")

    def test_individual_vendor_registration(self, auth_page, load_test_data, base_url):
        """Individual vendor registration."""
        test_cases = load_test_data("vendor")
        if not test_cases:
            pytest.skip("No vendor test data")

        tc = next((t for t in test_cases if t.get("user_type") == "individual"), None)
        if not tc:
            pytest.skip("No individual vendor test case")

        vp = VendorPage(auth_page)
        vp.goto()
        vp.click_add()

        vp.select_user_type("individual")
        vp.fill_used_for(tc["used_for"])
        vp.fill_financial(tc["opening_balance"], tc["financial_year"])
        vp.fill_personal(
            first_name=tc["first_name"], last_name=tc["last_name"],
            mobile=tc["mobile"], email=tc.get("email"),
        )
        vp.fill_address(addr1=tc["address1"])

        result = vp.save()
        assert result["status"] == "pass"
        vp.screenshot("individual_vendor_complete")

    def test_vendor_list_visual(self, auth_page, base_url):
        """Visual regression: vendor list page."""
        vp = VendorPage(auth_page)
        vp.goto()
        vp.assert_list_layout()
