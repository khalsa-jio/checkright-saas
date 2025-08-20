<?php

use App\Models\Company;
use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Simple Unit Tests', function () {
    it('can create a company', function () {
        $company = Company::factory()->create([
            'name' => 'Test Company',
            'domain' => 'testcompany123',
        ]);

        expect($company)->toBeInstanceOf(Company::class)
            ->and($company->name)->toBe('Test Company')
            ->and($company->domain)->toBe('testcompany123');
    });

    it('can create an invitation', function () {
        $company = Company::factory()->create();
        $invitation = Invitation::factory()->create([
            'tenant_id' => $company->id,
            'email' => 'test@example.com',
        ]);

        expect($invitation)->toBeInstanceOf(Invitation::class)
            ->and($invitation->email)->toBe('test@example.com')
            ->and($invitation->tenant_id)->toBe($company->id);
    });

    it('can create expired invitation', function () {
        $invitation = Invitation::factory()->expired()->create();

        expect($invitation->expires_at->isBefore(now()))->toBeTrue();
    });

    it('can create accepted invitation', function () {
        $invitation = Invitation::factory()->accepted()->create();

        expect($invitation->accepted_at)->not->toBeNull();
    });
});
