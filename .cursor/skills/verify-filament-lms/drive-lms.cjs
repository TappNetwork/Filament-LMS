const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  console.log('=== LOGIN ===');
  await page.goto('http://localhost:8000/lms/login');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1000);
  
  await page.screenshot({ path: '/workspace/.cursor/skills/verify-filament-lms/evidence/01-login-page.png', fullPage: true });
  console.log('✓ Login page screenshot');

  await page.fill('input[wire\\:model="data.email"]', 'admin@example.com');
  await page.fill('input[wire\\:model="data.password"]', 'password');
  await page.waitForTimeout(500);
  
  console.log('Submit login...');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  
  console.log('After login URL:', page.url());
  if (page.url().includes('/login')) {
    console.log('❌ Login failed!');
    process.exit(1);
  }
  console.log('✓ Login successful');

  console.log('\n=== DASHBOARD ===');
  await page.screenshot({ path: '/workspace/.cursor/skills/verify-filament-lms/evidence/02-dashboard.png', fullPage: true });
  console.log('✓ Dashboard screenshot');
  
  const courseVisible = await page.locator('text=Introduction to Filament LMS').count();
  console.log('Course visible:', courseVisible > 0);
  
  if (courseVisible > 0) {
    console.log('Click on course...');
    await page.click('text=Introduction to Filament LMS');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);
    
    console.log('Course page URL:', page.url());
    await page.screenshot({ path: '/workspace/.cursor/skills/verify-filament-lms/evidence/03-course-page.png', fullPage: true });
    console.log('✓ Course page screenshot');
    
    const stepVisible = await page.locator('text=Welcome to the LMS').count();
    console.log('Step visible:', stepVisible > 0);
    
    if (stepVisible > 0) {
      console.log('\n=== STEP VIEW ===');
      await page.click('text=Welcome to the LMS');
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1500);
      
      console.log('Step page URL:', page.url());
      await page.screenshot({ path: '/workspace/.cursor/skills/verify-filament-lms/evidence/04-step-view.png', fullPage: true });
      console.log('✓ Step view screenshot');
      
      // Check for complete button
      const completeButton = await page.locator('button:has-text("Complete"), button:has-text("Mark")').count();
      console.log('Complete button visible:', completeButton > 0);
      
      if (completeButton > 0) {
        console.log('\n=== COMPLETE STEP ===');
        await page.locator('button:has-text("Complete"), button:has-text("Mark")').first().click();
        await page.waitForTimeout(2000);
        await page.screenshot({ path: '/workspace/.cursor/skills/verify-filament-lms/evidence/05-after-complete.png', fullPage: true });
        console.log('✓ After completion screenshot');
      }
    }
  }

  await browser.close();
  console.log('\n✅ Verification complete!');
})();
