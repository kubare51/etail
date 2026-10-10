# Defect Report: BUG-20260623-r5b9

- **Status:** New
- **Screen:** Dashboard / Profit & Loss – Rate Cut Analysis
- **Link:** http://localhost/etail_development_src/admin/index.php/admin/dashboard
- **Observation:** In the Dashboard Profit & Loss – Rate Cut Analysis, silver purchase bill rate cuts are displaying the Gold 24KT current rate instead of the current silver rate.
- **Description:** In the Dashboard → Profit & Loss – Rate Cut Analysis, the system is currently showing the 24KT Gold Rate under the Current Rate column for all rate cut transactions. When a Silver Purchase Bill is rate cut, the dashboard still displays the Gold 24KT Rate, Profit/Loss calculations are based on the wrong metal rate, and the displayed deviation and profit/loss values become inaccurate.
- **Expected Behavior:** For silver purchase bill rate cuts, the system should display the current 1 Gram Silver Rate instead of the Gold 24KT Rate, and calculate deviation and profit/loss correctly.
- **Impacts:** 
  - Dashboard Profit & Loss calculation logic will be corrected
  - Metal-wise current rate mapping will be updated
  - Silver rate cut analysis will display accurate current rates
  - Deviation and profit/loss calculations will become accurate
  - Dashboard reporting and management analysis will improve
- **Screenshots:** Not assessed
