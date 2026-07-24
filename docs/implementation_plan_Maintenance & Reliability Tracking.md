# Maintenance & Reliability Tracking

This plan outlines the final analytics feature: **Maintenance & Reliability Tracking**. We will add a new dashboard view to help you analyze equipment breakdowns and identify which brands or models are costing you the most time and money in repairs.

## Proposed Changes

### 1. `reports.php` Updates

- **Filter Form**: Add "Maintenance & Reliability" to the Report Category dropdown (`report_type = 'maintenance_report'`).
- **Data Queries**: When `report_type` is `maintenance_report`, we will run the following queries:
  - **Most Frequently Repaired Items**: We will count how many times each item has been scanned as `maintenance` in the `scan_logs` table. This highlights specific items (and their brands/models) that break down repeatedly.
  - **Currently in Maintenance**: A quick snapshot of all equipment currently marked with `status = 'maintenance'` in the `items` table, so you know exactly what is out of commission right now.
- **UI Layout**:
  - Add a table for **Most Frequently Repaired**, ranking items by their total number of repair scans. We'll show the Brand and Model alongside the item name to help you spot unreliable product lines.
  - Add a table for **Currently in Maintenance**, listing the items that are broken right now, along with any notes from their latest scan.

### 2. `export_reports.php` Updates

- Add handling for `report_type === 'maintenance_report'`.
- Generate a CSV/Excel export containing the Most Frequently Repaired list, so you can factor it into your future purchasing decisions.

## Verification Plan
- Manual verification: I will select the new "Maintenance & Reliability" report category and ensure the queries correctly fetch the maintenance scan data. I'll verify the UI displays the repair counts properly and test the data export.
