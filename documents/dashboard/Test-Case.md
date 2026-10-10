# Test Cases: BUG-20260623-r5b9

## Test Case 1: Gold Rate Cut Analysis Verification
- **Description:** Verify that Gold rate cut entries display the correct current Gold rate and calculations are correct.
- **Setup:** A Gold rate cut entry exists in `ret_supplier_rate_cut`.
- **Steps:**
  1. Open Dashboard → Profit & Loss - Rate Cut Analysis.
  2. Locate the row containing the Gold rate cut entry.
- **Expected Results:**
  - The "Current Rate" column displays the 24KT Gold Rate.
  - The "Deviation" and "Profit %" values are calculated based on the Gold rate.

## Test Case 2: Silver Rate Cut Analysis Verification
- **Description:** Verify that Silver rate cut entries display the correct current Silver rate and calculations are correct.
- **Setup:** A Silver rate cut entry exists in `ret_supplier_rate_cut`.
- **Steps:**
  1. Open Dashboard → Profit & Loss - Rate Cut Analysis.
  2. Locate the row containing the Silver rate cut entry.
- **Expected Results:**
  - The "Current Rate" column displays the 1 Gram Silver Rate.
  - The "Deviation" and "Profit %" values are calculated based on the Silver rate.
