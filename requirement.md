# Bug Requirement: PAY-ADV-001 - Monthly Scheme Allows Multiple Payments Beyond Max Chance

## Context
**Module:** Payment
**Screen:** Payment Add Form (Customer Scheme Payment)
**Symptom:** A monthly scheme with `max_chance = 1` allows the user to make a second payment in the same month if "Advance Payment" is enabled, even though the monthly chance is already exhausted.
**Violated Business Rule:** `RULE-PAY-013` - Monthly Scheme: Chances = number of payments in current month. Controlled by `scheme.max_chance`.
**Root Cause (🟢 CONFIRMED):** 
In `payment_model::get_paymentContent()`, the logic for installment cycles 0/1/2/3 (Monthly/Daily) uses a loose condition `chances_allowed_due <= 1` to allow Advance/Pending payments. If `max_chance` is 1 and one payment is already made, `chances_allowed_due` is 0, which satisfies `0 <= 1`, thus wrongly allowing another payment.
Additionally, the ternary logic at line 1925 completely misses the `max_chance` check when `allow_advance` is enabled.

## User Story
As a branch manager, I want the system to strictly enforce the "Max Chance" setting for monthly schemes, so that customers cannot bypass the one-payment-per-month rule by simply selecting an advance installment, unless specifically allowed by the scheme settings.

## Observation
- Account `89042` (Monthly Scheme)
- `total_installments` = 11
- `paid_installments` = 1
- `currentmonthpaycount` = 1
- `max_chance` = 1
- `allow_advance` = 1
- System still returns `allow_pay = 'Y'` and allows paying for the next 10 months.

## Impact
- Financial inconsistency: Customers can over-pay in a month, potentially leading to bonus calculation issues or scheme rule violations.
- Bypass of administrative controls on payment frequency.

## Acceptance Criteria
- For schemes with `max_chance > 0`, the system must block ANY additional payment transactions in the same period once `max_chance` is reached, regardless of whether it's a Normal, Advance, or Pending due.
- Unlimited schemes (`max_chance = 0`) should remain unaffected and allow multiple payments.
- `payment_model::get_paymentContent()` must be updated to correctly check `chances_allowed_due > 0` before allowing Advance/Pending dues.
- The ternary logic at L1925 must also respect `max_chance`.
