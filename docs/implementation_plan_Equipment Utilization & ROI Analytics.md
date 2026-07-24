# Equipment Utilization & ROI Analytics

This plan outlines the steps to build the first analytics feature: Equipment Utilization & ROI. We will add a new "Equipment Utilization" view to the reports dashboard to help you track which equipment is used the most, what sits idle, and overall inventory deployment rates.

## User Review Required

Please review the proposed metrics. If you have any specific definitions for "Utilization" (e.g., you prefer to use `scan_logs` vs `batch_items` for checkout counts), let me know! Otherwise, I will use the available movement data.

## Proposed Changes

### 1. `reports.php`

- **Filter Form**: Add "Equipment Utilization" to the Report Category dropdown (`report_type = 'utilization'`).
- **Data Queries**: When `report_type` is `utilization`, run new SQL queries:
  - **Most Used Equipment**: Count check-out records for each item to find the top 10 most utilized items.
  - **Least Used/Idle Equipment**: Identify items with zero or very few check-outs over the selected time period.
  - **Overall Deployment Rate**: A pie chart showing the percentage of items currently 'in_use' versus 'available' or 'maintenance'.
- **UI Sections**:
  - Add a bar chart showing the top 10 most used items.
  - Add a table listing the least used equipment (candidates for sale or retirement).
  - Add summary cards highlighting the overall utilization rate (%).

### 2. `export_reports.php`

- Add handling for `report_type === 'utilization'`.
- Export a detailed CSV/Excel sheet listing every item along with its total checkout count, current status, and calculated utilization metrics for the selected date range.

## Verification Plan

### Manual Verification
- Select the new "Equipment Utilization" category on the Reports page.
- Verify that the bar chart and tables populate correctly with data.
- Verify that the "CSV/Excel" export button downloads a spreadsheet with the utilization data.
