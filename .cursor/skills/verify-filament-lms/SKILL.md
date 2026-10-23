---
name: verify-filament-lms
description: Verify Filament-LMS package changes by launching the test LMS panel web UI, navigating through courses and lessons, and capturing screenshots. Use after implementing features or fixing bugs to prove the package works end-to-end.
---

# Filament-LMS Verification Skill

Verifies the Filament-LMS package (a Filament/Laravel LMS plugin) by starting a local testbench server, seeding test data, navigating the student-facing LMS panel web UI, and capturing screenshots as evidence.

## Surface

**Primary**: Student-facing LMS panel web UI at `/lms` (Livewire/Filament pages)
- Dashboard: course library with progress indicators
- Course viewer: sidebar navigation with lessons/steps, video player, step completion tracking
- Certificate page: course completion certificates

**Secondary surfaces** (not verified by this skill):
- Admin panel resources (Courses, Lessons, Steps, Videos, etc.)
- Artisan commands (`lms:mcp-token`, `filament-lms:reconcile-user-group-memberships`)
- MCP server tools for AI-driven course authoring
- REST API routes (certificates, SCORM packages)

## Launch

**Start the LMS**:
```bash
# From workspace root
php vendor/bin/testbench migrate --force --seed
php vendor/bin/testbench serve --host=0.0.0.0 --port=8000
```

The server is ready when you see:
```
INFO  Server running on [http://127.0.0.1:8000].
```

**Seed test data** (run after migrations):
```bash
bash .cursor/skills/verify-filament-lms/seed-verification-data.sh
```

This creates:
- Test user: `admin@example.com` / `password`
- Demo course "Introduction to Filament LMS" with 2 lessons and 3 video steps
- Public course (no enrollment required)

**Access the LMS**:
- URL: `http://localhost:8000/lms`
- Login with `admin@example.com` / `password`
- Browser required (Chrome/Chromium for screenshots)

**Teardown**:
```bash
# Stop the server (Ctrl+C if running in foreground)
# Or if backgrounded:
pkill -f "php.*testbench serve"

# Clean database (optional, for fresh start)
rm -f database/database.sqlite vendor/orchestra/testbench-core/laravel/database/database.sqlite
```

## Doctor

**Read-only health check**:
```bash
# 1. Server is running
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/lms | grep -q "200\|302" && echo "✓ Server responding" || echo "✗ Server down"

# 2. Database has courses
php vendor/bin/testbench tinker --execute="echo 'Courses: ' . \Tapp\FilamentLms\Models\Course::count() . PHP_EOL;"

# 3. Test user exists
php vendor/bin/testbench tinker --execute="echo 'Test user: ' . (\Tapp\FilamentLms\Tests\TestUser::where('email', 'admin@example.com')->exists() ? 'exists' : 'missing') . PHP_EOL;"

# 4. Port 8000 owned by testbench
lsof -ti:8000 | xargs -I {} ps -p {} -o comm= | grep -q testbench && echo "✓ Port owned by testbench" || echo "✗ Port not owned by testbench"
```

## Drive

**Harness**: Pest feature tests + Playwright for browser automation

The package ships with Pest feature tests covering:
- Course model and relationships
- Step completion tracking
- Certificate generation
- Livewire components (video player, step navigation)

**Browser verification recipe** (manual or via Playwright):

1. **Navigate to LMS dashboard**:
   - Visit `http://localhost:8000/lms`
   - Should redirect to `/lms/login` if not authenticated
   - Login with `admin@example.com` / `password`
   - Should land on `/lms` (Dashboard)

2. **Verify course library**:
   - Dashboard should list "Introduction to Filament LMS" course card
   - Card shows course name, description, lesson count
   - Progress indicator shows 0% (no steps completed)

3. **Enter course**:
   - Click course card
   - Should navigate to `/lms/intro-to-filament-lms/getting-started/getting-started-welcome`
   - Sidebar shows:
     - Lesson: "Getting Started" (expanded)
       - Step: "Welcome" (active)
       - Step: "Installation" (locked)
     - Lesson: "Advanced Features" (collapsed, locked)

4. **View step**:
   - Main content area shows:
     - Step title "Welcome"
     - Embedded YouTube video player
     - "Mark as Complete" button at bottom

5. **Complete step**:
   - Click "Mark as Complete"
   - Page refreshes, advances to next step "Installation"
   - Sidebar updates: "Welcome" shows checkmark, "Installation" now active

6. **Navigate manually**:
   - Click "Welcome" in sidebar (should allow revisiting completed steps)
   - Confirm video loads, completion checkmark persists

7. **Complete remaining steps**:
   - Navigate through and complete "Installation" and "Admin Panel Overview"
   - After final step, should redirect to `/lms/intro-to-filament-lms/completed`

8. **Certificate page**:
   - Completed page shows:
     - Congratulations message
     - Certificate preview or download link
     - "View Certificate" / "Download Certificate" buttons
   - Click "View Certificate" → opens certificate in new tab
   - Certificate shows user name, course name, completion date

**Stable selectors** (from codebase inspection):
- Course card: `.filament-card` with course name in heading
- Sidebar step: `.fi-sidebar-item` with step slug
- Mark complete button: `button:has-text("Mark as Complete")` or `[wire\\:click*="markComplete"]`
- Certificate download: `a[href*="certificates"][href*="download"]`

## Evidence

**What to capture**:
1. **Dashboard screenshot**: course library with demo course visible
2. **Step viewer screenshot**: active step with video player and sidebar navigation
3. **Completion screenshot**: certificate page after completing all steps
4. **Certificate PDF**: downloaded PDF file (proves generation works)

**Where evidence goes**:
```
/workspace/.cursor/skills/verify-filament-lms/evidence/
├── dashboard.png
├── step-viewer.png
├── course-completed.png
└── certificate.pdf
```

**Proof standards**:
- Exercise the real user path: login → browse → enter course → complete steps → view certificate
- Capture the action AND resulting state: screenshot shows UI state after interaction
- Verify side effects: check database for completed steps, generated certificate records
- Use production code paths: no test-only endpoints, mocks only at existing boundaries (HTTP, external APIs)

**Capture commands** (using Playwright or similar):
```javascript
// Example Playwright script (not included in skill)
await page.goto('http://localhost:8000/lms');
await page.fill('input[name="email"]', 'admin@example.com');
await page.fill('input[name="password"]', 'password');
await page.click('button[type="submit"]');
await page.screenshot({ path: 'evidence/dashboard.png' });

await page.click('text=Introduction to Filament LMS');
await page.screenshot({ path: 'evidence/step-viewer.png' });

await page.click('text=Mark as Complete');
// ... continue through steps ...
await page.screenshot({ path: 'evidence/course-completed.png' });
```

## Cleanup

**Stop processes**:
```bash
# Stop testbench server
pkill -f "php.*testbench serve"

# Confirm process stopped
! pgrep -f "php.*testbench serve" && echo "✓ Server stopped" || echo "✗ Server still running"
```

**Clean scratch state** (leaves evidence intact):
```bash
# Remove database (SQLite in-memory or file-based)
rm -f database/database.sqlite vendor/orchestra/testbench-core/laravel/database/database.sqlite

# Remove testbench cache
rm -rf vendor/orchestra/testbench-core/laravel/bootstrap/cache/*
```

**Never remove**:
- Evidence directory (`.cursor/skills/verify-filament-lms/evidence/`)
- This skill directory
- Package source code

## Helpers

**Seed script**: `.cursor/skills/verify-filament-lms/seed-verification-data.sh`
- Executable: `chmod +x .cursor/skills/verify-filament-lms/seed-verification-data.sh`
- Invocation: `bash .cursor/skills/verify-filament-lms/seed-verification-data.sh` (from workspace root)
- Creates test user, demo course with lessons and video steps
- Idempotent: safe to run multiple times (clears existing test data first)

**Doctor script**: `.cursor/skills/verify-filament-lms/doctor.sh`
- Quick health check: server running, database populated, test user exists
- Invocation: `bash .cursor/skills/verify-filament-lms/doctor.sh`

## Environment Isolation

**Can two instances run side by side?**
No (without extra config). Testbench uses:
- Port 8000 (configurable with `--port`)
- SQLite database at `vendor/orchestra/testbench-core/laravel/database/database.sqlite` (shared location)

**Workaround for parallel instances**:
1. Use different ports: `--port=8001`, `--port=8002`, etc.
2. Use separate databases via `DB_DATABASE` env var
3. Or run in separate tmux sessions with `cd` to different package clones

For this skill, assume single-instance sequential verification.

## Gotchas

- **Testbench doesn't support live reload**: changes to package files require restarting the server
- **Filament asset compilation**: if JS/CSS changes don't appear, check if package has a build step (`npm run build`)
- **Certificate generation requires Browsershot**: needs Node + Puppeteer for PDF rendering. If missing, certificate download will fail with 500 error. Verify with `node --version` and `npm list puppeteer`.
- **Video embeds**: YouTube/Vimeo URLs must be embed format (`/embed/` or `player.vimeo.com`), not watch URLs
- **Auth middleware**: all routes require authentication. If redirected to login unexpectedly, check user session.
- **Migrations**: testbench uses an in-memory database by default. Run migrations before seeding: `php vendor/bin/testbench migrate --force`
