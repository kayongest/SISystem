# Implementation Plan - RFID Simulator Page

This plan outlines the design and implementation of an interactive **RFID Simulator** page in the **aBility Asset Intelligence & Inventory Management System**. The simulator will demonstrate how UHF RFID technology solves the "fly case" problem (reading contents without opening the case and detecting mismatched items) using a fully functional, highly visual sandbox integrated with the live MySQL database.

---

## Proposed Changes

### Component 1: Web Interface & Controller

#### [NEW] [rfid_simulator.php](file:///c:/xampp/htdocs/ability_app_main/rfid_simulator.php)
Create a new main page for the RFID Simulator that provides:
1. **Interactive Hardware Visualization:** A panel explaining the three RFID layers (Tags, Readers, Middleware) with interactive toggles.
2. **Interactive Scan Sandbox:**
   - Case selector (dynamic dropdown of cases from the database).
   - "Physical Scan Zone" checklist (user checks which items are *physically* inside the case during the scan, with pre-configured templates for normal vs. discrepant states containing items 4, 9, 10, and 12).
   - "Pull Trigger / Scan Gate" button with sweeping radar micro-animations.
3. **Middleware Console:** A monospace terminal output simulating raw EPC scans translating into JSON payloads.
4. **Discrepancy Audit Engine:** Compare scanned items against the database `storage_location` of the selected case. Highlight mismatched items (e.g. 4, 9, 10, 12) in red and missing items in orange.
5. **Database Sync Action:** A "Sync Database" button that updates the database `storage_location` fields to match the audited physical scan.

#### [MODIFY] [navbar_main.php](file:///c:/xampp/htdocs/ability_app_main/includes/navbar_main.php)
Add a new link to the **RFID Simulator** under the navigation sidebar/header layout.

### Component 2: API Endpoints

#### [NEW] [api/cases/rfid_sync.php](file:///c:/xampp/htdocs/ability_app_main/api/cases/rfid_sync.php)
Create an API endpoint that handles:
- Packing/unpacking items automatically based on the simulated physical scan (updating `storage_location` in the MySQL database).

---

## Verification Plan

### Manual Verification
1. Log in to the application and navigate to the new **RFID Simulator** link in the navbar.
2. Select a case (e.g., standard case or one queried from the DB).
3. Toggle the physical scan contents: check items 4, 9, 10, and 12.
4. Run the simulated RFID sweep and verify the micro-animations.
5. Check that the **Middleware Logs** output correct simulated EPC values.
6. Verify that the **Discrepancy Engine** accurately flags items 4, 9, 10, and 12 as "Does Not Belong" (not registered to this case).
7. Click "Sync Database" and verify that the items' `storage_location` updates in the database and is reflected in the main **Fly Cases** dashboard.
