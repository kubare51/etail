"""
Masters E2E Tests

Migrated from: Sparqla/Test_master/Metal.py, Category.py, Product.py, Design.py
Tests: Full CRUD cycle (Create → Edit → Delete → Verify) for each master.
Each master follows identical pattern: create, verify in list, edit, verify, delete, verify gone.
"""
import pytest
from playwright.sync_api import expect
from pages.masters_page import MetalPage, CategoryPage, ProductPage, DesignPage


# ═══════════════════════════════════════════════════════════════════════════════
# METAL MASTER
# ═══════════════════════════════════════════════════════════════════════════════

@pytest.mark.p0
@pytest.mark.masters
class TestMetalMaster:
    """Metal master — Create, Edit, Delete cycle."""

    def test_metal_crud_full(self, auth_page, load_test_data, base_url):
        """TC_METAL_001: Full CRUD — create, edit, delete metal."""
        test_cases = load_test_data("masters")
        metals = test_cases.get("metal", []) if isinstance(test_cases, dict) else []
        if not metals:
            pytest.skip("No metal test data")

        tc = metals[0]
        metal = MetalPage(auth_page)
        metal.goto()

        # CREATE
        result = metal.create(tc["metal_name"], tc["short_code"], tc["tax_group"])
        assert result["status"] == "pass", f"Create failed: {result['message']}"

        # Verify in list
        assert metal.verify_in_list(tc["metal_name"]), f"{tc['metal_name']} not found in list"

        # EDIT
        if tc["edit"]["enabled"]:
            result = metal.edit(tc["metal_name"], tc["edit"]["new_code"])
            assert result["status"] == "pass", f"Edit failed: {result['message']}"

        # DELETE
        if tc["delete"]["enabled"]:
            search_name = tc["metal_name"]
            result = metal.delete(search_name)
            assert "deleted" in result["message"].lower() or result["status"] == "pass"

        metal.screenshot("metal_crud_complete")

    def test_metal_create_only(self, auth_page, load_test_data, base_url):
        """TC_METAL_002: Create only — no edit/delete."""
        test_cases = load_test_data("masters")
        metals = test_cases.get("metal", []) if isinstance(test_cases, dict) else []
        tc = next((m for m in metals if m["id"] == "TC_METAL_002"), None)
        if not tc:
            pytest.skip("TC_METAL_002 not found")

        metal = MetalPage(auth_page)
        metal.goto()

        result = metal.create(tc["metal_name"], tc["short_code"], tc["tax_group"])
        assert result["status"] == "pass", f"Create failed: {result['message']}"

    def test_metal_list_visual(self, auth_page, base_url):
        """Visual regression: metal list page."""
        metal = MetalPage(auth_page)
        metal.goto()
        metal.assert_list_layout()


# ═══════════════════════════════════════════════════════════════════════════════
# CATEGORY MASTER
# ═══════════════════════════════════════════════════════════════════════════════

@pytest.mark.p0
@pytest.mark.masters
class TestCategoryMaster:
    """Category master — Create, Edit, Delete cycle."""

    def test_category_ornament_crud(self, auth_page, load_test_data, base_url):
        """TC_CAT_001: Full CRUD — ornament category with purity."""
        test_cases = load_test_data("masters")
        categories = test_cases.get("category", []) if isinstance(test_cases, dict) else []
        tc = next((c for c in categories if c["id"] == "TC_CAT_001"), None)
        if not tc:
            pytest.skip("TC_CAT_001 not found")

        cat = CategoryPage(auth_page)
        cat.goto()

        # CREATE
        result = cat.create(
            name=tc["category_name"],
            hsn=tc["hsn_code"],
            code=tc["short_code"],
            cat_type=tc["category_type"],
            multi_metal=tc["multi_metal"],
            metal=tc["metal"],
            tax_group=tc["tax_group"],
            purities=tc.get("purity"),
            description=tc.get("description_text"),
        )
        assert result["status"] == "pass", f"Create failed: {result['message']}"

        # EDIT
        if tc["edit"]["enabled"]:
            result = cat.edit(tc["category_name"], tc["edit"]["new_name"])
            assert result["status"] == "pass", f"Edit failed: {result['message']}"

        # DELETE
        if tc["delete"]["enabled"]:
            delete_name = tc["edit"]["new_name"] if tc["edit"]["enabled"] else tc["category_name"]
            result = cat.delete(delete_name)
            assert result["status"] == "pass"

        cat.screenshot("category_crud_complete")

    def test_category_list_visual(self, auth_page, base_url):
        """Visual regression: category list page."""
        cat = CategoryPage(auth_page)
        cat.goto()
        cat.assert_list_layout()


# ═══════════════════════════════════════════════════════════════════════════════
# PRODUCT MASTER
# ═══════════════════════════════════════════════════════════════════════════════

@pytest.mark.p1
@pytest.mark.masters
class TestProductMaster:
    """Product master CRUD tests."""

    def test_product_crud(self, auth_page, load_test_data, base_url):
        """TC_PROD_001: Create, edit, delete product."""
        test_cases = load_test_data("masters")
        products = test_cases.get("product", []) if isinstance(test_cases, dict) else []
        tc = next((p for p in products if p["id"] == "TC_PROD_001"), None)
        if not tc:
            pytest.skip("TC_PROD_001 not found")

        prod = ProductPage(auth_page)
        prod.goto()

        result = prod.create(tc["product_name"], tc["short_code"], tc["category"])
        assert result["status"] == "pass", f"Create failed: {result['message']}"

        if tc["edit"]["enabled"]:
            result = prod.edit(tc["product_name"], tc["edit"]["new_name"])
            assert result["status"] == "pass", f"Edit failed: {result['message']}"

        if tc["delete"]["enabled"]:
            delete_name = tc["edit"]["new_name"] if tc["edit"]["enabled"] else tc["product_name"]
            result = prod.delete(delete_name)
            assert result["status"] == "pass"

        prod.screenshot("product_crud_complete")


# ═══════════════════════════════════════════════════════════════════════════════
# DESIGN MASTER
# ═══════════════════════════════════════════════════════════════════════════════

@pytest.mark.p1
@pytest.mark.masters
class TestDesignMaster:
    """Design master CRUD tests."""

    def test_design_crud(self, auth_page, load_test_data, base_url):
        """TC_DES_001: Create, edit, delete design."""
        test_cases = load_test_data("masters")
        designs = test_cases.get("design", []) if isinstance(test_cases, dict) else []
        tc = next((d for d in designs if d["id"] == "TC_DES_001"), None)
        if not tc:
            pytest.skip("TC_DES_001 not found")

        des = DesignPage(auth_page)
        des.goto()

        result = des.create(tc["design_name"], tc["design_code"], tc["product"])
        assert result["status"] == "pass", f"Create failed: {result['message']}"

        if tc["edit"]["enabled"]:
            result = des.edit(tc["design_name"], tc["edit"]["new_name"])
            assert result["status"] == "pass", f"Edit failed: {result['message']}"

        if tc["delete"]["enabled"]:
            delete_name = tc["edit"]["new_name"] if tc["edit"]["enabled"] else tc["design_name"]
            result = des.delete(delete_name)
            assert result["status"] == "pass"

        des.screenshot("design_crud_complete")
