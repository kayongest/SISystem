# Offline-First Scanning & Sync (PWA) + Asset Label Printing

This plan details the implementation of two primary features:
1. **Offline-First Scanning & Sync (PWA Upgrade):** Caching assets via a Service Worker, caching a lightweight items catalog, and queuing scans/batches offline in `localStorage` for automatic sync when online.
2. **Standardized PDF/HTML Barcode & Asset Label Printing:** Adding bulk checkboxes to print labels in a clean 3x10 Avery 5160 grid using browser-native print controls and client-side QR generation.

---

## User Review Required

> [!IMPORTANT]
> **Client-Side QR Generation for Printing**
> In the print labels module, we will utilize the client-side `qrcode.min.js` library (already cached by the system) instead of server-side PHP GD image generation. This bypasses the PHP GD library error documented in your `ERROR_REPORT.md` and makes the printing module 100% resilient to server extension limitations.
>
> **Avery 5160 Dimensions**
> The printing layout is configured by default for standard Avery 5160 sheets (3 columns, 10 rows per sheet, margins, and spacing). Let me know if you use another label template so I can customize the CSS dimensions.

---

## Proposed Changes

### PWA & Offline Scanning Architecture

#### [NEW] [get_items_offline_cache.php](file:///c:/xampp/htdocs/ability_app_main/api/get_items_offline_cache.php)
*   **Purpose:** Create a lightweight API that returns all active items (ID, Name, Serial Number, Stock Location, Status) in a single compact JSON response to build the client-side offline catalog.
*   **Key Details:**
    *   Query `items` table for active equipment.
    *   Return minimal fields: `id`, `item_name`, `serial_number`, `status`, `stock_location`.

#### [NEW] [sw.js](file:///c:/xampp/htdocs/ability_app_main/sw.js)
*   **Purpose:** Service Worker file located at the workspace root to cache essential assets for offline access.
*   **Key Details:**
    *   Cache main assets: `scan_bulk.php`, `manifest.json`, CSS, JQuery, Bootstrap, and HTML5 QR code scanner libraries.
    *   Implement a "Network first, falling back to cache" strategy for scanning workflows.

#### [NEW] [manifest.json](file:///c:/xampp/htdocs/ability_app_main/manifest.json)
*   **Purpose:** Web App Manifest to make the system installable on mobile devices.
*   **Key Details:**
    *   Specify application name, start URL (`scan_bulk.php`), theme colors, display mode (`standalone`), and icons.

#### [MODIFY] [header.php](file:///c:/xampp/htdocs/ability_app_main/includes/header.php)
*   **Purpose:** Link to the manifest and register the service worker globally.
*   **Key Details:**
    *   Add `<link rel="manifest" href="manifest.json">`.
    *   Add a script block to register `sw.js` if supported by the browser.

#### [MODIFY] [scan_bulk.php](file:///c:/xampp/htdocs/ability_app_main/scan_bulk.php)
*   **Purpose:** Introduce offline logic, catalog synchronization, local storage queuing, and online auto-syncing.
*   **Key Details:**
    *   **Catalog Sync:** On page load (when online), query `api/get_items_offline_cache.php` and cache it in `localStorage.setItem('offline_catalog', ...)`.
    *   **Online/Offline Detection:** Add UI connection badge (e.g., green "Online" vs amber "Working Offline").
    *   **Offline Scanner Handler:** If offline during scan, search the `offline_catalog` local storage cache for the scanned ID or serial number. If found, add details to `batchItems` and update the UI. If not found, add as an "Offline Scan" placeholder.
    *   **Offline Batch Submission:** If the user submits a batch while offline:
        1. Capture all batch details (technician, driver, destination, item IDs).
        2. Append it to an `offline_batches_queue` array in `localStorage`.
        3. Clear the screen, notify the user the batch is queued locally, and show the pending queue count.
    *   **Sync Logic:** Listen to window `online` event. When connection is restored, iterate over the `offline_batches_queue`, POST them to `api/submit_batch.php`, remove them from the queue, and show success alerts.

---

### Barcode & Asset Label Printing

#### [NEW] [print_labels.php](file:///c:/xampp/htdocs/ability_app_main/print_labels.php)
*   **Purpose:** A dedicated, clean printable view for selected items using `@media print` CSS.
*   **Key Details:**
    *   Accept a comma-separated list of item IDs via `$_GET['ids']`.
    *   Query database for item names, serials, and locations.
    *   Load `qrcode.min.js`.
    *   Generate a grid matching Avery 5160 labels (width: `2.625in`, height: `1in`, 3 columns, 10 rows).
    *   Render QR code (containing the item details URL or ID), item name, ID, and serial number on each label.
    *   Include CSS `@media print` to remove headers/footers and force automatic page breaks every 30 labels.
    *   Trigger `window.print()` automatically on page load.

#### [MODIFY] [index.php](file:///c:/xampp/htdocs/ability_app_main/views/items/index.php)
*   **Purpose:** Add the "Print Labels" button to the bulk action group in the items table.
*   **Key Details:**
    *   Add a new button: `<button type="button" class="btn text-white btn-sm" style="background-color: #8C6A5C;" id="bulkPrintLabelsBtn" disabled><i class="fas fa-print me-1"></i>Print Selected Labels</button>`.
    *   In `updateBulkActions()`, enable the button if `selectedCount > 0`.
    *   Add click handler: Gather selected IDs from checked rows and open `print_labels.php?ids=ID1,ID2,ID3...` in a new tab.

---

## Verification Plan

### Manual Verification
1. **Label Printing:**
   * Go to the **Equipment Management** page.
   * Check several checkboxes next to items in the list.
   * Click **Print Selected Labels**.
   * Verify that a new tab opens showing a grid of labels with generated QR codes.
   * Verify that the browser's print dialog opens and previews a clean Avery-compatible grid.
2. **Offline Simulation:**
   * Open the **Bulk Scanning** page.
   * Turn off internet access (or toggle DevTools offline simulation).
   * Verify that the status indicator changes to "Working Offline".
   * Scan or search for a cached item ID manually.
   * Verify that the item details are resolved locally from `offline_catalog` and added to the batch list.
   * Click **Submit Batch** while offline.
   * Verify that the batch is queued locally and is not lost.
   * Re-enable internet connection.
   * Verify that the queue is processed automatically, submitting the batch, and showing a success notification.
