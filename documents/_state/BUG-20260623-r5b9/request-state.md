# Request State: BUG-20260623-r5b9

- **Request ID:** BUG-20260623-r5b9
- **Status:** ready-for-implementation
- **Step:** pretest-docs-generated
- **Target Folder:** documents/dashboard/
- **Requirement Type:** Bug
- **Created At:** 2026-06-23T15:43:00+05:30
- **Unit Test Required:** true
- **Unit Test Reasoning:** The change alters the P&L and deviation calculation logic for rate cut transactions based on the metal type (Gold vs Silver).
- **Pretest Docs Generated:**
  - Defect-Report.md
  - Test-Case.md
  - Unit-Test-Plan.md

## Fields

- **User Story:** Dashboard Profit & Loss – Incorrect Current Rate Display for Silver Rate Cut Analysis. For silver purchase bill rate cuts, the system should display the current 1 Gram Silver Rate instead of the Gold 24KT Rate.
- **Requirement Type:** Bug
- **Links:** http://localhost/etail_development_src/admin/index.php/admin/dashboard
- **Impacts:** 
  - Dashboard Profit & Loss calculation logic will be corrected
  - Metal-wise current rate mapping will be updated
  - Silver rate cut analysis will display accurate current rates
  - Deviation and profit/loss calculations will become accurate
  - Dashboard reporting and management analysis will improve
- **Observation:** In the Dashboard Profit & Loss – Rate Cut Analysis, silver purchase bill rate cuts are displaying the Gold 24KT current rate instead of the current silver rate.
- **Screenshots:** Not assessed
