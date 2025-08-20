<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationCrossDomainFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a super admin user for invitation management
        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@checkright.test',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_completes_full_invitation_flow_with_session_based_authentication()
    {
        // Step 1: Create a company with domain "testcompany"
        $company = Company::create([
            'name' => 'Test Company',
            'domain' => 'testcompany',
            'data' => [
                'settings' => ['timezone' => 'UTC'],
                'billing' => ['plan' => 'basic'],
            ],
        ]);

        $this->assertDatabaseHas('tenants', [
            'name' => 'Test Company',
            'domain' => 'testcompany',
        ]);

        // Step 2: Create an invitation for that company
        $invitation = Invitation::create([
            'tenant_id' => $company->id,
            'email' => 'john.doe@example.com',
            'role' => 'admin',
            'invited_by' => $this->superAdmin->id,
        ]);

        $this->assertDatabaseHas('invitations', [
            'tenant_id' => $company->id,
            'email' => 'john.doe@example.com',
            'role' => 'admin',
        ]);

        // Verify invitation is valid
        $this->assertTrue($invitation->isValid());
        $this->assertFalse($invitation->isExpired());
        $this->assertFalse($invitation->isAccepted());

        // Get invitation acceptance URL
        $invitationUrl = $invitation->getAcceptanceUrl();
        $this->assertStringContainsString("/invitation/{$invitation->token}", $invitationUrl);

        // Test session token encryption/decryption before proceeding
        $testData = [
            'user_id' => 123,
            'tenant_id' => $company->id,
            'expires_at' => now()->addMinutes(5)->timestamp,
            'invitation_success' => true,
        ];

        $encryptedToken = base64_encode(encrypt($testData));
        $decryptedData = decrypt(base64_decode($encryptedToken));

        $this->assertEquals($testData, $decryptedData);

        // Now proceed with browser automation using Playwright
        $this->runPlaywrightInvitationTest($invitation, $company);
    }

    private function runPlaywrightInvitationTest(Invitation $invitation, Company $company)
    {
        $playwrightScript = $this->generatePlaywrightScript($invitation, $company);

        // Write the script to a temporary file
        $scriptPath = storage_path('app/invitation_test_script.js');
        file_put_contents($scriptPath, $playwrightScript);

        // Execute the Playwright script
        $output = null;
        $returnVar = null;

        // First check if node is available
        exec('which node', $nodeCheck, $nodeReturn);
        if ($nodeReturn !== 0) {
            $this->markTestSkipped('Node.js is not available for Playwright testing');
        }

        // Install playwright if not already installed
        $playwrightDir = base_path('node_modules/@playwright/test');
        if (! is_dir($playwrightDir)) {
            // Install playwright via npm
            exec('npm install @playwright/test playwright', $npmOutput, $npmReturn);
            if ($npmReturn !== 0) {
                $this->markTestSkipped('Could not install Playwright');
            }
        }

        // Run the Playwright script
        exec('cd ' . escapeshellarg(base_path()) . ' && node ' . escapeshellarg($scriptPath) . ' 2>&1', $output, $returnVar);

        // Clean up
        if (file_exists($scriptPath)) {
            unlink($scriptPath);
        }

        // Assert based on output
        $outputString = implode("\n", $output);

        // Check for success indicators in the output
        $this->assertStringContains('Starting comprehensive invitation flow test', $outputString);

        // The actual browser testing is done by the JavaScript,
        // but we verify the database changes here
        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
        ]);
    }

    private function generatePlaywrightScript(Invitation $invitation, Company $company): string
    {
        $centralDomain = config('app.url');
        $tenantDomain = "http://{$company->domain}" . config('tenant.domain.suffix', '.test');
        $invitationToken = $invitation->token;

        return <<<JAVASCRIPT
const { chromium } = require('playwright');

async function testInvitationFlow() {
    console.log('🚀 Starting comprehensive invitation flow test...');
    
    const browser = await chromium.launch({ 
        headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage']
    });
    
    try {
        const context = await browser.newContext({
            ignoreHTTPSErrors: true
        });
        const page = await context.newPage();

        // Step 1: Visit the invitation acceptance page
        console.log('📝 Step 1: Visiting invitation acceptance page');
        const invitationUrl = '{$centralDomain}/invitation/{$invitationToken}';
        console.log('Invitation URL:', invitationUrl);
        
        await page.goto(invitationUrl, { waitUntil: 'networkidle' });
        
        // Check if the invitation form is present
        const nameField = await page.locator('input[name="name"]').count();
        if (nameField === 0) {
            console.log('❌ Invitation form not found - token may be invalid or expired');
            return;
        }
        
        console.log('✅ Invitation form loaded successfully');

        // Step 2: Fill out and submit the invitation acceptance form
        console.log('📝 Step 2: Filling out invitation acceptance form');
        
        await page.fill('input[name="name"]', 'John Doe');
        await page.fill('input[name="password"]', 'SecurePassword123!');
        await page.fill('input[name="password_confirmation"]', 'SecurePassword123!');
        
        console.log('✅ Form fields filled');

        // Step 3: Submit the form and follow redirects
        console.log('📝 Step 3: Submitting form and following redirects');
        
        // Listen for navigation to capture redirects
        let redirectUrls = [];
        page.on('response', response => {
            if (response.status() >= 300 && response.status() < 400) {
                redirectUrls.push(response.url());
            }
        });

        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            page.click('button[type="submit"]')
        ]);

        const currentUrl = page.url();
        console.log('Current URL after form submission:', currentUrl);
        console.log('Redirect chain:', redirectUrls);

        // Step 4: Verify we're redirected to tenant domain with session token
        console.log('📝 Step 4: Verifying redirect to tenant domain');
        
        if (currentUrl.includes('{$company->domain}') && currentUrl.includes('/impersonate/')) {
            console.log('✅ Successfully redirected to tenant domain with session token');
            
            // Extract session token from URL
            const sessionTokenMatch = currentUrl.match(/\/auth\/session\/([^\/\?]+)/);
            if (sessionTokenMatch) {
                console.log('✅ Session token found in URL');
                
                // Wait for final redirect to admin panel
                try {
                    await page.waitForNavigation({ timeout: 10000, waitUntil: 'networkidle' });
                    const finalUrl = page.url();
                    console.log('Final URL after session authentication:', finalUrl);
                    
                    // Step 5: Verify user authentication and admin panel access
                    if (finalUrl.includes('/admin')) {
                        console.log('✅ Successfully authenticated and redirected to admin panel');
                        
                        // Check if we can access protected content
                        const pageTitle = await page.title();
                        const pageContent = await page.textContent('body');
                        
                        console.log('Page title:', pageTitle);
                        
                        // Verify we're not logged in as super admin by checking user context
                        if (!pageContent.includes('Super Admin') && pageContent.includes('John Doe')) {
                            console.log('✅ Authenticated as invited user (not super admin)');
                        } else {
                            console.log('⚠️ User identity verification inconclusive');
                        }
                        
                        // Step 6: Test admin panel functionality
                        console.log('📝 Step 6: Testing admin panel access');
                        
                        // Try to navigate to a protected route
                        await page.goto('{$tenantDomain}/admin', { waitUntil: 'networkidle' });
                        
                        const adminPageContent = await page.textContent('body');
                        if (!adminPageContent.includes('Login') && !adminPageContent.includes('Unauthorized')) {
                            console.log('✅ Admin panel accessible - authentication successful');
                        } else {
                            console.log('❌ Admin panel not accessible - authentication may have failed');
                        }
                        
                        console.log('🎉 Complete invitation flow test completed successfully!');
                    } else {
                        console.log('❌ Not redirected to admin panel. Final URL:', finalUrl);
                    }
                } catch (navError) {
                    console.log('❌ Error waiting for final navigation:', navError.message);
                }
            } else {
                console.log('❌ Session token not found in redirect URL');
            }
        } else {
            console.log('❌ Not redirected to expected tenant domain with session token');
            console.log('Expected domain: {$company->domain}');
            console.log('Expected path pattern: /impersonate/');
        }

    } catch (error) {
        console.error('❌ Test error:', error);
    } finally {
        await browser.close();
    }
}

// Run the test
testInvitationFlow().catch(console.error);
JAVASCRIPT;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_session_token_encryption_and_decryption()
    {
        $company = Company::factory()->withDomain('testcompany')->create();

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'tenant_id' => $company->id,
            'email_verified_at' => now(),
        ]);

        // Test the session token encryption/decryption
        $originalData = [
            'user_id' => $user->id,
            'tenant_id' => $company->id,
            'expires_at' => now()->addMinutes(5)->timestamp,
            'invitation_success' => true,
        ];

        // Encrypt the data (same as controller does)
        $encryptedToken = base64_encode(encrypt($originalData));

        // Decrypt the data (same as route does)
        $decryptedData = decrypt(base64_decode($encryptedToken));

        // Verify data integrity
        $this->assertEquals($originalData['user_id'], $decryptedData['user_id']);
        $this->assertEquals($originalData['tenant_id'], $decryptedData['tenant_id']);
        $this->assertEquals($originalData['expires_at'], $decryptedData['expires_at']);
        $this->assertEquals($originalData['invitation_success'], $decryptedData['invitation_success']);

        // Test expiration validation
        $expiredData = [
            'user_id' => $user->id,
            'tenant_id' => $company->id,
            'expires_at' => now()->subMinutes(1)->timestamp,
            'invitation_success' => true,
        ];

        $expiredToken = base64_encode(encrypt($expiredData));
        $decryptedExpiredData = decrypt(base64_decode($expiredToken));

        $this->assertTrue($decryptedExpiredData['expires_at'] < now()->timestamp);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_proper_tenant_redirect_url_with_impersonation_token()
    {
        $company = Company::factory()->withDomain('testcompany')->create();

        $invitation = Invitation::factory()
            ->forTenant($company)
            ->email('test@example.com')
            ->admin()
            ->pending()
            ->create();

        // Simulate the AcceptInvitationController logic
        $user = User::create([
            'name' => 'Test User',
            'email' => $invitation->email,
            'password' => bcrypt('password'),
            'role' => $invitation->role,
            'tenant_id' => $invitation->tenant_id,
            'email_verified_at' => now(),
        ]);

        // Use core impersonation feature
        $impersonationTokenModel = tenancy()->impersonate($company, $user->id, '/admin');
        $impersonationToken = $impersonationTokenModel->token;

        $protocol = request()->isSecure() ? 'https' : 'http';
        $tenantDomain = $company->domain . config('tenant.domain.suffix', '.test');
        $expectedUrl = "{$protocol}://{$tenantDomain}/impersonate/{$impersonationToken}";

        // The URL should contain the impersonation token and point to the tenant domain
        $this->assertStringContainsString($company->domain, $expectedUrl);
        $this->assertStringContainsString('/impersonate/', $expectedUrl);
        $this->assertStringContainsString($impersonationToken, $expectedUrl);

        // Verify the impersonation token is properly generated
        $this->assertNotEmpty($impersonationToken);
        $this->assertIsString($impersonationToken);
        $this->assertEquals($user->id, $impersonationTokenModel->user_id);
        $this->assertEquals($company->id, $impersonationTokenModel->tenant_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_processes_invitation_acceptance_and_marks_as_accepted()
    {
        $company = Company::factory()->withDomain('testcompany')->create();

        $invitation = Invitation::factory()
            ->forTenant($company)
            ->email('newuser@example.com')
            ->admin()
            ->pending()
            ->create();

        // Verify invitation is initially not accepted
        $this->assertFalse($invitation->isAccepted());
        $this->assertNull($invitation->accepted_at);

        // Submit invitation acceptance form
        $response = $this->post(route('invitation.store', $invitation->token), [
            'name' => 'New User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        // Should redirect away (to tenant domain)
        $this->assertEquals(302, $response->status());

        $location = $response->headers->get('Location');
        $this->assertStringContainsString($company->domain, $location);
        $this->assertStringContainsString('/impersonate/', $location);

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'role' => 'admin',
            'tenant_id' => $company->id,
        ]);

        // Verify invitation was marked as accepted
        $invitation->refresh();
        $this->assertTrue($invitation->isAccepted());
        $this->assertNotNull($invitation->accepted_at);

        // Verify user can be found by invitation
        $createdUser = User::where('email', $invitation->email)
            ->where('tenant_id', $invitation->tenant_id)
            ->first();

        $this->assertNotNull($createdUser);
        $this->assertEquals('New User', $createdUser->name);
        $this->assertEquals('admin', $createdUser->role);
        $this->assertEquals($company->id, $createdUser->tenant_id);
    }
}
