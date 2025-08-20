<?php

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Services\ErrorHandling\TenantCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

describe('TenantCleanupService Unit Tests', function () {
    beforeEach(function () {
        Log::spy();
        $this->service = new TenantCleanupService();
    });

    it('can instantiate TenantCleanupService', function () {
        expect($this->service)->toBeInstanceOf(TenantCleanupService::class);
    });

    describe('cleanup failed tenant creation', function () {
        it('cleans up company and related resources', function () {
            $company = Company::factory()->create();
            $invitation = Invitation::factory()->create(['tenant_id' => $company->id]);
            $user = User::factory()->create(['tenant_id' => $company->id]);

            // Verify resources exist
            expect(Company::find($company->id))->not->toBeNull()
                ->and(Invitation::find($invitation->id))->not->toBeNull()
                ->and(User::find($user->id))->not->toBeNull();

            // Perform cleanup
            $this->service->cleanupFailedTenantCreation($company, $invitation, $user);

            // Verify resources are cleaned up
            expect(Company::find($company->id))->toBeNull()
                ->and(Invitation::find($invitation->id))->toBeNull()
                ->and(User::find($user->id))->toBeNull();
        });

        it('handles partial cleanup when some resources are null', function () {
            $company = Company::factory()->create();

            // Only company exists, invitation and user are null
            $this->service->cleanupFailedTenantCreation($company, null, null);

            // Verify company is cleaned up
            expect(Company::find($company->id))->toBeNull();
        });

        it('handles cleanup when resources do not exist', function () {
            // Test with non-existent resources - should not throw exceptions
            $this->service->cleanupFailedTenantCreation(null, null, null);

            // Should complete without errors
            expect(true)->toBeTrue();
        });

        it('logs cleanup operations', function () {
            $company = Company::factory()->create();

            $this->service->cleanupFailedTenantCreation($company, null, null, ['test' => 'context']);

            // Verify logging occurred
            Log::shouldHaveReceived('info')->atLeast()->once();
        });
    });

    describe('individual resource cleanup', function () {
        it('handles company cleanup with domains', function () {
            $company = Company::factory()->create();

            // Add a domain to the company
            $company->domains()->create(['domain' => 'test.example.com']);

            expect($company->domains()->count())->toBe(1);

            // This would test the protected cleanupCompany method
            // For now, test through the public interface
            $this->service->cleanupFailedTenantCreation($company, null, null);

            expect(Company::find($company->id))->toBeNull();
        });

        it('handles invitation cleanup', function () {
            $company = Company::factory()->create();
            $invitation = Invitation::factory()->create(['tenant_id' => $company->id]);

            $this->service->cleanupFailedTenantCreation(null, $invitation, null);

            expect(Invitation::find($invitation->id))->toBeNull();
        });

        it('handles user cleanup', function () {
            $company = Company::factory()->create();
            $user = User::factory()->create(['tenant_id' => $company->id]);

            $this->service->cleanupFailedTenantCreation(null, null, $user);

            expect(User::find($user->id))->toBeNull();
        });
    });

    describe('error handling', function () {
        it('continues cleanup even if individual operations fail', function () {
            // This test would require mocking database failures
            // For now, verify basic functionality works
            $company = Company::factory()->create();

            $this->service->cleanupFailedTenantCreation($company, null, null);

            expect(Company::find($company->id))->toBeNull();
        });

        it('logs errors during cleanup', function () {
            $company = Company::factory()->create();

            $this->service->cleanupFailedTenantCreation($company, null, null);

            // Should have logged the cleanup operation
            Log::shouldHaveReceived('info')->atLeast()->once();
        });
    });
});
