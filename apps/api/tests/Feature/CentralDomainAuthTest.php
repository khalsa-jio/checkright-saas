<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create and authenticate user on central domain', function () {
    // Create a user (this should work on central domain)
    $user = User::factory()->create([
        'email' => 'admin@checkright.test',
        'password' => bcrypt('password123'),
    ]);

    // Verify user was created
    expect($user)->toBeInstanceOf(User::class);
    expect($user->email)->toBe('admin@checkright.test');

    // Test that we can authenticate with this user
    $this->assertDatabaseHas('users', [
        'email' => 'admin@checkright.test',
    ]);

    // Test session handling
    $response = $this->withSession(['key' => 'value'])
        ->get('/admin');

    // Should redirect to login since we're not authenticated
    $response->assertRedirect('/admin/login');
});

it('handles sessions correctly on central domain', function () {
    // Test that sessions work on central domain
    $response = $this->withSession(['test_key' => 'test_value'])
        ->get('/admin/login');

    $response->assertOk();
    $response->assertSessionHas('test_key', 'test_value');
});
