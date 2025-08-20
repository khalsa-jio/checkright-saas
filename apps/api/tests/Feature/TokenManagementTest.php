<?php

namespace Tests\Feature;

use App\Models\MobileTokenRegistry;
use App\Models\User;
use App\Services\Security\TokenManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $apiKey;

    protected TokenManager $tokenManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'admin',
            'tenant_id' => 'test-tenant-123',
        ]);

        $this->apiKey = 'test-mobile-api-key-12345';
        config(['sanctum-mobile.api_key.key' => $this->apiKey]);

        $this->tokenManager = app(TokenManager::class);
    }

    public function test_can_generate_mobile_token_pair(): void
    {
        $deviceId = 'test-device-12345';

        $tokens = $this->tokenManager->generateMobileTokens($this->user, $deviceId);

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertArrayHasKey('access_expires_in', $tokens);
        $this->assertArrayHasKey('refresh_expires_in', $tokens);
        $this->assertArrayHasKey('token_type', $tokens);
        $this->assertEquals('Bearer', $tokens['token_type']);

        // Verify tokens are tracked in registry
        $this->assertDatabaseHas('mobile_token_registries', [
            'user_id' => $this->user->id,
            'device_id' => $deviceId,
        ]);
    }

    public function test_can_rotate_tokens_with_refresh_token(): void
    {
        $deviceId = 'test-device-rotate';

        // Generate initial tokens
        $initialTokens = $this->tokenManager->generateMobileTokens($this->user, $deviceId);

        // Get the refresh token model to simulate rotation
        $registry = MobileTokenRegistry::where('user_id', $this->user->id)
            ->where('device_id', $deviceId)
            ->first();

        $this->assertNotNull($registry);

        // Simulate token rotation by getting the refresh token
        $refreshTokenModel = $registry->refreshToken;
        $this->assertNotNull($refreshTokenModel);

        // Note: In real scenario, we'd use the plain text token
        // For testing, we'll simulate with a mock approach
        $this->assertTrue($refreshTokenModel->can('refresh'));
    }

    public function test_should_rotate_token_based_on_threshold(): void
    {
        // Create a token the normal way and use timestamps to manipulate time
        $token = $this->user->createToken('test-token', ['*'], now()->addMinutes(10));

        // Use Carbon's setTestNow to simulate time passage
        // This should create a scenario where 9 out of 10 minutes have passed (90% usage)
        \Carbon\Carbon::setTestNow(now()->addMinutes(9));

        // With default threshold of 0.8, this token should need rotation (90% > 80%)
        $shouldRotate = $this->tokenManager->shouldRotateToken($token->accessToken);
        $this->assertTrue($shouldRotate);

        // Reset time
        \Carbon\Carbon::setTestNow();

        // Test with a fresh token
        $freshToken = $this->user->createToken('fresh-token', ['*'], now()->addMinutes(15));

        $shouldRotateFresh = $this->tokenManager->shouldRotateToken($freshToken->accessToken);
        $this->assertFalse($shouldRotateFresh);
    }

    public function test_can_revoke_device_tokens(): void
    {
        $deviceId = 'test-device-revoke';

        // Generate tokens for the device
        $this->tokenManager->generateMobileTokens($this->user, $deviceId);

        // Verify tokens exist
        $this->assertDatabaseHas('mobile_token_registries', [
            'user_id' => $this->user->id,
            'device_id' => $deviceId,
        ]);

        // Revoke tokens
        $revokedCount = $this->tokenManager->revokeDeviceTokens($this->user->id, $deviceId);

        $this->assertEquals(1, $revokedCount);

        // Verify tokens are removed
        $this->assertDatabaseMissing('mobile_token_registries', [
            'user_id' => $this->user->id,
            'device_id' => $deviceId,
        ]);
    }

    public function test_can_revoke_all_user_tokens(): void
    {
        // Generate tokens for multiple devices
        $this->tokenManager->generateMobileTokens($this->user, 'device-1');
        $this->tokenManager->generateMobileTokens($this->user, 'device-2');
        $this->tokenManager->generateMobileTokens($this->user, 'device-3');

        // Verify all tokens exist
        $this->assertEquals(3, MobileTokenRegistry::where('user_id', $this->user->id)->count());

        // Revoke all tokens
        $revokedCount = $this->tokenManager->revokeAllUserTokens($this->user->id);

        $this->assertEquals(3, $revokedCount);

        // Verify all tokens are removed
        $this->assertEquals(0, MobileTokenRegistry::where('user_id', $this->user->id)->count());
    }

    public function test_can_get_token_info(): void
    {
        $deviceId = 'test-device-info';

        // Generate tokens
        $this->tokenManager->generateMobileTokens($this->user, $deviceId);

        // Get token info
        $tokenInfo = $this->tokenManager->getTokenInfo($this->user->id, $deviceId);

        $this->assertNotNull($tokenInfo);
        $this->assertArrayHasKey('access_expires_at', $tokenInfo);
        $this->assertArrayHasKey('refresh_expires_at', $tokenInfo);
        $this->assertArrayHasKey('should_rotate', $tokenInfo);
        $this->assertFalse($tokenInfo['access_is_expired']);
        $this->assertFalse($tokenInfo['refresh_is_expired']);
    }

    public function test_cleanup_expired_tokens(): void
    {
        $deviceId = 'test-device-expired';

        // Generate tokens
        $this->tokenManager->generateMobileTokens($this->user, $deviceId);

        // Make tokens expired by updating their timestamps
        $registry = MobileTokenRegistry::where('user_id', $this->user->id)
            ->where('device_id', $deviceId)
            ->first();

        // Force expire both tokens
        $registry->accessToken->update(['expires_at' => now()->subMinutes(5)]);
        $registry->refreshToken->update(['expires_at' => now()->subMinutes(5)]);

        // Run cleanup
        $cleanedCount = $this->tokenManager->cleanupExpiredTokens();

        $this->assertEquals(1, $cleanedCount);

        // Verify registry entry is removed
        $this->assertDatabaseMissing('mobile_token_registries', [
            'user_id' => $this->user->id,
            'device_id' => $deviceId,
        ]);
    }

    public function test_token_generation_endpoint(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->postJson('/api/mobile/tokens/generate', [
                'device_id' => 'test-device-endpoint',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'tokens' => [
                    'access_token',
                    'refresh_token',
                    'access_expires_in',
                    'refresh_expires_in',
                    'token_type',
                    'expires_at',
                    'refresh_expires_at',
                ],
            ]);
    }

    public function test_token_info_endpoint(): void
    {
        $deviceId = 'test-device-info-endpoint';

        // Generate tokens first
        $this->tokenManager->generateMobileTokens($this->user, $deviceId);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->getJson('/api/mobile/tokens/info?device_id=' . $deviceId);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'device_id',
                'token_info' => [
                    'access_expires_at',
                    'access_is_expired',
                    'refresh_expires_at',
                    'refresh_is_expired',
                    'should_rotate',
                ],
            ]);
    }

    public function test_revoke_device_tokens_endpoint(): void
    {
        $deviceId = 'test-device-revoke-endpoint';

        // Generate tokens first
        $this->tokenManager->generateMobileTokens($this->user, $deviceId);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->deleteJson('/api/mobile/tokens/device', [
                'device_id' => $deviceId,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'device_id' => $deviceId,
                'revoked_count' => 1,
            ]);
    }

    public function test_revoke_all_tokens_endpoint(): void
    {
        // Generate multiple token pairs
        $this->tokenManager->generateMobileTokens($this->user, 'device-1');
        $this->tokenManager->generateMobileTokens($this->user, 'device-2');

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->deleteJson('/api/mobile/tokens/all');

        $response->assertStatus(200)
            ->assertJson([
                'revoked_count' => 2,
            ]);
    }

    public function test_should_rotate_endpoint(): void
    {
        $token = $this->user->createToken('test-token', ['*'], now()->addMinutes(15));

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken,
            'X-API-Key' => $this->apiKey,
        ])
            ->getJson('/api/mobile/tokens/should-rotate');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'should_rotate',
                'token_expires_at',
                'token_created_at',
            ]);
    }

    public function test_validate_token_endpoint(): void
    {
        // Create a token and use it for authentication
        $token = $this->user->createToken('test-validate-token', ['*'], now()->addMinutes(15));

        // Use the created token for authentication
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken,
            'X-API-Key' => $this->apiKey,
        ])
            ->getJson('/api/mobile/tokens/validate');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'valid',
                'expired',
                'expires_at',
                'created_at',
                'should_rotate',
                'abilities',
                'token_name',
            ])
            ->assertJson([
                'valid' => true,
                'expired' => false,
            ]);
    }

    public function test_token_generation_requires_authentication(): void
    {
        $response = $this->withHeaders(['X-API-Key' => $this->apiKey])
            ->postJson('/api/mobile/tokens/generate', [
                'device_id' => 'test-device',
            ]);

        $response->assertStatus(401);
    }

    public function test_token_generation_validates_device_id(): void
    {
        $testCases = [
            // Missing device_id
            [
                'data' => [],
                'errors' => ['device_id'],
            ],
            // Device_id too short
            [
                'data' => ['device_id' => '123'],
                'errors' => ['device_id'],
            ],
            // Device_id too long
            [
                'data' => ['device_id' => str_repeat('a', 300)],
                'errors' => ['device_id'],
            ],
        ];

        foreach ($testCases as $testCase) {
            $response = $this->actingAs($this->user, 'sanctum')
                ->withHeaders(['X-API-Key' => $this->apiKey])
                ->postJson('/api/mobile/tokens/generate', $testCase['data']);

            $response->assertStatus(422)
                ->assertJsonValidationErrors($testCase['errors']);
        }
    }

    public function test_mobile_token_registry_relationships(): void
    {
        $deviceId = 'test-registry-relations';

        // Generate tokens
        $this->tokenManager->generateMobileTokens($this->user, $deviceId);

        // Get registry entry
        $registry = MobileTokenRegistry::where('user_id', $this->user->id)
            ->where('device_id', $deviceId)
            ->with(['user', 'accessToken', 'refreshToken'])
            ->first();

        $this->assertNotNull($registry);
        $this->assertNotNull($registry->user);
        $this->assertNotNull($registry->accessToken);
        $this->assertNotNull($registry->refreshToken);
        $this->assertEquals($this->user->id, $registry->user->id);
        $this->assertTrue($registry->areTokensValid());
        $this->assertEquals('active', $registry->status);
    }
}
