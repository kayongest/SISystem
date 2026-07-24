# Location & Venue Heatmaps

This plan outlines the steps to build the second analytics feature: Location & Venue Heatmaps. We will add a new "Location & Venue Heatmaps" view to the reports dashboard to help you track which venues/destinations consume the most equipment, and exactly where your stock is currently sitting.

## Proposed Changes

### 1. `reports.php` Updates

- **Filter Form**: Add "Location & Venue Heatmaps" to the Report Category dropdown (`report_type = 'locations'`).
- **Data Queries**: When `report_type` is `locations`, run the following new queries:
  - **Top Destinations (Venue Heatmap)**: Query the `stock_movements` table to count how many times equipment has been dispatched to each `destination_name` (e.g., BK Arena, KCC).
  - **Current Stock Distribution**: Query the `items` table to count how many active items are currently assigned to each `stock_location` (e.g., Ndera).
- **UI Layout**:
  - Add a visually appealing bar chart showing your "Top Venues / Destinations" by movement frequency.
  - Add a table breaking down the "Top Destinations" and the total number of equipment runs they've required.
  - Add a pie chart or progress bars showing your "Current Stock Distribution" across all warehouses/locations.

### 2. `export_reports.php` Updates

- Add handling for `report_type === 'locations'`.
- Export a clean spreadsheet that summarizes the Venue/Destination dispatch counts and your Stock Location inventory totals so you can use the data externally.

## Verification Plan
- Manual verification: I will select the new "Location & Venue Heatmaps" report and ensure the data populates the new charts/tables correctly. I will also test the CSV export.
