# Feature: Certificates

Course completion certificates prove a learner finished all required steps and can be viewed or downloaded as PDF.

## Sub-features

- **Certificate page**: congratulations page after course completion
- **Certificate preview**: HTML view of certificate in browser
- **PDF download**: generate and download certificate as PDF file
- **Certificate uniqueness**: each completion has unique certificate ID
- **Signature display**: configurable signature lines on certificate
- **Template customization**: uses certificate-builder package for layouts
- **PDF rendering**: Cloudflare Browser Rendering or Browsershot (local)
- **Signed URLs**: certificates can have time-limited signed download URLs

## How to get to it (user POV)

1. Complete all required steps in a course
2. After final step, redirected to `/lms/{course-slug}/completed`
3. Completed page shows:
   - "Congratulations" message
   - Course name and completion date
   - "View Certificate" button → opens HTML view
   - "Download Certificate" button → triggers PDF download

**Direct access**:
- View: `GET /lms/certificates/{course}/{user}` (requires auth)
- Download: `GET /lms/certificates/{course}/download` (requires auth)

**From dashboard**:
- Completed courses show "View Certificate" link on card

## Driving it with Pest/Playwright

### Pest (backend logic)

Test certificate generation:

```php
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Tests\TestUser;

test('certificate view renders after course completion', function () {
    $user = TestUser::create([...]);
    $course = Course::factory()->create([
        'certificate_template_id' => 1, // Assumes certificate template exists
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = Step::factory()->create(['lesson_id' => $lesson->id]);
    
    // Complete the step
    $step->complete($user);
    
    // Mark course as completed
    $course->users()->attach($user->id, ['completed_at' => now()]);
    
    $this->actingAs($user);
    
    $response = $this->get(route('filament-lms::certificates.show', [
        'course' => $course->id,
        'user' => $user->id,
    ]));
    
    $response->assertSuccessful();
    $response->assertSee($course->name);
    $response->assertSee($user->name);
});

test('certificate PDF downloads', function () {
    // ... setup course and user as above ...
    
    $this->actingAs($user);
    
    $response = $this->get(route('filament-lms::certificates.download', [
        'course' => $course->id,
    ]));
    
    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'application/pdf');
});
```

### Playwright (UI)

Complete course and download certificate:

```javascript
// Complete all steps in course (see step-completion.md)
// ... navigate through and complete steps ...

// Should redirect to completion page
await page.waitForURL(/\/lms\/intro-to-filament-lms\/completed/);

// Verify completion message
await expect(page.locator('text=Congratulations')).toBeVisible();
await expect(page.locator('text=Introduction to Filament LMS')).toBeVisible();

// Capture completion page screenshot
await page.screenshot({ path: 'evidence/course-completed.png' });

// Click "View Certificate"
await page.click('text=View Certificate');

// Wait for certificate page to load
await page.waitForSelector('text=Certificate of Completion', { state: 'visible' });

// Capture certificate HTML view
await page.screenshot({ path: 'evidence/certificate-view.png', fullPage: true });

// Go back and download PDF
await page.goBack();
const [download] = await Promise.all([
  page.waitForEvent('download'),
  page.click('text=Download Certificate')
]);

// Save PDF to evidence directory
await download.saveAs('evidence/certificate.pdf');
```

**Key selectors**:
- Completion page: look for heading "Congratulations" or similar
- View button: `a:has-text("View Certificate")` or `[href*="certificates"]`
- Download button: `a:has-text("Download Certificate")` or `[href*="download"]`
- Certificate content: varies by template, look for course name and user name

## Gotchas

1. **Certificate page 404 "Template not found"**:
   - Course must have `certificate_template_id` set
   - Certificate template must exist in `certificate_templates` table (from certificate-builder package)
   - Legacy courses may use `award` field instead → migrate to certificate-builder

2. **PDF download fails with 500 error**:
   - Check PDF driver configuration in `config/filament-lms.php`:
     - `browsershot`: requires Node + Puppeteer locally
     - `cloudflare`: requires `CLOUDFLARE_ACCOUNT_ID` and `CLOUDFLARE_BROWSER_RENDERING_API_TOKEN` env vars
   - Test Browsershot: `php artisan tinker` → `\Spatie\Browsershot\Browsershot::url('http://example.com')->save('/tmp/test.pdf')`
   - If Puppeteer missing: `npm install puppeteer`

3. **Certificate shows wrong data**:
   - Template tokens resolved from `['course' => $course, 'user' => $user]` context
   - Check certificate-builder token set matches course's token set (default: `'course'`)
   - Verify template placeholders like `{user.name}`, `{course.name}` match token names

4. **Signature lines don't appear**:
   - Check `config('filament-lms.certificate_show_signatures')` is `true`
   - Template must include signature markup (certificate-builder layout)

5. **Certificate ID missing**:
   - Check `config('filament-lms.certificate_show_id')` is `true`
   - ID generated from `lms_course_user` pivot record ID or similar unique field

6. **Evaluation blocks certificate**:
   - If course has `evaluation_course_id`, learner must complete evaluation first
   - Certificate page redirects to "Complete Evaluation" prompt
   - After evaluation done, certificate unlocks

7. **PDF layout broken (landscape mode, no backgrounds)**:
   - Verify `config/filament-lms.php` certificate settings:
     - `'landscape' => true` for horizontal layout
     - `'print_background' => true` to include CSS backgrounds
   - Browsershot options: check `browsershot.wait_until_network_idle` for dynamic content

8. **Signed URLs expiring too soon**:
   - Check `signed_url_expiration_hours` in config (default: 24 hours)
   - Signed URLs prevent unauthorized access to certificates
   - If URL expired, regenerate via certificate page "View Certificate" button

9. **Multi-tenancy breaks certificate URLs**:
   - With tenancy enabled, routes become `/lms/{tenant}/certificates/...`
   - Ensure tenant context passed to route generation
   - Check `Filament::getTenant()` returns correct tenant

**Preconditions for testing**:
- Course has certificate template assigned (or legacy award Blade)
- User completed all required steps
- `lms_course_user` record exists with `completed_at` timestamp
- Certificate-builder package installed and migrated
- PDF driver dependencies installed (Browsershot: Node + Puppeteer, or Cloudflare creds)

**Side effects to verify**:
- Database: `lms_course_user.completed_at` set
- File system: PDF generated (temporary or cached)
- Download: browser receives `Content-Disposition: attachment` header with PDF
- View: HTML certificate renders with correct data
