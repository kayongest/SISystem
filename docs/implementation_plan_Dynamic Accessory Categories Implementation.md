# Dynamic Accessory Categories Implementation

To give you complete control over your accessory categories (and fix the edit error you're seeing), we'll implement a dedicated Category Management system.

## Proposed Changes

### 1. Fix "Invalid Accessory ID" Error
- **Bug Fix**: There's a duplicate JavaScript handler for the "Edit" button that is causing the ID to be lost when the form submits. We will clean up the JavaScript in `accessories.php` to ensure the Accessory ID is always passed correctly when saving edits.

### 2. Database Update
- Create a new `accessory_categories` table in your database to store category names.
- Pre-populate it with the categories you requested: `Video Cable`, `Audio Cable`, and `Power Cable`.

### 3. API Updates
- Create new API endpoints (`api/accessories/categories_create.php`, `categories_delete.php`, etc.) to handle adding and removing categories.
- Update `accessories.php` to pull from this new database table instead of the hardcoded system categories.

### 4. UI Updates (`accessories.php`)
- **Category Dropdowns**: Change the category input in the "Add" and "Edit" modals into strict dropdowns (`<select>`) that only show your specific accessory categories.
- **Manage Categories Button**: Add a new "Manage Categories" button next to "Add Accessory".
- **Category Management Modal**: Clicking this button will open a new popup where you can view your list of categories, add new ones (e.g. "Networking Cables"), and delete ones you no longer need.

## Verification Plan
1. Ensure the "Invalid Accessory ID" error is resolved when editing an accessory.
2. Verify the Category dropdowns only show `Video Cable`, `Audio Cable`, and `Power Cable`.
3. Verify that adding a new category through the "Manage Categories" modal makes it instantly available in the dropdowns.
