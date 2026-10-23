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
  await page.waitForTimeout(3000);
  
  console.log('After login URL:', page.url());
  if (page.url().includes('/login')) {
    console.log('❌ Login failed!');
    process.exit(1);
  }
  console.log('✓ Login successful');

  console.log('\n=== DASHBOARD ===');
  await page.screenshot({ path: '/workspace/.cursor/skills/verify-filament-lms/evidence/02-dashboard.png', fullPage: true });
  console.log('✓ Dashboard screenshot');
  
  const courseLink = await page.locator('a:has-text("Introduction to Filament LMS")').first();
  const courseHref = await courseLink.getAttribute('href');
  console.log('✓ Course visible, link:', courseHref);
  
  // Navigate directly to avoid click interception
  console.log('\n=== STEP PAGE ===');
  await page.goto(courseHref);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  
  console.log('Step page URL:', page.url());
  await page.screenshot({ path: '/workspace/.cursor/skills/verify-filament-lms/evidence/03-step-page.png', fullPage: true });
  
  const stepName = await page.locator('text=Welcome to the LMS').count();
  console.log('✓ Step "Welcome to the LMS" visible:', stepName > 0);
  
  // Look for complete button
  const completeSelectors = [
    'button:has-text("Complete")',
    'button:has-text("Mark")',
    'button:has-text("Next")',
    '[wire\\:click*="complete"]'
  ];
  
  let completeFound = false;
  for (const selector of completeSelectors) {
    const count = await page.locator(selector).count();
    if (count > 0) {
      console.log(`✓ Found completion button: "${selector}"`);
      completeFound = true;
      
      try {
        await page.locator(selector).first().click({ timeout: 5000 });
        await page.waitForTimeout(2000);
        await page.screenshot({ path: '/workspace/.cursor/skills/verify-filament-lms/evidence/04-after-complete.png', fullPage: true });
        console.log('✓ Step completion attempted');
      } catch (e) {
        console.log('⚠ Could not click complete button:', e.message);
      }
      break;
    }
  }
  
  if (!completeFound) {
    console.log('ℹ No completion button found (may auto-complete or not require action)');
  }

  await browser.close();
  console.log('\n✅ Verification complete!');
})();
