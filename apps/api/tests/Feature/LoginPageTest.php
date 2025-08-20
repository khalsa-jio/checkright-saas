<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can load the custom login page without errors', function () {
    $response = $this->get('/admin/login');

    $response->assertOk();
    $response->assertSee('Login');
    $response->assertSee('Continue with Google');
    $response->assertSee('Continue with Facebook');
    $response->assertSee('Continue with Instagram');
});

it('displays social login buttons', function () {
    $response = $this->get('/admin/login');

    $response->assertOk();
    $response->assertSee('href="' . route('tenant.auth.redirect', ['provider' => 'google']) . '"', false);
    $response->assertSee('href="' . route('tenant.auth.redirect', ['provider' => 'facebook']) . '"', false);
    $response->assertSee('href="' . route('tenant.auth.redirect', ['provider' => 'instagram']) . '"', false);
});

it('has proper social login styling', function () {
    $response = $this->get('/admin/login');

    $response->assertOk();
    $response->assertSee('bg-white', false); // Google button
    $response->assertSee('bg-[#1877F2]', false); // Facebook button
    $response->assertSee('bg-gradient-to-r from-purple-600 via-pink-600 to-orange-500', false); // Instagram button
});

it('has proper split-screen layout structure for tenant domains', function () {
    // Mock request to simulate tenant domain
    config(['app.url' => 'https://tenant.checkright.test']);

    $response = $this->get('/admin/login');

    $response->assertOk();
    $response->assertSee('tenant-login-container', false);
    $response->assertSee('tenant-login-left', false);
    $response->assertSee('tenant-login-right', false);
    $response->assertSee('Welcome Back');
    $response->assertSee('Secure Business Management');
});
