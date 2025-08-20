<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can initialize mobile OAuth without errors', function () {
    // Configure test environment for OAuth
    config(['services.google.client_id' => 'test-client-id']);
    config(['services.google.client_secret' => 'test-client-secret']);
    config(['services.google.redirect' => 'http://localhost/auth/google/callback']);

    $response = $this->postJson('/api/mobile/oauth/google/initialize', [
        'tenant_id' => 'test-tenant',
        'device_id' => 'test-device',
        'app_version' => '1.0.0',
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'success',
        'data' => [
            'authorization_url',
            'state',
            'provider',
        ],
    ]);
});

it('returns proper error for mobile OAuth callback with invalid state', function () {
    $response = $this->postJson('/api/mobile/oauth/google/callback', [
        'code' => 'test-code',
        'state' => 'invalid-state',
    ]);

    $response->assertStatus(400);
    $response->assertJson([
        'success' => false,
        'message' => 'OAuth authentication failed',
        'error' => 'Invalid or expired state parameter',
    ]);
});

it('validates mobile OAuth callback parameters', function () {
    $response = $this->postJson('/api/mobile/oauth/google/callback', [
        'code' => 'test-code',
        // missing state
    ]);

    $response->assertStatus(400);
    $response->assertJson([
        'success' => false,
        'message' => 'OAuth authentication failed',
    ]);
});
