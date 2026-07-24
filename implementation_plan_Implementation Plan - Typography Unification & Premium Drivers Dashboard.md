# Implementation Plan - Typography Unification & Premium Drivers Dashboard

This plan outlines the steps to standardize typography across the **aBility Manager** application using the premium **Titillium Web** font, and rebuild the **Manage Drivers** page ([drivers.php](file:///c:/xampp/htdocs/ability_app_main/drivers.php)) into a modern, high-fidelity dashboard.

---

## Proposed Changes

### Component 1: Typography Standardization

#### [MODIFY] [header.php](file:///c:/xampp/htdocs/ability_app_main/views/partials/header.php)
1. Import **Titillium Web** from Google Fonts:
   `https://fonts.googleapis.com/css2?family=Titillium+Web:ital,wght@0,200;0,300;0,400;0,600;0,700;0,900;1,200;1,300;1,400;1,600;1,700&display=swap`
2. Change the primary font-family rules in the global `<style>` block from `"Marvel", sans-serif` to `"Titillium Web", sans-serif`.

---

### Component 2: Premium Drivers Dashboard Redesign

#### [MODIFY] [drivers.php](file:///c:/xampp/htdocs/ability_app_main/drivers.php)
1. **Layout Integration:** Replace the manual HTML structure with `require_once 'views/partials/header.php'` to dynamically include navigation, sidebar elements, and the base stylesheets.
2. **Dashboard Statistics Cards:** Add a row of cards displaying key logistics metrics:
   - **Total Registered Drivers**
   - **Available Drivers** (Green)
   - **Drivers On Trip** (Amber)
   - **Vehicles In Maintenance** (Red)
3. **Glassmorphism Design:** Implement clean glass-card panels for the driver listing and form modals.
4. **Enhanced Data Listing:**
   - Display vehicle details with inline icons (e.g. `fas fa-truck-pickup`, `fas fa-shuttle-van`).
   - Create high-quality, glowing status badges for driver availability.
   - Clean up table layout with custom actions buttons (`btn-outline-dark`).

---

## Verification Plan

### Manual Verification
1. Open the application dashboard and verify that all typography matches the new clean **Titillium Web** font.
2. Navigate to the **Setup** dropdown in the menu and click **Drivers**.
3. Verify that the page loads within the unified navigation frame.
4. Check that the driver status cards display accurate counts.
5. Create, edit, and delete a simulated driver to confirm AJAX/POST actions work correctly.
