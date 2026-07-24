# Implementation Plan - Multi-Technician Assignment with Labels

This plan details how to support assigning multiple technicians to an event and displaying them as styled badges/labels across the application.

## Proposed Changes

### Database & Backend
#### [MODIFY] [api/events.php](file:///c:/xampp/htdocs/ability_app_main/api/events.php)
- Update POST and PUT handlers to accept multiple technicians (`technicians[]` array or comma-separated string).
- Save assigned technicians as a comma-separated string / JSON array in `assigned_technician`.

#### [MODIFY] [api/events/explore_list.php](file:///c:/xampp/htdocs/ability_app_main/api/events/explore_list.php)
- Ensure `explore_list.php` returns the full `assigned_technician` string.

---

### Frontend UI & Form
#### [MODIFY] [events.php](file:///c:/xampp/htdocs/ability_app_main/events.php)
- **Multi-Select Input**: Update `eventFormModal` to use a multi-select dropdown / checkbox selection for `technicians[]`.
- **Event Cards (Grid & List View)**: Render each assigned technician as an individual styled badge/label.
- **Event Details Modal**: Format assigned technicians into interactive badges.
- **Edit Modal Pre-population**: Parse multiple technicians when editing an event to pre-select all assigned technicians.

---

## Verification Plan

### Automated / Syntax Check
- Run PHP syntax check (`php -l`) on modified files.

### Manual Verification
- Open `http://127.0.0.1/ability_app_main/events.php`.
- Create or Edit an event and select 2+ technicians.
- Verify that both technicians display as distinct badges/labels on the event card and inside the Event Details modal.
