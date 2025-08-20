<?php

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Services\TenantCreationService;
use Illuminate\Support\Facades\Mail;

describe('Story 1.2: Super Admin Tenant Creation', function () {
    beforeEach(function () {
        Mail::fake();
    });

    it('can create a Company model', function () {
        $company = Company::create([
            'name' => 'Test Company',
            'domain' => 'test-company',
        ]);

        expect($company->name)->toBe('Test Company');
        expect($company->domain)->toBe('test-company');
        expect($company->id)->toBeString(); // UUID
    });

    it('can create a User with tenant fields', function () {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'tenant_id' => 'test-tenant-id',
            'role' => 'admin',
        ]);

        expect($user->tenant_id)->toBe('test-tenant-id');
        expect($user->role)->toBe('admin');
        expect($user->isAdmin())->toBeTrue();
        expect($user->isManager())->toBeFalse();
    });

    it('can create an Invitation model', function () {
        $company = Company::create([
            'name' => 'Test Company',
            'domain' => 'test-company',
        ]);

        $user = User::factory()->create();

        $invitation = Invitation::create([
            'tenant_id' => $company->id,
            'email' => 'invite@example.com',
            'role' => 'admin',
            'invited_by' => $user->id,
        ]);

        expect($invitation->email)->toBe('invite@example.com');
        expect($invitation->role)->toBe('admin');
        expect($invitation->token)->toBeString();
        expect($invitation->isValid())->toBeTrue();
    });

    it('validates invitation expiry', function () {
        $company = Company::create([
            'name' => 'Test Company',
            'domain' => 'test-company',
        ]);

        $user = User::factory()->create();

        $expiredInvitation = Invitation::create([
            'tenant_id' => $company->id,
            'email' => 'expired@example.com',
            'role' => 'admin',
            'invited_by' => $user->id,
            'expires_at' => now()->subDay(),
        ]);

        expect($expiredInvitation->isExpired())->toBeTrue();
        expect($expiredInvitation->isValid())->toBeFalse();
    });

    it('can check user roles correctly', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $operator = User::factory()->create(['role' => 'operator']);

        expect($admin->isAdmin())->toBeTrue();
        expect($admin->isManager())->toBeFalse();
        expect($admin->isOperator())->toBeFalse();

        expect($manager->isAdmin())->toBeFalse();
        expect($manager->isManager())->toBeTrue();
        expect($manager->isOperator())->toBeFalse();

        expect($operator->isAdmin())->toBeFalse();
        expect($operator->isManager())->toBeFalse();
        expect($operator->isOperator())->toBeTrue();
    });

    it('can create tenant with admin invitation using service', function () {
        $service = app(TenantCreationService::class);

        $result = $service->createTenantWithAdmin(
            [
                'name' => 'Acme Corporation',
                'domain' => 'acme-corp',
            ],
            [
                'email' => 'admin@acme.com',
            ]
        );

        expect($result)->toHaveKey('company');
        expect($result)->toHaveKey('invitation');

        $company = $result['company'];
        $invitation = $result['invitation'];

        expect($company)->toBeInstanceOf(Company::class);
        expect($company->name)->toBe('Acme Corporation');
        expect($company->domain)->toBe('acme-corp');

        expect($invitation)->toBeInstanceOf(Invitation::class);
        expect($invitation->email)->toBe('admin@acme.com');
        expect($invitation->role)->toBe('admin');
        expect($invitation->tenant_id)->toBe($company->id);

        // Verify email was sent
        Mail::assertSent(\App\Mail\InvitationMail::class);
    });

    it('can accept invitation and create user', function () {
        $service = app(TenantCreationService::class);

        // Create company and invitation
        $result = $service->createTenantWithAdmin(
            ['name' => 'Test Company', 'domain' => 'test-co'],
            ['email' => 'admin@test.com']
        );

        $invitation = $result['invitation'];

        // Accept invitation
        $user = $service->acceptInvitation($invitation->token, [
            'name' => 'John Admin',
            'password' => 'password123',
        ]);

        expect($user)->toBeInstanceOf(User::class);
        expect($user->name)->toBe('John Admin');
        expect($user->email)->toBe('admin@test.com');
        expect($user->role)->toBe('admin');
        expect($user->tenant_id)->toBe($invitation->tenant_id);

        // Verify invitation is marked as accepted
        $invitation->refresh();
        expect($invitation->isAccepted())->toBeTrue();
    });

    it('validates TenantCreationService class exists', function () {
        $service = app(TenantCreationService::class);
        expect($service)->toBeInstanceOf(TenantCreationService::class);
    });
});
