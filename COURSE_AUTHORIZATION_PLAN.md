# Course Authorization Plan

This document outlines the plan for implementing admin-controlled course access for users in the LMS package.

---

## 1. Course Access Control via Pivot Table

1. Create a `lms_course_user` pivot table to determine which users have access to which courses.
2. Table should include at least: `user_id`, `course_id`, and timestamps.

---

## 2. Filament Plugin: User-Course Management

A. **Plugin Approach:**
   1. adds a relation manager for courses to the User resource.
   2. Allow bulk assignment of courses from the User resource via a bulk action.
   3. Include install steps in the README for:
      a. Registering the relation manager on the User resource.
      b. Registering the bulk action on the User resource.

---

## 3. Assigning Courses Programmatically

A. Add a section to the README (under Course Authorization) describing how to assign a course to a user programmatically (not through the relation manager).
B. Example: How to attach a course to a user after registration or in custom logic.

---

## 4. Migration Script for Existing Users

A. No code or script will be provided in the package for migrating existing users.
B. **Instructions for consuming project:**
   1. Describe to Cursor (or your assistant) what logic is needed to assign existing users to courses, and request a migration or Artisan command tailored to your needs.

---

## 5. Configurable Course Visibility

1. Add a config option (default: show all courses) to control whether users see all courses or only those they have access to.
2. Update course queries to respect this config.
3. Document this option and its usage in the README.

---

## 6. README Updates

1. Add a section on Course Authorization:
   a. How to change the config to lock down courses.
   b. How to register the relation manager and bulk action on the User resource.
   c. How to assign courses programmatically. 