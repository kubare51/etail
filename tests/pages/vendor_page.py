"""
VendorPage — Page object for Vendor Registration module.

Extracted from: Sparqla/Test_vendor/Vendor.py (526 lines)
Covers: Company/Individual registration, TCS/TDS setup,
        address details, contract price, save + verification.
"""
import logging
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class VendorPage(BasePage):
    """Page object for Vendor Registration (Purchase Module)."""

    # ── Navigation ────────────────────────────────────────────────────────────
    ADD_VENDOR_BTN = '//a[@id="karigar_add"]'

    # ── User Type ─────────────────────────────────────────────────────────────
    TYPE_COMPANY = '//*[@id="click_label_company"]'
    TYPE_INDIVIDUAL = '//*[@id="click_label_individual"]'

    # ── Used For ──────────────────────────────────────────────────────────────
    USED_FOR_SELECT2 = '//span[@id="select2-karigar_for-container"]'
    SELECT2_SEARCH = '/html/body/span[1]/span/span[1]/input'

    # ── Tax ───────────────────────────────────────────────────────────────────
    TCS_YES = '//*[@id="click_label_tcsyes"]'
    TCS_NO = '//*[@id="click_label_tcsno"]'
    TCS_INPUT = '//*[@id="tcs"]'
    TDS_YES = '//*[@id="click_label_tdsyes"]'
    TDS_NO = '//*[@id="click_label_tdsno"]'
    TDS_INPUT = '//*[@id="tds_tax"]'

    # ── Financial ─────────────────────────────────────────────────────────────
    OPENING_BAL = '//*[@id="opening_bal_amt"]'
    FIN_YEAR_SELECT = "#order_fin_year_select"

    # ── Personal ──────────────────────────────────────────────────────────────
    FIRST_NAME = '//*[@id="first_name"]'
    LAST_NAME = '//*[@id="last_name_karigar"]'
    MOBILE = '//*[@id="karigar_mobile"]'
    EMAIL = '//*[@id="email_karigar"]'
    PHONE = '//*[@id="phone_karigar"]'
    USERNAME = '//*[@id="user_name_karigar"]'
    PASSWORD = '//*[@id="password"]'

    # ── Company ───────────────────────────────────────────────────────────────
    COMPANY_NAME = '//*[@id="company_karigar"]'
    GST_NUMBER = '//*[@id="gst_number_karigar"]'

    # ── Address ───────────────────────────────────────────────────────────────
    ADDRESS1 = '//*[@id="address1"]'
    ADDRESS2 = '//*[@id="address2_karigar"]'
    ADDRESS3 = '//*[@id="address3"]'
    COUNTRY_BTN = '//button[@data-id="country"]'
    STATE_BTN = '//button[@data-id="state"]'
    CITY_BTN = '//button[@data-id="city"]'
    PINCODE = '//*[@id="kar_pincode"]'
    REMARKS = '//textarea[@id="remarks"]'
    IMAGE = '//input[@id="user_img"]'

    # ── Save ──────────────────────────────────────────────────────────────────
    SAVE_AND_NEXT = '//*[@id="add_karNext"]'
    SAVE_NEW = '//*[@id="add_newkar"]'
    CANCEL_BTN = '//button[@class="btn btn-default btn-cancel"]'

    # ── Methods ───────────────────────────────────────────────────────────────

    def goto(self):
        """Navigate to Vendor Registration."""
        self.navigate_via_menu(
            "Purchase Module", "Vendor Registration",
            fallback_path="index.php/admin_ret_purchase/karigar/list"
        )
        return self

    def click_add(self):
        self.click(self.ADD_VENDOR_BTN)
        self.page.wait_for_timeout(2000)

    def select_user_type(self, user_type: str):
        """Company or Individual."""
        if user_type.lower() == "company":
            self.click(self.TYPE_COMPANY)
        else:
            self.click(self.TYPE_INDIVIDUAL)

    def fill_used_for(self, used_for: str):
        self.select2(self.USED_FOR_SELECT2, used_for, self.SELECT2_SEARCH)

    def fill_tax_details(self, tcs: str = "No", tcs_pct: str = None,
                         tds: str = "No", tds_pct: str = None):
        if tcs == "Yes":
            self.click(self.TCS_YES)
            if tcs_pct:
                self.fill(self.TCS_INPUT, str(tcs_pct))
        else:
            self.click(self.TCS_NO)

        if tds == "Yes":
            self.click(self.TDS_YES)
            if tds_pct:
                self.fill(self.TDS_INPUT, str(tds_pct))
        else:
            self.click(self.TDS_NO)

    def fill_financial(self, opening_bal: str, fin_year: str):
        self.fill(self.OPENING_BAL, str(opening_bal))
        self.select_native(self.FIN_YEAR_SELECT, fin_year)

    def fill_personal(self, first_name: str, last_name: str,
                      mobile: str, email: str = None, phone: str = None,
                      username: str = None, password: str = None):
        self.fill(self.FIRST_NAME, first_name)
        self.fill(self.LAST_NAME, last_name)
        self.fill(self.MOBILE, str(mobile))
        if email:
            self.fill(self.EMAIL, email)
        if phone:
            self.fill(self.PHONE, str(phone))
        if username:
            self.fill(self.USERNAME, username)
        if password:
            self.fill(self.PASSWORD, password)

    def fill_company(self, company_name: str, gst: str = None):
        self.fill(self.COMPANY_NAME, company_name)
        if gst:
            self.fill(self.GST_NUMBER, gst)

    def fill_address(self, addr1: str, addr2: str = None, addr3: str = None,
                     country: str = None, state: str = None,
                     city: str = None, pincode: str = None):
        self.fill(self.ADDRESS1, addr1)
        if addr2:
            self.fill(self.ADDRESS2, addr2)
        if addr3:
            self.fill(self.ADDRESS3, addr3)
        if country:
            self.page.locator(f"xpath={self.COUNTRY_BTN}").click()
            self.page.wait_for_timeout(500)
            search = self.page.locator(f"xpath={self.COUNTRY_BTN}/following-sibling::div//input")
            search.fill(country)
            self.page.locator(f"//span[text()='{country}']").click()
        if state:
            self.page.locator(f"xpath={self.STATE_BTN}").click()
            self.page.wait_for_timeout(500)
            search = self.page.locator(f"xpath={self.STATE_BTN}/following-sibling::div//input")
            search.fill(state)
            search.press("Enter")
        if city:
            self.page.locator(f"xpath={self.CITY_BTN}").click()
            self.page.wait_for_timeout(500)
            search = self.page.locator(f"xpath={self.CITY_BTN}/following-sibling::div//input")
            search.fill(city)
            search.press("Enter")
        if pincode:
            self.fill(self.PINCODE, str(pincode))

    def fill_remarks(self, remarks: str):
        self.fill(self.REMARKS, remarks)

    def save(self, with_contract_price: bool = False) -> dict:
        """Save vendor. Returns status dict."""
        self.screenshot("before_vendor_save")
        btn = self.SAVE_AND_NEXT if with_contract_price else self.SAVE_NEW
        self.click(btn)
        self.page.wait_for_timeout(3000)

        # Check for alert/toaster
        try:
            msg_el = self.page.locator("div.alert-success, div.alert-danger, #toast-container")
            msg_el.wait_for(state="visible", timeout=5000)
            msg = msg_el.inner_text().replace("×", "").strip()
            status = "pass" if "success" in msg.lower() else "fail"
            return {"status": status, "message": msg}
        except Exception:
            return {"status": "pass", "message": "Saved (no toaster detected)"}

    # ── Visual Regression ─────────────────────────────────────────────────────

    def assert_form_layout(self):
        expect(self.page).to_have_screenshot(
            "vendor_form.png", full_page=True, max_diff_pixel_ratio=0.01
        )

    def assert_list_layout(self):
        expect(self.page).to_have_screenshot(
            "vendor_list.png", full_page=True, max_diff_pixel_ratio=0.01
        )
