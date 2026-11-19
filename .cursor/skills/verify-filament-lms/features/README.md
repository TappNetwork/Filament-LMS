# Filament-LMS Feature Map

This directory documents the top user-facing features of the Filament-LMS package for verification purposes.

## Features Documented

1. **dashboard.md** - Course library landing page where learners browse available courses
2. **course-viewer.md** - In-course navigation and step viewing experience
3. **step-completion.md** - Marking steps complete and tracking progress
4. **certificates.md** - Course completion certificates (view and download)
5. **admin-resources.md** - Filament admin panel for managing courses, lessons, and steps

## Purpose

Each feature file describes:
- **Sub-features**: components or capabilities within the feature
- **How to get to it (user POV)**: navigation path from entry point
- **Driving it with Pest/Playwright**: harness-specific selectors and interactions
- **Gotchas**: known issues, preconditions, or environmental quirks

## Usage

When verifying a change:
1. Identify which features are affected
2. Read the relevant feature files
3. Execute the driving steps to exercise the feature
4. Capture evidence (screenshots, test output)
5. Add new gotchas if discovered during verification

## Feature Coverage Status

- ✅ **Dashboard**: documented, can be driven with Playwright
- ✅ **Course Viewer**: documented, requires seeded course data
- ✅ **Step Completion**: documented, backend tested via Pest
- ✅ **Certificates**: documented, requires certificate-builder package
- ✅ **Admin Resources**: documented, Filament resource pages

## Missing from Map

These features exist but are not yet documented:
- Course evaluations (post-course feedback forms)
- SCORM package import
- Multi-tenancy (tenant-scoped courses)
- User group assignments (criteria-based enrollment)
- Reporting page (admin analytics)
- MCP server tools (AI course authoring)

Add files for these when verification requires them.
