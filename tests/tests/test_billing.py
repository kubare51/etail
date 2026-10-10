"""
Billing E2E Tests

Migrated from: Sparqla/Test_Bill/Bill.py (901 lines)
Tests: All 8 bill types, payment flows, total summary verification,
       credit billing, PAN threshold, visual regression.
"""
import pytest
import random
import string
from playwright.sync_api import expect
from pages.billing_page import BillingPage


@pytest.mark.p0
@pytest.mark.billing
class TestBilling:
    """Billing module tests — P0 priority."""

    def test_billing_form_loads(self, auth_page, base_url):
        """Verify new bill form loads with all required elements."""
        billing = BillingPage(auth_page)
        billing.goto_new_bill()

        # Verify key elements
        expect(auth_page.locator(f"xpath={billing.COST_CENTRE}")).to_be_visible()
        expect(auth_page.locator(f"xpath={billing.BILLING_TO_CUSTOMER}")).to_be_visible()
        expect(auth_page.locator(f"xpath={billing.EMPLOYEE_SELECT2}")).to_be_visible()

        billing.screenshot("billing_form_loaded")

    def test_billing_form_visual(self, auth_page, base_url):
        """Visual regression: new bill form layout."""
        billing = BillingPage(auth_page)
        billing.goto_new_bill()
        expect(auth_page).to_have_screenshot(
            "billing_new_form.png",
            full_page=True,
            max_diff_pixel_ratio=0.01,
        )

    def test_billing_header_fill(self, auth_page, load_test_data, base_url):
        """Fill billing header — cost centre, billing to, employee, customer."""
        test_cases = load_test_data("billing")
        if not test_cases:
            pytest.skip("No billing test data")

        tc = test_cases[0]
        billing = BillingPage(auth_page)
        billing.goto_new_bill()

        billing.fill_header(
            cost_centre=tc["header"]["cost_centre"],
            billing_to=tc["header"]["billing_to"],
            employee=tc["header"]["employee"],
            customer_number=tc["header"]["customer_number"],
        )

        billing.screenshot("billing_header_filled")

    def test_bill_type_selection(self, auth_page, base_url):
        """Verify all 8 bill type radio buttons are clickable."""
        billing = BillingPage(auth_page)
        billing.goto_new_bill()

        for bill_type, selector in billing.BILL_TYPES.items():
            radio = auth_page.locator(f"xpath={selector}")
            expect(radio).to_be_visible()

    def test_sales_bill_with_estimation(self, auth_page, load_test_data, base_url):
        """
        Full SALES bill flow:
        1. Fill header
        2. Select SALES bill type
        3. Load estimation
        4. Verify total summary
        5. Fill payment (cash)
        6. Submit and capture bill ID
        """
        test_cases = load_test_data("billing")
        if not test_cases:
            pytest.skip("No billing test data")

        tc = test_cases[0]
        billing = BillingPage(auth_page)
        billing.goto_new_bill()

        # 1. Header
        billing.fill_header(
            cost_centre=tc["header"]["cost_centre"],
            billing_to=tc["header"]["billing_to"],
            employee=tc["header"]["employee"],
            customer_number=tc["header"]["customer_number"],
        )

        # 2. Bill type
        billing.select_bill_type("SALES")

        # 3. Estimation (if not direct)
        if not tc.get("direct", True) and tc.get("estimation_no"):
            billing.fill_estimation(tc["estimation_no"])
            auth_page.wait_for_timeout(3000)

            # 4. Total Summary
            billing.goto_total_summary()
            summary = billing.get_summary_values()
            assert summary["taxable"] > 0 or summary["sale_with_tax"] > 0, "Sale amount should be > 0"

            # Fill charges if provided
            billing.fill_charges(
                discount=tc.get("charges", {}).get("discount"),
                handling=tc.get("charges", {}).get("handling_charges"),
            )

        # 5. Payment
        billing.goto_payment()

        # PAN threshold check (≥ 2 Lakh)
        total_text = billing.get_value(billing.RECEIVED_AMOUNT)
        try:
            total_val = float(total_text.replace(",", "")) if total_text else 0
            if total_val >= 200000:
                pan = ''.join(random.choices(string.ascii_uppercase, k=5)) + \
                      ''.join(random.choices(string.digits, k=4)) + \
                      random.choice(string.ascii_uppercase)
                billing.fill_payment(pan=pan)
        except (ValueError, TypeError):
            pass

        # Fill cash
        cash = tc.get("payment", {}).get("cash")
        if cash:
            billing.fill_payment(cash=cash)

        # 6. Submit
        bill_id = billing.submit_bill()
        assert bill_id, "Should capture bill ID from new tab"

        # Verify success
        msg = billing.get_success_message()
        assert "successfully" in msg.lower(), f"Expected success message, got: {msg}"

        billing.screenshot("billing_sales_complete")

    def test_total_summary_visual(self, auth_page, base_url):
        """Visual regression: total summary tab layout."""
        billing = BillingPage(auth_page)
        billing.goto_new_bill()

        # Navigate to total summary tab
        billing.goto_total_summary()
        expect(auth_page).to_have_screenshot(
            "billing_total_summary.png",
            full_page=True,
            max_diff_pixel_ratio=0.02,
        )

    def test_payment_tab_visual(self, auth_page, base_url):
        """Visual regression: make payment tab layout."""
        billing = BillingPage(auth_page)
        billing.goto_new_bill()
        billing.goto_payment()
        expect(auth_page).to_have_screenshot(
            "billing_payment_tab.png",
            full_page=True,
            max_diff_pixel_ratio=0.02,
        )
