const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  console.log('=== LOGIN ===');
  await page.goto('http://localhost:8000/lms/login');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1000);
  
  await page.screenshot({ path: '/workspace/.cursor/skills/verify-filament-lms/evidence/01-login-page.png', fullPage: true });
  console.log('✓ Login page');

  await page.fill('input[wire\\:model="data.email"]', 'admin@example.com');
  await page.fill('input[wire\\:model="data.password"]', 'password');
  await page.waitForTimeout(500);
  
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  
  console.log('After login URL:', page.url());
  if (page.url().includes('/login')) {
    console.log('❌ Login failed!');
    process.exit(1);
  }
  console.log('✓ Login successful');

  await page.screenshot({ path: '/workspace/.cursor/skills/verify-filament-lms/evidence/02-dashboard.png', fullPage: true });
  console.log('✓ Dashboard screenshot');

  console.log('\n=== NAVIGATE TO STEP ===');
  const stepUrl = 'http://localhost:8000/lms/courses/intro-to-filament-lms/getting-started/welcome';
  console.log('Going to:', stepUrl);
  await page.goto(stepUrl);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  
  console.log('Step page URL:', page.url());
  await page.screenshot({ path: '/workspace/.cursor/skills/verify-filament-lms/evidence/03-step-view.png', fullPage: true });
  console.log('✓ Step view screenshot');

  const pageTitle = await page.title();
  console.log('Page title:', pageTitle);
  
  const stepName = await page.locator('text=Welcome to the LMS').count();
  console.log('Step name visible:', stepName > 0);

  await browser.close();
  console.log('\n✅ Verification complete!');
})();
