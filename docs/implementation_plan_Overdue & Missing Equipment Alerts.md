# Overdue & Missing Equipment Alerts

This plan covers the fourth analytics feature: **Overdue & Missing Equipment Alerts**. We will add an "Alerts" dashboard view to help you track down equipment that should have been returned by now, and identify items that haven't been seen in a long time (preventing loss).

## Proposed Changes

### 1. `reports.php` Updates

- **Filter Form**: Add "Overdue & Missing Alerts" to the Report Category dropdown (`report_type = 'alerts'`).
- **Data Queries**: When `report_type` is `alerts`, we will run the following queries:
  - **Overdue Equipment**: We will query the `items` table for any item currently marked as `in_use`, and cross-reference its latest `check_out` scan in the `scan_logs` table to see if the `expected_return` date has passed.
  - **Unaudited/Missing Equipment**: We will check the `last_scanned` field in the `items` table for any active equipment that hasn't been scanned in the last 6 months (or has never been scanned), highlighting potential lost items.
- **UI Layout**:
  - Add a table for **Overdue Equipment** displaying the item, who took it (`transport_user`), where it went (`to_location`), and the exact date it was supposed to be returned. We will style this with red alert badges.
  - Add a table for **Needs Audit (6+ Months Unseen)** showing items that are overdue for a physical scan.

### 2. `export_reports.php` Updates

- Add handling for `report_type === 'alerts'`.
- Generate a CSV/Excel export containing the list of Overdue Equipment to hand to your team for follow-up.

## Verification Plan
- Manual verification: I will select the new "Overdue & Missing Alerts" report category and ensure the queries correctly fetch the missing/overdue data based on your database schema (`last_scanned` and `expected_return`). I'll also verify the export button generates the correct CSV.
