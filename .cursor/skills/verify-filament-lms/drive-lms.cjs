const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  console.log('=== LOGIN ===');
  await page.goto('http://localhost:8000/lms/login');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'evidence/01-login-page.png', fullPage: true });
  console.log('✓ Login page');

  await page.fill('input[wire\\:model="data.email"]', 'admin@example.com');
  await page.fill('input[wire\\:model="data.password"]', 'password');
  await page.screenshot({ path: 'evidence/02-login-filled.png' });
  
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
  await page.screenshot({ path: 'evidence/03-after-login.png', fullPage: true });
  console.log('✓ Dashboard screenshot');
  
  const courseCount = await page.locator('text=Introduction to Filament LMS').count();
  console.log(`✓ Course visible on dashboard: ${courseCount > 0 ? 'YES' : 'NO'} (${courseCount} matches)`);
  
  const courseLink = await page.locator('a:has-text("Introduction to Filament LMS")').first();
  const link = await courseLink.getAttribute('href');
  console.log('Course link:', link);

  // === STEP PAGE ===
  console.log('\n=== STEP PAGE (via navigation) ===');
  
  // Track all responses to see redirect chain
  const redirects = [];
  page.on('response', response => {
    const status = response.status();
    if (status >= 300 && status < 400) {
      const location = response.headers()['location'] || '(no location header)';
      redirects.push(`${status} ${response.url()} -> ${location}`);
    }
  });
  
  try {
    // Navigate directly to the link URL
    await page.goto(link, { waitUntil: 'networkidle', timeout: 10000 });
    await page.waitForTimeout(2000);
    await page.screenshot({ path: 'evidence/04-step-page.png', fullPage: true });
    console.log('✓ Step page screenshot');
    console.log('Final URL:', page.url());
    
    if (redirects.length > 0) {
      console.log('\n=== REDIRECT CHAIN ===');
      redirects.forEach((r, i) => console.log(`${i + 1}. ${r}`));
    }
  } catch (error) {
    console.log('❌ Error navigating to step:', error.message);
    if (redirects.length > 0) {
      console.log('\n=== REDIRECT CHAIN (partial) ===');
      redirects.forEach((r, i) => console.log(`${i + 1}. ${r}`));
    }
    console.log('Current URL:', page.url());
    await page.screenshot({ path: 'evidence/04-error-state.png', fullPage: true });
  }

  await page.screenshot({ path: 'evidence/05-final-state.png', fullPage: true });
  await browser.close();
  console.log('\n✅ Verification complete!');
})();
