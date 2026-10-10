"""BillingPage — Billing module page object."""
from pages.base_page import BasePage


class BillingPage(BasePage):
    """Page object for billing (new bill / bill list)."""

    # ── Selectors ─────────────────────────────────────────────────────────────

    # Navigation
    MENU_BILLING = "//span[contains(text(), 'Billing')]"
    MENU_NEW_BILL = "//span[contains(text(), 'New Bill')]"

    # Header
    COST_CENTRE = '//select[@id="id_branch"]'
    BILLING_TO_CUSTOMER = '//input[@id="billing_for1"]'
    BILLING_TO_COMPANY = '//input[@id="billing_for2"]'
    BILLING_TO_SUPPLIER = '//input[@id="billing_for3"]'
    EMPLOYEE_SELECT2 = "//span[@id='select2-emp_select-container']"
    CUSTOMER_AUTOCOMPLETE = "bill_cus_name"
    CLOSE_MODAL = '(//button[@class="btn btn-close btn-warning"])[11]'

    # Bill Type radio buttons
    BILL_TYPES = {
        "SALES": '//input[@id="bill_typesales"]',
        "SALES & PURCHASE": '//input[@id="bill_type_salesPurch"]',
        "SALES PURCHASE & RETURN": '//input[@id="bill_type_saleRet"]',
        "PURCHASE": '//input[@id="bill_type_purchase"]',
        "ORDER ADVANCE": '//input[@id="bill_type_order_advance"]',
        "SALES RETURN": '//input[@id="bill_type_sales_return"]',
        "CREDIT COLLECTION": '//input[@id="bill_type_credit_bill"]',
        "ORDER DELIVERY": '//input[@id="bill_type_order_del"]',
        "Repair Order Delivery": '//input[@id="repair_order_delivery"]',
    }

    # Estimation
    EST_FILTER = '//input[@id="filter_est_no"]'
    EST_SEARCH_BTN = '//button[@id="search_est_no"]'

    # Total Summary
    TAB_TOTAL_SUMMARY = "//a[normalize-space()='Total Summary']"
    TAXABLE_SALE_AMT = "//span[@class='summary_lbl summary_sale_amt']"
    SALES_CGST = '//span[@class="summary_lbl sales_cgst"]'
    SALES_SGST = '//span[@class="summary_lbl sales_sgst"]'
    SALES_IGST = '//span[@class="summary_lbl sales_igst"]'
    SALE_AMT_WITH_TAX = '//span[@class="summary_lbl sale_amt_with_tax"]'
    PURCHASE_AMT = '//span[@class="summary_lbl summary_pur_amt"]'
    DISCOUNT = '//input[@id="summary_discount_amt"]'
    HANDLING_CHARGES = '//input[@id="handling_charges"]'
    RETURN_CHARGES = '//input[@id="return_charges"]'
    NEXT_TAB_BTN = '(//button[@class="btn btn-warning next-tab"])[2]'

    # Payment
    TAB_MAKE_PAY = '//li[@id="tab_make_pay"]'
    PAN_NO = "#pan_no"
    RECEIVED_AMOUNT = '//input[@name="billing[tot_amt_received]"]'
    IS_CREDIT_YES = '//input[@id="is_credit_yes"]'
    IS_TOBE_YES = '//input[@id="is_to_be_yes"]'
    CREDIT_DUE_DATE = '//input[@id="credit_due_date"]'
    CASH_INPUT = '//input[@id="make_pay_cash"]'
    PAY_SUBMIT = '//button[@id="pay_submit"]'
    PAYMENT_BALANCE = '//table[@id="payment_modes"]//tfoot//tr[2]//th[3]'

    # Verification
    SUCCESS_ALERT = "//div[contains(@class, 'alert-success')]"

    # ── Actions ───────────────────────────────────────────────────────────────

    def goto_new_bill(self):
        """Navigate to new bill page."""
        self.navigate_via_menu(
            "Billing", "New Bill",
            fallback_path="index.php/admin_ret_billing/billing/add"
        )
        return self

    def fill_header(self, cost_centre: str, billing_to: str, employee: str, customer_number: str):
        """Fill the billing header section."""
        # Cost Centre
        if cost_centre:
            self.select_native(self.COST_CENTRE, cost_centre)

        # Billing To (radio)
        billing_to_map = {
            "Customer": self.BILLING_TO_CUSTOMER,
            "Company": self.BILLING_TO_COMPANY,
            "Supplier": self.BILLING_TO_SUPPLIER,
        }
        self.click(billing_to_map.get(billing_to, self.BILLING_TO_CUSTOMER))

        # Employee (Select2)
        if employee:
            self.select2(self.EMPLOYEE_SELECT2, employee)

        # Customer (autocomplete)
        if customer_number:
            self.autocomplete(self.CUSTOMER_AUTOCOMPLETE, str(customer_number))

        # Close modal if present
        try:
            self.click(self.CLOSE_MODAL)
        except Exception:
            pass

    def select_bill_type(self, bill_type: str):
        """Select the bill type radio button."""
        selector = self.BILL_TYPES.get(bill_type)
        if selector:
            self.click(selector)
        else:
            raise ValueError(f"Unknown bill type: {bill_type}")

    def fill_estimation(self, est_no: str):
        """Search and load an estimation."""
        self.fill(self.EST_FILTER, est_no)
        self.click(self.EST_SEARCH_BTN)
        self.page.wait_for_timeout(2000)
        try:
            self.click(self.CLOSE_MODAL)
        except Exception:
            pass

    def goto_total_summary(self):
        """Click the Total Summary tab."""
        self.click(self.TAB_TOTAL_SUMMARY)

    def get_summary_values(self) -> dict:
        """Read all summary values from the Total Summary tab."""
        return {
            "taxable": self.get_float(self.TAXABLE_SALE_AMT),
            "cgst": self.get_float(self.SALES_CGST),
            "sgst": self.get_float(self.SALES_SGST),
            "igst": self.get_float(self.SALES_IGST),
            "sale_with_tax": self.get_float(self.SALE_AMT_WITH_TAX),
            "purchase": self.get_float(self.PURCHASE_AMT),
        }

    def fill_charges(self, discount: float = None, handling: float = None, returns: float = None):
        """Fill discount, handling, and return charges."""
        if discount is not None:
            self.fill(self.DISCOUNT, str(discount))
        if handling is not None:
            self.fill(self.HANDLING_CHARGES, str(handling))
        if returns is not None:
            self.fill(self.RETURN_CHARGES, str(returns))

    def goto_payment(self):
        """Navigate to the Make Payment tab."""
        try:
            self.click(self.NEXT_TAB_BTN)
        except Exception:
            self.click(self.TAB_MAKE_PAY)

    def fill_payment(self, cash: float = None, pan: str = None):
        """Fill payment details."""
        if pan:
            self.fill(self.PAN_NO, pan)
        if cash is not None:
            self.fill(self.CASH_INPUT, str(int(cash)))

    def submit_bill(self) -> str:
        """Submit the bill and capture the bill ID from the new tab."""
        return self.click_and_capture_new_tab(self.PAY_SUBMIT)

    def get_success_message(self) -> str:
        """Get the success alert message after bill submission."""
        try:
            alert = self.page.locator(self.SUCCESS_ALERT)
            alert.wait_for(state="visible", timeout=10000)
            return alert.inner_text().strip()
        except Exception:
            return ""
