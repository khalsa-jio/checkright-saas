<?php

use App\Exceptions\DomainException;
use App\Exceptions\InvitationException;
use App\Exceptions\TenantCreationException;
use App\Exceptions\TenantValidationException;
use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;

uses(RefreshDatabase::class);

describe('Custom Exceptions Unit Tests', function () {
    describe('TenantCreationException', function () {
        it('can be instantiated with company and admin data', function () {
            $companyData = ['name' => 'Test Company'];
            $adminData = ['email' => 'admin@test.com'];

            $exception = new TenantCreationException(
                'Test error',
                $companyData,
                $adminData
            );

            expect($exception)->toBeInstanceOf(TenantCreationException::class)
                ->and($exception->getMessage())->toBe('Test error')
                ->and($exception->getCompanyData())->toBe($companyData)
                ->and($exception->getAdminData())->toBe($adminData);
        });

        it('removes sensitive data from admin data', function () {
            $companyData = ['name' => 'Test Company'];
            $adminData = ['email' => 'admin@test.com', 'password' => 'secret123'];

            $exception = new TenantCreationException(
                'Test error',
                $companyData,
                $adminData
            );

            $safeAdminData = $exception->getAdminData();

            expect($safeAdminData)->not->toHaveKey('password')
                ->and($safeAdminData)->toHaveKey('email')
                ->and($safeAdminData['email'])->toBe('admin@test.com');
        });
    });

    describe('TenantValidationException', function () {
        it('can be instantiated with validation errors', function () {
            $errors = new MessageBag(['domain' => ['Domain is required']]);
            $inputData = ['name' => 'Test Company'];

            $exception = new TenantValidationException('Validation failed', $errors, $inputData);

            expect($exception)->toBeInstanceOf(TenantValidationException::class)
                ->and($exception->getMessage())->toBe('Validation failed')
                ->and($exception->getValidationErrors())->toBe($errors)
                ->and($exception->getInputData())->toBe($inputData);
        });

        it('removes sensitive data from input data', function () {
            $errors = new MessageBag(['email' => ['Email is invalid']]);
            $inputData = ['email' => 'test@example.com', 'password' => 'secret123'];

            $exception = new TenantValidationException('Validation failed', $errors, $inputData);

            $safeInputData = $exception->getInputData();

            expect($safeInputData)->not->toHaveKey('password')
                ->and($safeInputData)->toHaveKey('email');
        });
    });

    describe('InvitationException', function () {
        it('can be instantiated with invitation context', function () {
            $invitation = Invitation::factory()->make();
            $context = ['reason' => 'expired'];

            $exception = new InvitationException('Invitation error', $invitation, $context);

            expect($exception)->toBeInstanceOf(InvitationException::class)
                ->and($exception->getMessage())->toBe('Invitation error')
                ->and($exception->getInvitation())->toBe($invitation)
                ->and($exception->getContext())->toBe($context);
        });

        it('can be instantiated without invitation', function () {
            $context = ['email' => 'test@example.com', 'reason' => 'invalid'];

            $exception = new InvitationException('Invitation error', null, $context);

            expect($exception)->toBeInstanceOf(InvitationException::class)
                ->and($exception->getInvitation())->toBeNull()
                ->and($exception->getContext())->toBe($context);
        });
    });

    describe('DomainException', function () {
        it('can be instantiated with domain context', function () {
            $domain = 'test.example.com';
            $operation = 'create';

            $exception = new DomainException('Domain error', $domain, $operation);

            expect($exception)->toBeInstanceOf(DomainException::class)
                ->and($exception->getMessage())->toBe('Domain error')
                ->and($exception->getDomain())->toBe($domain)
                ->and($exception->getOperation())->toBe($operation);
        });

        it('handles empty domain and operation', function () {
            $exception = new DomainException('Generic domain error');

            expect($exception)->toBeInstanceOf(DomainException::class)
                ->and($exception->getDomain())->toBe('')
                ->and($exception->getOperation())->toBe('');
        });
    });
});
