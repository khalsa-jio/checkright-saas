<?php

use Illuminate\Support\Facades\Config;

describe('Project Architecture Validation', function () {
    it('validates monorepo structure exists', function () {
        // Check if key monorepo files exist
        $rootPackageJson = base_path('../../package.json');
        $sharedPackage = base_path('../../packages/shared/package.json');

        expect(file_exists($rootPackageJson))->toBeTrue('Root package.json should exist');
        expect(file_exists($sharedPackage))->toBeTrue('Shared package should exist');
    });

    it('validates all service providers are registered', function () {
        $providers = Config::get('app.providers', []);

        // Check Filament is available
        expect(class_exists(\Filament\FilamentServiceProvider::class))->toBeTrue();

        // Check Tenancy is available
        expect(class_exists(\Stancl\Tenancy\TenancyServiceProvider::class))->toBeTrue();

        // Check Horizon is available
        expect(class_exists(\Laravel\Horizon\HorizonServiceProvider::class))->toBeTrue();

        // Check ActivityLog is available
        expect(class_exists(\Spatie\Activitylog\ActivitylogServiceProvider::class))->toBeTrue();
    });

    it('validates core configuration files exist and are valid', function () {
        // Check tenancy config
        expect(config('tenancy.tenant_model'))->not()->toBeNull();
        expect(config('tenancy.central_domains'))->toBeArray();

        // Check activity log config
        expect(config('activitylog.enabled'))->toBeTrue();
        expect(config('activitylog.default_log_name'))->toBe('default');

        // Check horizon config exists
        expect(config('horizon'))->toBeArray();
    });

    it('validates test environment is properly configured', function () {
        expect(config('app.env'))->toBe('testing');
        expect(config('database.default'))->toBe('sqlite');
        expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    });

    it('validates pest testing framework is functional', function () {
        // Test that pest functions are available
        expect(function_exists('test'))->toBeTrue();
        expect(function_exists('it'))->toBeTrue();
        expect(function_exists('describe'))->toBeTrue();
        expect(function_exists('expect'))->toBeTrue();
    });
});

describe('Code Quality Standards', function () {
    it('validates pint configuration exists', function () {
        $pintConfig = base_path('pint.json');
        expect(file_exists($pintConfig))->toBeTrue('Pint configuration should exist');

        $config = json_decode(file_get_contents($pintConfig), true);
        expect($config['preset'])->toBe('laravel');
        expect($config['rules'])->toBeArray();
    });

    it('validates environment configuration', function () {
        expect(config('app.name'))->toBe('CheckRight');
        expect(config('app.key'))->not()->toBeEmpty();
        expect(config('app.debug'))->toBeBool();
    });
});

describe('Package Integration Tests', function () {
    it('can instantiate filament panel', function () {
        $panels = \Filament\Facades\Filament::getPanels();
        expect($panels)->not()->toBeEmpty();
        expect($panels)->toHaveKey('admin');

        $defaultPanel = \Filament\Facades\Filament::getDefaultPanel();
        expect($defaultPanel)->not()->toBeNull();
        expect($defaultPanel->getId())->toBe('admin');
    });

    it('validates tenancy bootstrappers are configured', function () {
        $bootstrappers = config('tenancy.bootstrappers');
        expect($bootstrappers)->toBeArray();
        expect(count($bootstrappers))->toBeGreaterThan(0);
    });
});
