<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Caching\TenantCacheService;
use Illuminate\Console\Command;

class ManageTenantCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:cache 
                           {action : The cache action to perform (clear|warmup|stats)}
                           {--company= : Specific company ID to target}
                           {--domain= : Specific domain to target}
                           {--force : Force the action without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage tenant caching (clear, warmup, or show stats)';

    protected TenantCacheService $cacheService;

    /**
     * Create a new command instance.
     */
    public function __construct(TenantCacheService $cacheService)
    {
        parent::__construct();
        $this->cacheService = $cacheService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $companyId = $this->option('company');
        $domain = $this->option('domain');
        $force = $this->option('force');

        if (! $this->cacheService->isCachingEnabled()) {
            $this->error('Tenant caching is disabled in configuration.');

            return self::FAILURE;
        }

        switch ($action) {
            case 'clear':
                return $this->handleClearAction($companyId, $domain, $force);

            case 'warmup':
                return $this->handleWarmupAction($companyId, $domain);

            case 'stats':
                return $this->handleStatsAction();

            default:
                $this->error("Invalid action '{$action}'. Available actions: clear, warmup, stats");

                return self::FAILURE;
        }
    }

    /**
     * Handle cache clear action.
     */
    protected function handleClearAction(?string $companyId, ?string $domain, bool $force): int
    {
        if ($companyId) {
            // Clear specific company cache
            if (! $force && ! $this->confirm("Clear cache for company ID {$companyId}?")) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }

            $company = $domain ? null : Company::find($companyId);
            $companyDomain = $domain ?: $company?->domain;

            $this->cacheService->invalidateCompanyCache((int) $companyId, $companyDomain);
            $this->info("Cache cleared for company ID {$companyId}");
        } elseif ($domain) {
            // Clear cache for specific domain
            if (! $force && ! $this->confirm("Clear cache for domain '{$domain}'?")) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }

            $company = Company::where('domain', $domain)->first();
            if (! $company) {
                $this->error("Company with domain '{$domain}' not found.");

                return self::FAILURE;
            }

            $this->cacheService->invalidateCompanyCache($company->id, $domain);
            $this->info("Cache cleared for domain '{$domain}'");
        } else {
            // Clear all system cache
            if (! $force && ! $this->confirm('Clear ALL tenant caches? This will affect system performance temporarily.')) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }

            $companies = Company::all();
            foreach ($companies as $company) {
                $this->cacheService->invalidateCompanyCache($company->id, $company->domain);
            }

            $this->cacheService->invalidateSystemCache();
            $this->info('All tenant caches cleared.');
        }

        return self::SUCCESS;
    }

    /**
     * Handle cache warmup action.
     */
    protected function handleWarmupAction(?string $companyId, ?string $domain): int
    {
        if ($companyId) {
            // Warmup specific company cache
            $company = Company::find($companyId);
            if (! $company) {
                $this->error("Company with ID {$companyId} not found.");

                return self::FAILURE;
            }

            $this->info("Warming up cache for company '{$company->name}' (ID: {$companyId})...");
            $this->cacheService->warmupCompanyCache($company);
            $this->info('Cache warmed up successfully.');
        } elseif ($domain) {
            // Warmup cache for specific domain
            $company = Company::where('domain', $domain)->first();
            if (! $company) {
                $this->error("Company with domain '{$domain}' not found.");

                return self::FAILURE;
            }

            $this->info("Warming up cache for domain '{$domain}'...");
            $this->cacheService->warmupCompanyCache($company);
            $this->info('Cache warmed up successfully.');
        } else {
            // Warmup all company caches
            $companies = Company::all();
            $total = $companies->count();

            if ($total === 0) {
                $this->info('No companies found to warm up.');

                return self::SUCCESS;
            }

            $this->info("Warming up cache for {$total} companies...");

            $bar = $this->output->createProgressBar($total);
            $bar->start();

            foreach ($companies as $company) {
                $this->cacheService->warmupCompanyCache($company);
                $bar->advance();
            }

            $bar->finish();
            $this->line('');
            $this->info('All company caches warmed up successfully.');
        }

        return self::SUCCESS;
    }

    /**
     * Handle cache stats action.
     */
    protected function handleStatsAction(): int
    {
        $stats = $this->cacheService->getCacheStats();
        $systemStats = $this->cacheService->cacheSystemStats();

        $this->info('=== Tenant Cache Statistics ===');
        $this->line('');

        $this->table(
            ['Configuration', 'Value'],
            [
                ['Caching Enabled', $stats['caching_enabled'] ? 'Yes' : 'No'],
                ['Cache Driver', $stats['cache_driver']],
                ['Default TTL', $stats['default_ttl'] . ' seconds'],
                ['Key Prefix', $stats['key_prefix']],
            ]
        );

        $this->line('');
        $this->info('=== System Statistics ===');

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Companies', $systemStats['total_companies']],
                ['Active Companies', $systemStats['active_companies']],
                ['Total Users', $systemStats['total_users']],
                ['Pending Invitations', $systemStats['pending_invitations']],
                ['Expired Invitations', $systemStats['expired_invitations']],
                ['Stats Cached At', $systemStats['cached_at']->format('Y-m-d H:i:s')],
            ]
        );

        return self::SUCCESS;
    }
}
