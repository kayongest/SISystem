# Accessory Categories Implementation

We will add a "Category" field to Accessories so you can easily group items like "Video Cables", "Audio Cables", and "Power Adapters".

## Proposed Changes

### 1. Database Update
- We will run a database migration to add a `category` column to the `accessories` table.

### 2. API Updates
- **`api/accessories/create.php`**: Update to accept and save the new `category` field when adding a new accessory.
- **`api/accessories/update.php`**: Update to accept and save the new `category` field when editing an existing accessory.

### 3. Frontend Updates (`accessories.php`)
- **Add / Edit Modals**: Add a "Category" input field to both the Add Accessory and Edit Accessory modals. We will use a smart input field with an auto-complete dropdown, so you can easily type "Video cables" and it will suggest it for future accessories.
- **Dashboard Table**: Add a "Category" column to the main Accessory List table, making it easy to see what group each item belongs to at a glance.
- **Filter Bar**: Add a "Category" dropdown filter to the top search bar, so you can easily filter the dashboard to only show your "Video cables".

## Verification Plan
1. Apply the database change.
2. Update the API endpoints.
3. Update the UI and verify that you can add an accessory with a category, and that it displays and filters correctly.
