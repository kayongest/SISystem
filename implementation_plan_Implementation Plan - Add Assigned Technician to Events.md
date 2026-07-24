# Implementation Plan - Add Assigned Technician to Events

This plan outlines the changes required to allow users to select, assign, and view assigned technicians for events on `http://127.0.0.1/ability_app_main/events.php`.

## Proposed Changes

### Database
#### [MODIFY] `events` Table Schema
- Add column `assigned_technician VARCHAR(255) NULL` to the `events` table via SQL migration.

---

### Backend API
#### [MODIFY] [events.php](file:///c:/xampp/htdocs/ability_app_main/api/events.php)
- Update `GET` method to return `assigned_technician`.
- Update `POST` method to receive `$_POST['technician']` and store it in `assigned_technician`.
- Update `PUT` method to receive `technician` from form data and update `assigned_technician`.

#### [MODIFY] [explore_list.php](file:///c:/xampp/htdocs/ability_app_main/api/events/explore_list.php)
- Update query to return `COALESCE(e.assigned_technician, MAX(sm.submitted_by)) as technician`.

---

### Frontend UI & Forms
#### [MODIFY] [events.php](file:///c:/xampp/htdocs/ability_app_main/events.php)
- Fetch active technicians from the `users` table.
- Add an **Assigned Technician** dropdown input in `eventFormModal` for both Create Event and Edit Event.
- Update `saveEvent()` JS to collect and submit the technician selection.
- Update `openFormModal()` / `editEvent()` JS to prepopulate the selected technician when editing an event.
- Display the assigned technician on event cards (Grid & List views) and inside the Event Details Modal.

---

## Verification Plan

### Automated / Database Verification
- Execute `DESCRIBE events` via PHP script to confirm column addition.
- Test `GET`, `POST`, and `PUT` calls to `api/events.php` with technician parameter.

### Manual Verification
- Open `http://127.0.0.1/ability_app_main/events.php` in browser.
- Click **Add Event**, select a technician from the dropdown, fill in details, and save.
- Verify that the assigned technician displays on the event card and in the Event Details modal.
- Click **Edit**, change the assigned technician, save, and verify that the change persists.
