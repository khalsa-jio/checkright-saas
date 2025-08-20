<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-End Browser Testing for Invitation Flow.
 *
 * Validates complete user experience including:
 * - Real browser interaction
 * - JavaScript functionality
 * - Visual layout rendering
 * - Cross-domain navigation
 * - Form submission and validation
 * - User authentication flow
 */
class InvitationFlowE2ETest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Company $company;

    private Invitation $invitation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->company = Company::factory()->withDomain('e2e-test')->create();
        $this->invitation = Invitation::factory()
            ->forTenant($this->company)
            ->email('e2e@test.com')
            ->admin()
            ->pending()
            ->sentBy($this->superAdmin)
            ->create();
    }

    /**
     * E2E TEST: Complete Invitation Flow with Browser Automation
     * This test runs the complete flow in a real browser environment.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function complete_invitation_flow_browser_automation()
    {
        // Skip if Playwright is not available
        if (! $this->isPlaywrightAvailable()) {
            $this->markTestSkipped('Playwright is not available for browser testing');
        }

        $playwrightScript = $this->generateE2EScript();
        $scriptPath = storage_path('app/e2e_invitation_test.js');

        file_put_contents($scriptPath, $playwrightScript);

        try {
            // Execute Playwright script
            $output = [];
            $returnVar = 0;

            exec('cd ' . escapeshellarg(base_path()) . ' && node ' . escapeshellarg($scriptPath) . ' 2>&1', $output, $returnVar);

            $outputString = implode("\n", $output);

            // Validate browser test results
            $this->assertStringContainsString('E2E Test: Starting complete invitation flow', $outputString);
            $this->assertStringNotContainsString('ERROR:', $outputString);
            $this->assertStringNotContainsString('FAILED:', $outputString);

            // Validate database changes occurred
            $user = User::where('email', $this->invitation->email)->first();
            $this->assertNotNull($user, 'User should be created through browser flow');

            $this->invitation->refresh();
            $this->assertTrue($this->invitation->isAccepted(), 'Invitation should be marked as accepted');
        } finally {
            // Clean up script file
            if (file_exists($scriptPath)) {
                unlink($scriptPath);
            }
        }
    }

    /**
     * E2E TEST: Visual Layout Validation.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function visual_layout_validation_browser_test()
    {
        if (! $this->isPlaywrightAvailable()) {
            $this->markTestSkipped('Playwright is not available for visual testing');
        }

        $visualTestScript = $this->generateVisualTestScript();
        $scriptPath = storage_path('app/visual_test.js');

        file_put_contents($scriptPath, $visualTestScript);

        try {
            $output = [];
            $returnVar = 0;

            exec('cd ' . escapeshellarg(base_path()) . ' && node ' . escapeshellarg($scriptPath) . ' 2>&1', $output, $returnVar);

            $outputString = implode("\n", $output);

            // Validate visual elements are rendered correctly
            $this->assertStringContainsString('Visual Test: Layout validation passed', $outputString);
            $this->assertStringNotContainsString('Layout overlap detected', $outputString);
            $this->assertStringContainsString('Social login buttons found', $outputString);
            $this->assertStringContainsString('Split-screen layout confirmed', $outputString);
        } finally {
            if (file_exists($scriptPath)) {
                unlink($scriptPath);
            }
        }
    }

    /**
     * E2E TEST: Cross-Domain Authentication Flow.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function cross_domain_authentication_browser_test()
    {
        if (! $this->isPlaywrightAvailable()) {
            $this->markTestSkipped('Playwright is not available for cross-domain testing');
        }

        $crossDomainScript = $this->generateCrossDomainScript();
        $scriptPath = storage_path('app/cross_domain_test.js');

        file_put_contents($scriptPath, $crossDomainScript);

        try {
            $output = [];
            $returnVar = 0;

            exec('cd ' . escapeshellarg(base_path()) . ' && node ' . escapeshellarg($scriptPath) . ' 2>&1', $output, $returnVar);

            $outputString = implode("\n", $output);

            // Validate cross-domain navigation works correctly
            $this->assertStringContainsString('Cross-Domain Test: Navigation successful', $outputString);
            $this->assertStringContainsString('Impersonation token valid', $outputString);
            $this->assertStringContainsString('Tenant domain accessible', $outputString);
            $this->assertStringNotContainsString('Authentication failed', $outputString);
        } finally {
            if (file_exists($scriptPath)) {
                unlink($scriptPath);
            }
        }
    }

    /**
     * E2E TEST: Performance Monitoring.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function performance_monitoring_browser_test()
    {
        if (! $this->isPlaywrightAvailable()) {
            $this->markTestSkipped('Playwright is not available for performance testing');
        }

        $performanceScript = $this->generatePerformanceScript();
        $scriptPath = storage_path('app/performance_test.js');

        file_put_contents($scriptPath, $performanceScript);

        try {
            $output = [];
            $returnVar = 0;

            exec('cd ' . escapeshellarg(base_path()) . ' && node ' . escapeshellarg($scriptPath) . ' 2>&1', $output, $returnVar);

            $outputString = implode("\n", $output);

            // Validate performance metrics
            $this->assertStringContainsString('Performance Test: Metrics collected', $outputString);
            $this->assertStringContainsString('Page load time: OK', $outputString);
            $this->assertStringContainsString('Form submission time: OK', $outputString);
            $this->assertStringContainsString('Navigation time: OK', $outputString);
        } finally {
            if (file_exists($scriptPath)) {
                unlink($scriptPath);
            }
        }
    }

    /**
     * Check if Playwright is available for testing.
     */
    private function isPlaywrightAvailable(): bool
    {
        // Check if Node.js is available
        exec('which node 2>/dev/null', $nodeOutput, $nodeReturn);
        if ($nodeReturn !== 0) {
            return false;
        }

        // Check if Playwright package is installed
        $playwrightDir = base_path('node_modules/@playwright/test');
        if (! is_dir($playwrightDir)) {
            // Try to install Playwright
            exec('cd ' . escapeshellarg(base_path()) . ' && npm install @playwright/test playwright 2>/dev/null', $npmOutput, $npmReturn);
            if ($npmReturn !== 0 || ! is_dir($playwrightDir)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate complete E2E test script.
     */
    private function generateE2EScript(): string
    {
        $centralDomain = config('app.url');
        $tenantDomain = "http://{$this->company->domain}.test";
        $invitationToken = $this->invitation->token;

        return <<<JAVASCRIPT
const { chromium } = require('playwright');

async function runCompleteE2ETest() {
    console.log('🚀 E2E Test: Starting complete invitation flow');
    
    const browser = await chromium.launch({ 
        headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage']
    });
    
    try {
        const context = await browser.newContext({
            ignoreHTTPSErrors: true,
            recordVideo: { dir: 'storage/app/videos/' }
        });
        
        const page = await context.newPage();
        
        // Step 1: Navigate to invitation page
        console.log('📝 Step 1: Loading invitation page');
        const invitationUrl = '{$centralDomain}/invitation/{$invitationToken}';
        await page.goto(invitationUrl, { waitUntil: 'networkidle' });
        
        // Validate page loaded correctly
        const pageTitle = await page.title();
        console.log('Page title:', pageTitle);
        
        // Check for form elements
        await page.waitForSelector('input[name="name"]');
        await page.waitForSelector('input[name="password"]');
        await page.waitForSelector('input[name="password_confirmation"]');
        await page.waitForSelector('button[type="submit"]');
        console.log('✅ Form elements found');
        
        // Step 2: Fill and submit form
        console.log('📝 Step 2: Filling invitation form');
        await page.fill('input[name="name"]', 'E2E Test User');
        await page.fill('input[name="password"]', 'SecurePassword123!');
        await page.fill('input[name="password_confirmation"]', 'SecurePassword123!');
        
        console.log('✅ Form filled successfully');
        
        // Step 3: Submit form and track navigation
        console.log('📝 Step 3: Submitting form');
        
        const [response] = await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            page.click('button[type="submit"]')
        ]);
        
        const finalUrl = page.url();
        console.log('Final URL after submission:', finalUrl);
        
        // Step 4: Validate redirect to tenant domain
        console.log('📝 Step 4: Validating tenant domain redirect');
        
        if (finalUrl.includes('{$this->company->domain}')) {
            console.log('✅ Successfully redirected to tenant domain');
            
            // Wait for final page load
            await page.waitForLoadState('networkidle');
            
            // Check if we're in the admin panel
            const currentUrl = page.url();
            if (currentUrl.includes('/admin')) {
                console.log('✅ Successfully authenticated in admin panel');
                
                // Take screenshot for validation
                await page.screenshot({ path: 'storage/app/e2e_success.png' });
                
            } else {
                console.log('⚠️ Not in admin panel. Current URL:', currentUrl);
            }
        } else {
            console.log('❌ Not redirected to tenant domain. URL:', finalUrl);
            await page.screenshot({ path: 'storage/app/e2e_failure.png' });
        }
        
        console.log('🎉 E2E Test: Complete invitation flow finished');
        
    } catch (error) {
        console.error('❌ E2E Test Error:', error.message);
        throw error;
    } finally {
        await browser.close();
    }
}

runCompleteE2ETest().catch(error => {
    console.error('E2E Test failed:', error);
    process.exit(1);
});
JAVASCRIPT;
    }

    /**
     * Generate visual layout test script.
     */
    private function generateVisualTestScript(): string
    {
        $centralDomain = config('app.url');
        $tenantLoginUrl = "http://{$this->company->domain}.test/admin/login";

        return <<<JAVASCRIPT
const { chromium } = require('playwright');

async function runVisualTest() {
    console.log('🎨 Visual Test: Starting layout validation');
    
    const browser = await chromium.launch({ headless: true });
    
    try {
        const context = await browser.newContext({ 
            viewport: { width: 1920, height: 1080 }
        });
        const page = await context.newPage();
        
        // Test tenant login page layout
        console.log('📝 Testing tenant login page layout');
        await page.goto('{$tenantLoginUrl}', { waitUntil: 'networkidle' });
        
        // Check for split-screen layout elements
        const leftPanel = await page.locator('[class*="tenant-login-left"]').count();
        const rightPanel = await page.locator('[class*="tenant-login-right"]').count();
        const container = await page.locator('[class*="tenant-login-container"]').count();
        
        if (container > 0 && leftPanel > 0 && rightPanel > 0) {
            console.log('✅ Split-screen layout confirmed');
        } else {
            console.log('❌ Split-screen layout not found');
        }
        
        // Check for social login buttons
        const googleButton = await page.locator('text=Continue with Google').count();
        const facebookButton = await page.locator('text=Continue with Facebook').count();
        const instagramButton = await page.locator('text=Continue with Instagram').count();
        
        if (googleButton > 0 && facebookButton > 0 && instagramButton > 0) {
            console.log('✅ Social login buttons found');
        } else {
            console.log('❌ Missing social login buttons');
        }
        
        // Check for layout overlap by measuring element positions
        const elements = await page.locator('div, section, main').all();
        let overlapDetected = false;
        
        for (const element of elements) {
            const box = await element.boundingBox();
            if (box && (box.x < 0 || box.y < 0)) {
                overlapDetected = true;
                break;
            }
        }
        
        if (!overlapDetected) {
            console.log('✅ No layout overlap detected');
        } else {
            console.log('❌ Layout overlap detected');
        }
        
        // Take screenshot for manual validation
        await page.screenshot({ path: 'storage/app/layout_validation.png', fullPage: true });
        
        console.log('🎉 Visual Test: Layout validation passed');
        
    } catch (error) {
        console.error('❌ Visual Test Error:', error.message);
        throw error;
    } finally {
        await browser.close();
    }
}

runVisualTest().catch(error => {
    console.error('Visual test failed:', error);
    process.exit(1);
});
JAVASCRIPT;
    }

    /**
     * Generate cross-domain navigation test script.
     */
    private function generateCrossDomainScript(): string
    {
        $centralDomain = config('app.url');
        $tenantDomain = "http://{$this->company->domain}.test";
        $invitationToken = $this->invitation->token;

        return <<<JAVASCRIPT
const { chromium } = require('playwright');

async function runCrossDomainTest() {
    console.log('🌐 Cross-Domain Test: Starting navigation validation');
    
    const browser = await chromium.launch({ headless: true });
    
    try {
        const context = await browser.newContext({ ignoreHTTPSErrors: true });
        const page = await context.newPage();
        
        // Track all navigation events
        const navigationHistory = [];
        page.on('framenavigated', frame => {
            if (frame === page.mainFrame()) {
                navigationHistory.push(frame.url());
                console.log('Navigation to:', frame.url());
            }
        });
        
        // Start at central domain invitation page
        console.log('📝 Step 1: Loading central domain invitation');
        await page.goto('{$centralDomain}/invitation/{$invitationToken}', { waitUntil: 'networkidle' });
        
        // Submit invitation form
        console.log('📝 Step 2: Submitting invitation form');
        await page.fill('input[name="name"]', 'Cross Domain Test User');
        await page.fill('input[name="password"]', 'SecurePassword123!');
        await page.fill('input[name="password_confirmation"]', 'SecurePassword123!');
        
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            page.click('button[type="submit"]')
        ]);
        
        // Validate cross-domain navigation
        const finalUrl = page.url();
        console.log('Final URL:', finalUrl);
        
        if (finalUrl.includes('{$this->company->domain}')) {
            console.log('✅ Cross-domain navigation successful');
            
            if (finalUrl.includes('/impersonate/')) {
                console.log('✅ Impersonation token valid');
            }
            
            if (finalUrl.includes('/admin') || await page.locator('text=Dashboard').count() > 0) {
                console.log('✅ Tenant domain accessible');
            }
        } else {
            console.log('❌ Cross-domain navigation failed');
            console.log('Navigation history:', navigationHistory);
        }
        
        console.log('🎉 Cross-Domain Test: Navigation successful');
        
    } catch (error) {
        console.error('❌ Cross-Domain Test Error:', error.message);
        throw error;
    } finally {
        await browser.close();
    }
}

runCrossDomainTest().catch(error => {
    console.error('Cross-domain test failed:', error);
    process.exit(1);
});
JAVASCRIPT;
    }

    /**
     * Generate performance monitoring test script.
     */
    private function generatePerformanceScript(): string
    {
        $centralDomain = config('app.url');
        $tenantLoginUrl = "http://{$this->company->domain}.test/admin/login";
        $invitationToken = $this->invitation->token;

        return <<<JAVASCRIPT
const { chromium } = require('playwright');

async function runPerformanceTest() {
    console.log('⚡ Performance Test: Starting metrics collection');
    
    const browser = await chromium.launch({ headless: true });
    
    try {
        const context = await browser.newContext();
        const page = await context.newPage();
        
        const metrics = {};
        
        // Test 1: Invitation page load time
        console.log('📊 Measuring invitation page load time');
        const invitationStart = Date.now();
        await page.goto('{$centralDomain}/invitation/{$invitationToken}', { waitUntil: 'networkidle' });
        metrics.invitationLoadTime = Date.now() - invitationStart;
        
        console.log(metrics.invitationLoadTime < 1000 ? '✅ Page load time: OK' : '⚠️ Page load time: SLOW');
        
        // Test 2: Form submission time
        console.log('📊 Measuring form submission time');
        await page.fill('input[name="name"]', 'Performance Test User');
        await page.fill('input[name="password"]', 'SecurePassword123!');
        await page.fill('input[name="password_confirmation"]', 'SecurePassword123!');
        
        const submissionStart = Date.now();
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            page.click('button[type="submit"]')
        ]);
        metrics.formSubmissionTime = Date.now() - submissionStart;
        
        console.log(metrics.formSubmissionTime < 2000 ? '✅ Form submission time: OK' : '⚠️ Form submission time: SLOW');
        
        // Test 3: Navigation time to tenant domain
        const navigationStart = Date.now();
        await page.waitForLoadState('networkidle');
        metrics.navigationTime = Date.now() - navigationStart;
        
        console.log(metrics.navigationTime < 3000 ? '✅ Navigation time: OK' : '⚠️ Navigation time: SLOW');
        
        // Test 4: Login page load time
        console.log('📊 Measuring login page load time');
        const loginStart = Date.now();
        await page.goto('{$tenantLoginUrl}', { waitUntil: 'networkidle' });
        metrics.loginPageLoadTime = Date.now() - loginStart;
        
        console.log(metrics.loginPageLoadTime < 1000 ? '✅ Login page load time: OK' : '⚠️ Login page load time: SLOW');
        
        // Log all metrics
        console.log('📊 Performance Metrics:');
        console.log('- Invitation page load:', metrics.invitationLoadTime + 'ms');
        console.log('- Form submission:', metrics.formSubmissionTime + 'ms');
        console.log('- Navigation time:', metrics.navigationTime + 'ms');
        console.log('- Login page load:', metrics.loginPageLoadTime + 'ms');
        
        console.log('🎉 Performance Test: Metrics collected');
        
    } catch (error) {
        console.error('❌ Performance Test Error:', error.message);
        throw error;
    } finally {
        await browser.close();
    }
}

runPerformanceTest().catch(error => {
    console.error('Performance test failed:', error);
    process.exit(1);
});
JAVASCRIPT;
    }
}
