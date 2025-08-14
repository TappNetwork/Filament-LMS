# Plan: Fire CourseCreated Event on Course Creation

## Goal
Fire a `CourseCreated` event whenever a new course is created. The event should contain the `Course` model and its data.

## Steps

1. **Create the Event Class**
   - File: `src/Events/CourseCreated.php`
   - The event should accept a `Course` model instance in its constructor and expose it as a public property.

2. **Fire the Event When a Course is Created**
   - Option A: Use the `created` Eloquent model event in `Course.php` (preferred for decoupling).
   - Option B: Fire the event directly in the course creation logic (e.g., controller or service).
   - File(s): `src/Models/Course.php` (and/or wherever courses are created)

3. **(Optional) Create/Update Event Listener(s)**
   - If any listeners should respond to this event, update or create them as needed.
   - File(s): `src/Listeners/` (if needed)

4. **Testing**
   - Add or update tests to ensure the event is fired with the correct data.
   - File(s): `tests/`

## Affected Files
- `src/Events/CourseCreated.php` (new)
- `src/Models/Course.php` (edit)
- (Optional) `src/Listeners/` (new/edit)
- (Optional) `tests/` (edit)

---
**Let me know if you want to proceed with this plan or make any changes.** 