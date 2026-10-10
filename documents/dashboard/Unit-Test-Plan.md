# Unit Test Plan: BUG-20260623-r5b9

## Target Module / Functions
- **Model:** `ret_dashboard_api_model.php`
- **Method:** `get_rate_cut_profit_loss($from_date, $to_date, $id_branch, $id_metal)`

## Test Scenarios

### Test 1: get_rate_cut_profit_loss returning correct rates for Gold
- **Target Function:** `ret_dashboard_api_model::get_rate_cut_profit_loss`
- **Inputs:** Date range including a Gold rate cut transaction.
- **Assertions:**
  - The returned `current_bullion_rate` matches the `goldrate_24ct` from `metal_rates` on that day.
  - `rate_deviation` and `profit_percentage` are computed accurately.

### Test 2: get_rate_cut_profit_loss returning correct rates for Silver
- **Target Function:** `ret_dashboard_api_model::get_rate_cut_profit_loss`
- **Inputs:** Date range including a Silver rate cut transaction.
- **Assertions:**
  - The returned `current_bullion_rate` matches the `silverrate_1gm` from `metal_rates` on that day.
  - `rate_deviation` and `profit_percentage` are computed accurately.
