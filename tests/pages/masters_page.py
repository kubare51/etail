"""
MastersPage — Page objects for Master Data modules.

Extracted from: Sparqla/Test_master/Metal.py, Category.py, Product.py, Design.py
Pattern: Each master follows Create → Edit → Delete → Verify CRUD cycle.
"""
import logging
from playwright.sync_api import Page, expect
from pages.base_page import BasePage

logger = logging.getLogger(__name__)


class MetalPage(BasePage):
    """Metal master page object."""

    # Navigation
    MENU_CATALOG = "Retail Catalog"
    MENU_METAL = "//span[normalize-space(text())='Metal']"

    # Create
    ADD_METAL_BTN = "#add_metal"
    METAL_NAME = "#metal_name"
    METAL_CODE = "#metal_code"
    TAX_GROUP_SELECT2 = "#select2-tgrp_sel-container"
    SAVE_BTN = "#add_newmetal"
    SUCCESS_ALERT = '//*[@id="chit_alert"]'

    # Edit
    DT_SEARCH = "//input[@type='search']"
    EDIT_BTN = "//a[@id='edit']/i"
    EDIT_METAL_NAME = "#ed_metal_name"
    EDIT_METAL_CODE = "#ed_metal_code"
    UPDATE_BTN = "#update_metal"
    UPDATE_SUCCESS = '//div[@class="alert alert-success alert-dismissable"]'

    # Delete
    DELETE_BTN = "//table[@id='metal_list']/tbody/tr/td[6]/a[2]/i"
    CONFIRM_DELETE = "Delete"

    # List table
    METAL_TABLE = "#metal_list"

    def goto(self):
        """Navigate to Metal master."""
        self.navigate_via_menu(
            "Retail Catalog", None,
            fallback_path="index.php/admin_ret_metal/metal/index"
        )
        self.page.wait_for_timeout(500)
        self.click(self.MENU_METAL)
        return self

    def create(self, name: str, code: str, tax_group: str) -> dict:
        """Create a new metal. Returns {status, message}."""
        self.page.reload()
        self.page.wait_for_load_state("networkidle")
        self.click(self.ADD_METAL_BTN)
        self.fill(self.METAL_NAME, name)
        self.fill(self.METAL_CODE, code)

        # Tax group (Select2 dropdown inside modal)
        self.click(self.TAX_GROUP_SELECT2)
        self.page.locator(f"//li[normalize-space()='{tax_group}']").click()

        self.click(self.SAVE_BTN)
        self.page.wait_for_timeout(2000)

        msg = self._get_alert_text()
        success = "added successfully" in msg.lower()
        return {"status": "pass" if success else "fail", "message": msg}

    def edit(self, search_name: str, new_code: str) -> dict:
        """Edit a metal record. Returns {status, message}."""
        self.page.reload()
        self.page.wait_for_load_state("networkidle")
        self.page.wait_for_timeout(1000)

        self.fill(self.DT_SEARCH, search_name)
        self.page.wait_for_timeout(1000)
        self.click(self.EDIT_BTN)

        # Verify name field has value
        existing = self.get_value(self.EDIT_METAL_NAME)
        if not existing:
            self.fill(self.EDIT_METAL_NAME, search_name)

        locator = self.page.locator(self.EDIT_METAL_CODE)
        locator.clear()
        locator.fill(new_code)
        self.click(self.UPDATE_BTN)
        self.page.wait_for_timeout(2000)

        msg = self._get_success_text()
        success = "modified successfully" in msg.lower()
        return {"status": "pass" if success else "fail", "message": msg}

    def delete(self, search_name: str) -> dict:
        """Delete a metal record. Returns {status, message}."""
        self.page.wait_for_timeout(1000)
        self.fill(self.DT_SEARCH, search_name)
        self.page.wait_for_timeout(1000)
        self.click(self.DELETE_BTN)

        # Confirm delete
        self.page.get_by_text("Delete", exact=True).click()
        self.page.wait_for_timeout(2000)

        msg = self._get_success_text()
        return {"status": "pass", "message": msg}

    def verify_in_list(self, name: str) -> bool:
        """Verify metal appears in the DataTable list."""
        return self.datatable_search("metal_list", name)

    def assert_list_layout(self):
        """Visual regression: verify metal list table layout."""
        table = self.page.locator(self.METAL_TABLE)
        expect(table).to_have_screenshot("metal_list_table.png")

    def _get_alert_text(self) -> str:
        try:
            el = self.page.locator(f"xpath={self.SUCCESS_ALERT}")
            el.wait_for(state="visible", timeout=5000)
            return el.inner_text().replace("×", "").replace("\n", " ").strip()
        except Exception:
            return ""

    def _get_success_text(self) -> str:
        try:
            el = self.page.locator(f"xpath={self.UPDATE_SUCCESS}")
            el.wait_for(state="visible", timeout=5000)
            return el.inner_text().replace("×", "").replace("\n", " ").strip()
        except Exception:
            return ""


class CategoryPage(BasePage):
    """Category master page object."""

    ADD_BTN = "#add_category"
    CATEGORY_NAME = "#category_name"
    HSN_CODE = "#hsn_code"
    SHORT_CODE = "#cat_code"
    CATEGORY_TYPES = {
        "Ornament": "#ornament",
        "Bullion": "#bullion",
        "Stone": "#stone",
        "Alloy": "#alloy",
    }
    MULTI_METAL_YES = "#multimetal_yes"
    MULTI_METAL_NO = "#multimetal_no"
    METAL_SELECT2 = "//span[@id='select2-metal_category-container']/span"
    TAX_GROUP_SELECT2 = "#select2-tgrp_sel-container"
    PURITY_INPUT = '(//input[@class="select2-search__field"])[1]'
    DESCRIPTION = "#category_desc"
    SAVE_BTN = "#add_newcategory"
    ALERT = '//*[@id="chit_alert"]/div'

    # Edit
    DT_SEARCH = "(//input[@type='search'])[1]"
    EDIT_BTN = "//a[@id='edit']/i"
    EDIT_NAME = "#ed_category_name"
    UPDATE_BTN = "#update_category"

    # Delete
    DELETE_BTN = "//a[@class='btn btn-danger btn-del']/i"
    CONFIRM_DELETE = "//a[@class='btn btn-danger btn-confirm']"

    CATEGORY_TABLE = "#category_list"

    def goto(self):
        """Navigate to Category master."""
        self.navigate_via_menu(
            "Retail Catalog", None,
            fallback_path="index.php/admin_ret_category/category/index"
        )
        self.page.get_by_text("Category", exact=False).first.click()
        return self

    def create(self, name: str, hsn: str, code: str, cat_type: str,
               multi_metal: str, metal: str, tax_group: str,
               purities: str = None, description: str = None) -> dict:
        """Create a new category."""
        self.page.reload()
        self.page.wait_for_load_state("networkidle")
        self.click(self.ADD_BTN)

        self.fill(self.CATEGORY_NAME, name)
        self.fill(self.HSN_CODE, str(hsn))
        self.fill(self.SHORT_CODE, code)

        # Category type radio
        if cat_type in self.CATEGORY_TYPES:
            self.click(self.CATEGORY_TYPES[cat_type])

        # Multi-metal
        if multi_metal == "Yes":
            self.click(self.MULTI_METAL_YES)
        else:
            self.click(self.MULTI_METAL_NO)

        self.page.wait_for_timeout(1000)

        # Metal dropdown
        self.click(self.METAL_SELECT2)
        self.page.locator(f"//li[normalize-space()='{metal}']").click()

        # Tax group
        self.click(self.TAX_GROUP_SELECT2)
        self.page.locator(f"//li[normalize-space()='{tax_group}']").click()

        # Purity (comma-separated)
        if purities:
            purity_list = [p.strip() for p in purities.split(",")]
            self.click(self.PURITY_INPUT)
            for p in purity_list:
                purity_input = self.page.locator(f"xpath={self.PURITY_INPUT}")
                purity_input.fill(p)
                purity_input.press("Enter")

        if description:
            self.fill(self.DESCRIPTION, description)

        self.click(self.SAVE_BTN)
        self.page.wait_for_timeout(2000)

        msg = self._get_alert_text()
        success = "added successfully" in msg.lower()
        return {"status": "pass" if success else "fail", "message": msg}

    def edit(self, search_name: str, new_name: str) -> dict:
        """Edit a category record."""
        self.fill(self.DT_SEARCH, search_name)
        self.page.wait_for_timeout(1000)
        self.click(self.EDIT_BTN)
        self.page.locator(self.EDIT_NAME).clear()
        self.fill(self.EDIT_NAME, new_name)
        self.click(self.UPDATE_BTN)
        self.page.wait_for_timeout(2000)

        msg = self._get_success_text()
        success = "modified successfully" in msg.lower()
        return {"status": "pass" if success else "fail", "message": msg}

    def delete(self, search_name: str) -> dict:
        """Delete a category record."""
        self.page.wait_for_timeout(1000)
        self.fill("//input[@type='search']", search_name)
        self.page.wait_for_timeout(1000)
        self.click(self.DELETE_BTN)
        self.click(self.CONFIRM_DELETE)
        self.page.wait_for_timeout(2000)

        msg = self._get_success_text()
        return {"status": "pass", "message": msg}

    def assert_list_layout(self):
        """Visual regression: verify category list."""
        expect(self.page).to_have_screenshot("category_list.png", full_page=True, max_diff_pixel_ratio=0.01)

    def _get_alert_text(self) -> str:
        try:
            el = self.page.locator(f"xpath={self.ALERT}")
            el.wait_for(state="visible", timeout=5000)
            return el.inner_text().replace("×", "").replace("\n", " ").strip()
        except Exception:
            return ""

    def _get_success_text(self) -> str:
        try:
            el = self.page.locator("div.alert-success")
            el.wait_for(state="visible", timeout=5000)
            return el.inner_text().replace("×", "").replace("\n", " ").strip()
        except Exception:
            return ""


class ProductPage(BasePage):
    """Product master page object."""

    ADD_BTN = "#add_product"
    PRODUCT_NAME = "#prod_name"
    SHORT_CODE = "#prod_code"
    CATEGORY_SELECT2 = "#select2-cat_sel-container"
    SAVE_BTN = "#add_newproduct"
    ALERT = '//*[@id="chit_alert"]/div'

    # Edit
    DT_SEARCH = "//input[@type='search']"
    EDIT_BTN = "//a[@id='edit']/i"
    EDIT_NAME = "#ed_prod_name"
    UPDATE_BTN = "#update_product"

    # Delete
    DELETE_BTN = "//a[@class='btn btn-danger btn-del']/i"
    CONFIRM_DELETE = "//a[@class='btn btn-danger btn-confirm']"

    PRODUCT_TABLE = "#product_list"

    def goto(self):
        """Navigate to Product master."""
        self.navigate_via_menu(
            "Retail Catalog", None,
            fallback_path="index.php/admin_ret_product/product/index"
        )
        self.page.get_by_text("Product", exact=False).first.click()
        return self

    def create(self, name: str, code: str, category: str) -> dict:
        """Create a new product."""
        self.page.reload()
        self.page.wait_for_load_state("networkidle")
        self.click(self.ADD_BTN)
        self.fill(self.PRODUCT_NAME, name)
        self.fill(self.SHORT_CODE, code)

        self.click(self.CATEGORY_SELECT2)
        self.page.locator(f"//li[normalize-space()='{category}']").click()

        self.click(self.SAVE_BTN)
        self.page.wait_for_timeout(2000)

        msg = self._get_alert_text()
        success = "added successfully" in msg.lower()
        return {"status": "pass" if success else "fail", "message": msg}

    def edit(self, search_name: str, new_name: str) -> dict:
        """Edit a product."""
        self.fill(self.DT_SEARCH, search_name)
        self.page.wait_for_timeout(1000)
        self.click(self.EDIT_BTN)
        self.page.locator(self.EDIT_NAME).clear()
        self.fill(self.EDIT_NAME, new_name)
        self.click(self.UPDATE_BTN)
        self.page.wait_for_timeout(2000)
        msg = self._get_success_text()
        success = "modified successfully" in msg.lower()
        return {"status": "pass" if success else "fail", "message": msg}

    def delete(self, search_name: str) -> dict:
        """Delete a product."""
        self.page.wait_for_timeout(1000)
        self.fill(self.DT_SEARCH, search_name)
        self.page.wait_for_timeout(1000)
        self.click(self.DELETE_BTN)
        self.page.get_by_text("Delete", exact=True).click()
        self.page.wait_for_timeout(2000)
        msg = self._get_success_text()
        return {"status": "pass", "message": msg}

    def _get_alert_text(self) -> str:
        try:
            el = self.page.locator(f"xpath={self.ALERT}")
            el.wait_for(state="visible", timeout=5000)
            return el.inner_text().replace("×", "").replace("\n", " ").strip()
        except Exception:
            return ""

    def _get_success_text(self) -> str:
        try:
            el = self.page.locator("div.alert-success")
            el.wait_for(state="visible", timeout=5000)
            return el.inner_text().replace("×", "").replace("\n", " ").strip()
        except Exception:
            return ""


class DesignPage(BasePage):
    """Design master page object."""

    ADD_BTN = "#add_design"
    DESIGN_NAME = "#design_name"
    DESIGN_CODE = "#design_code"
    PRODUCT_SELECT2 = "#select2-product_sel-container"
    SAVE_BTN = "#add_newdesign"
    ALERT = '//*[@id="chit_alert"]/div'

    DT_SEARCH = "//input[@type='search']"
    EDIT_BTN = "//a[@id='edit']/i"
    EDIT_NAME = "#ed_design_name"
    UPDATE_BTN = "#update_design"
    DELETE_BTN = "//a[@class='btn btn-danger btn-del']/i"
    CONFIRM_DELETE = "//a[@class='btn btn-danger btn-confirm']"

    def goto(self):
        """Navigate to Design master."""
        self.navigate_via_menu(
            "Retail Catalog", None,
            fallback_path="index.php/admin_ret_design/design/index"
        )
        self.page.get_by_text("Design", exact=False).first.click()
        return self

    def create(self, name: str, code: str, product: str) -> dict:
        """Create a new design."""
        self.page.reload()
        self.page.wait_for_load_state("networkidle")
        self.click(self.ADD_BTN)
        self.fill(self.DESIGN_NAME, name)
        self.fill(self.DESIGN_CODE, code)

        self.click(self.PRODUCT_SELECT2)
        self.page.locator(f"//li[normalize-space()='{product}']").click()

        self.click(self.SAVE_BTN)
        self.page.wait_for_timeout(2000)

        msg = self._get_alert_text()
        success = "added successfully" in msg.lower()
        return {"status": "pass" if success else "fail", "message": msg}

    def edit(self, search_name: str, new_name: str) -> dict:
        """Edit a design."""
        self.fill(self.DT_SEARCH, search_name)
        self.page.wait_for_timeout(1000)
        self.click(self.EDIT_BTN)
        self.page.locator(self.EDIT_NAME).clear()
        self.fill(self.EDIT_NAME, new_name)
        self.click(self.UPDATE_BTN)
        self.page.wait_for_timeout(2000)
        msg = self._get_success_text()
        success = "modified successfully" in msg.lower()
        return {"status": "pass" if success else "fail", "message": msg}

    def delete(self, search_name: str) -> dict:
        """Delete a design."""
        self.page.wait_for_timeout(1000)
        self.fill(self.DT_SEARCH, search_name)
        self.page.wait_for_timeout(1000)
        self.click(self.DELETE_BTN)
        self.page.get_by_text("Delete", exact=True).click()
        self.page.wait_for_timeout(2000)
        msg = self._get_success_text()
        return {"status": "pass", "message": msg}

    def _get_alert_text(self) -> str:
        try:
            el = self.page.locator(f"xpath={self.ALERT}")
            el.wait_for(state="visible", timeout=5000)
            return el.inner_text().replace("×", "").replace("\n", " ").strip()
        except Exception:
            return ""

    def _get_success_text(self) -> str:
        try:
            el = self.page.locator("div.alert-success")
            el.wait_for(state="visible", timeout=5000)
            return el.inner_text().replace("×", "").replace("\n", " ").strip()
        except Exception:
            return ""
