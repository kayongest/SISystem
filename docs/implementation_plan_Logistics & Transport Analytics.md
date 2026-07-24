# Logistics & Transport Analytics

This plan outlines the steps to build the third analytics feature: **Logistics & Transport Analytics**. We will add a new "Logistics & Transport Analytics" view to your reports dashboard to help you track how your equipment is being physically moved, which drivers are the busiest, and which vehicles are making the most runs.

## Proposed Changes

### 1. `reports.php` Updates

- **Filter Form**: Add "Logistics & Transport" to the Report Category dropdown (`report_type = 'logistics'`).
- **Data Queries**: When `report_type` is `logistics`, run the following new queries on the `stock_movements` table:
  - **Top Drivers**: Count the total number of movements/deliveries grouped by `transport_driver` to see who handles the most runs.
  - **Top Vehicles**: Count the total movements grouped by `transport_vehicle_number` and `transport_vehicle_type` to monitor fleet usage.
- **UI Layout**:
  - Add a table or bar chart showing the "Top Drivers" and their total deliveries for the selected period.
  - Add a table showing "Vehicle Usage", highlighting the specific license plates / vehicles taking on the most work.

### 2. `export_reports.php` Updates

- Add handling for `report_type === 'logistics'`.
- Generate a CSV/Excel export containing the full list of drivers and vehicles with their movement counts for external analysis.

## Verification Plan
- Manual verification: I will select the new "Logistics & Transport" report category and ensure the data populates the new charts and tables correctly, based on your historical `stock_movements` data. I'll also test the export functionality.
