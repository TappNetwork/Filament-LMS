# Plan: Add Optional Custom Preview Image to Document

## Goal
Allow users to upload an optional custom preview image for a Document. If provided, this image will be used as the preview; otherwise, the system will fall back to the generated preview.

## Steps

### 1. Database
- use Spatie MediaLibrary to store the preview image in a separate collection (e.g., `preview`).

### 2. Model (`Document.php`)
- Update the model to handle the custom preview image:
  - Add logic to retrieve the custom preview image from the `preview` media collection if it exists.
  - Fallback to the generated preview if not.

### 3. Resource Form (`DocumentResource.php`)
- Update the form to allow uploading an optional preview image (using `SpatieMediaLibraryFileUpload` for the `preview` collection).

### 4. Preview Logic (View/Component)
- Update any logic or views that display the document preview to use the custom preview image if available, otherwise use the generated one.

### 5. (Optional) Validation/UI
- Make sure the preview image is optional and clearly labeled in the UI.

---

**Files to Change:**
- `src/Models/Document.php`
- `src/Resources/DocumentResource.php`
- Any view/component that displays the document preview

**Notes:**
- Using Spatie MediaLibrary for the preview image is preferred for consistency and flexibility.
- No breaking changes for existing documents. 