<?php

it('can boot the application successfully', function () {
    expect(app())->toBeInstanceOf(\Illuminate\Foundation\Application::class);
});

it('has filament installed', function () {
    expect(class_exists(\Filament\FilamentServiceProvider::class))->toBeTrue();
});

it('has pest configured', function () {
    expect(function_exists('test'))->toBeTrue();
    expect(function_exists('it'))->toBeTrue();
});

it('has tenancy package installed', function () {
    expect(class_exists(\Stancl\Tenancy\TenancyServiceProvider::class))->toBeTrue();
});

it('has horizon installed', function () {
    expect(class_exists(\Laravel\Horizon\HorizonServiceProvider::class))->toBeTrue();
});

it('has activity log installed', function () {
    expect(class_exists(\Spatie\Activitylog\ActivitylogServiceProvider::class))->toBeTrue();
});

it('has correct app configuration', function () {
    expect(config('app.name'))->toBe('CheckRight');
    expect(config('app.env'))->toBe('testing');
});
