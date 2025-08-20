<?php

namespace Tests\Feature;

use App\Models\DeviceRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'admin',
            'tenant_id' => 'test-tenant-123',
        ]);

        $this->apiKey = 'test-mobile-api-key-12345';
        config(['sanctum-mobile.api_key.key' => $this->apiKey]);
    }

    public function test_can_register_device(): void
    {
        $deviceData = [
            'device_id' => 'test-device-12345',
            'device_info' => [
                'platform' => 'ios',
                'model' => 'iPhone 14',
                'version' => '16.0',
                'app_version' => '1.0.0',
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->postJson('/api/mobile/devices/register', $deviceData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Device registered successfully',
                'device' => [
                    'device_id' => 'test-device-12345',
                    'is_trusted' => false,
                ],
            ])
            ->assertJsonStructure([
                'device_secret',
            ]);

        $this->assertDatabaseHas('device_registrations', [
            'user_id' => $this->user->id,
            'device_id' => 'test-device-12345',
            'is_trusted' => false,
        ]);
    }

    public function test_cannot_register_duplicate_device(): void
    {
        // Register device first time
        DeviceRegistration::create([
            'user_id' => $this->user->id,
            'device_id' => 'duplicate-device',
            'device_info' => ['platform' => 'ios'],
            'registered_at' => now(),
        ]);

        $deviceData = [
            'device_id' => 'duplicate-device',
            'device_info' => ['platform' => 'android'],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->postJson('/api/mobile/devices/register', $deviceData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['device_id']);
    }

    public function test_can_list_user_devices(): void
    {
        // Create multiple devices for user
        DeviceRegistration::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        // Create device for another user (should not appear)
        $otherUser = User::factory()->create(['tenant_id' => 'test-tenant-123']);
        DeviceRegistration::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->getJson('/api/mobile/devices');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'devices' => [
                    '*' => [
                        'id',
                        'device_id',
                        'device_info_string',
                        'is_trusted',
                        'is_trust_expired',
                        'registered_at',
                        'last_used_at',
                    ],
                ],
                'total',
                'max_devices',
            ]);

        $this->assertCount(3, $response->json('devices'));
    }

    public function test_can_trust_device(): void
    {
        $device = DeviceRegistration::factory()->create([
            'user_id' => $this->user->id,
            'device_id' => 'trust-device-123',
            'is_trusted' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->postJson("/api/mobile/devices/{$device->device_id}/trust");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Device trusted successfully',
            ])
            ->assertJsonStructure(['trusted_until']);

        $device->refresh();
        $this->assertTrue($device->is_trusted);
        $this->assertNotNull($device->trusted_at);
        $this->assertNotNull($device->trusted_until);
    }

    public function test_cannot_trust_nonexistent_device(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->postJson('/api/mobile/devices/nonexistent-device/trust');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['device']);
    }

    public function test_can_revoke_device_trust(): void
    {
        $device = DeviceRegistration::factory()->create([
            'user_id' => $this->user->id,
            'device_id' => 'revoke-trust-device',
            'is_trusted' => true,
            'trusted_at' => now(),
            'trusted_until' => now()->addDays(30),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->deleteJson("/api/mobile/devices/{$device->device_id}/trust");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Device trust revoked successfully',
            ]);

        $device->refresh();
        $this->assertFalse($device->is_trusted);
        $this->assertNull($device->trusted_at);
        $this->assertNull($device->trusted_until);
    }

    public function test_can_remove_device(): void
    {
        $device = DeviceRegistration::factory()->create([
            'user_id' => $this->user->id,
            'device_id' => 'remove-device-123',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->deleteJson("/api/mobile/devices/{$device->device_id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Device removed successfully',
            ]);

        $this->assertDatabaseMissing('device_registrations', [
            'id' => $device->id,
        ]);
    }

    public function test_can_get_device_security_status(): void
    {
        $device = DeviceRegistration::factory()->create([
            'user_id' => $this->user->id,
            'device_id' => 'status-device-123',
            'is_trusted' => true,
            'trusted_until' => now()->addDays(30),
            'last_used_at' => now()->subHours(1),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders([
                'X-API-Key' => $this->apiKey,
                'X-Device-Id' => $device->device_id,
            ])
            ->getJson('/api/mobile/devices/security-status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'device_id',
                'is_registered',
                'is_trusted',
                'is_trust_expired',
                'trust_expires_at',
                'last_used_at',
                'security_score',
                'recommendations',
            ]);

        $this->assertTrue($response->json('is_registered'));
        $this->assertTrue($response->json('is_trusted'));
        $this->assertFalse($response->json('is_trust_expired'));
    }

    public function test_device_registration_requires_authentication(): void
    {
        $deviceData = [
            'device_id' => 'test-device-unauth',
            'device_info' => ['platform' => 'ios'],
        ];

        $response = $this->withHeaders(['X-API-Key' => $this->apiKey])
            ->postJson('/api/mobile/devices/register', $deviceData);

        $response->assertStatus(401);
    }

    public function test_device_registration_requires_valid_api_key(): void
    {
        $deviceData = [
            'device_id' => 'test-device-invalid-key',
            'device_info' => ['platform' => 'ios'],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-API-Key' => 'invalid-key'])
            ->postJson('/api/mobile/devices/register', $deviceData);

        $response->assertStatus(401);
    }

    public function test_device_registration_validates_input(): void
    {
        $testCases = [
            // Invalid device_id
            [
                'data' => ['device_id' => ''],
                'errors' => ['device_id'],
            ],
            // Device_id too short
            [
                'data' => ['device_id' => '123'],
                'errors' => ['device_id'],
            ],
            // Invalid platform
            [
                'data' => [
                    'device_id' => 'valid-device-id',
                    'device_info' => ['platform' => 'windows'],
                ],
                'errors' => ['device_info.platform'],
            ],
        ];

        foreach ($testCases as $testCase) {
            $response = $this->actingAs($this->user, 'sanctum')
                ->withHeaders(['X-API-Key' => $this->apiKey])
                ->postJson('/api/mobile/devices/register', $testCase['data']);

            $response->assertStatus(422)
                ->assertJsonValidationErrors($testCase['errors']);
        }
    }

    public function test_device_limit_enforcement(): void
    {
        // Set device limit to 2 for testing
        config(['sanctum-mobile.device_binding.max_devices_per_user' => 2]);

        // Create 2 existing devices
        DeviceRegistration::factory()->count(2)->create([
            'user_id' => $this->user->id,
        ]);

        // Try to register a 3rd device (should remove oldest)
        $deviceData = [
            'device_id' => 'new-device-over-limit',
            'device_info' => ['platform' => 'ios'],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->postJson('/api/mobile/devices/register', $deviceData);

        $response->assertStatus(201);

        // Should still have only 2 devices total
        $this->assertEquals(2, DeviceRegistration::where('user_id', $this->user->id)->count());

        // New device should exist
        $this->assertDatabaseHas('device_registrations', [
            'user_id' => $this->user->id,
            'device_id' => 'new-device-over-limit',
        ]);
    }
}
