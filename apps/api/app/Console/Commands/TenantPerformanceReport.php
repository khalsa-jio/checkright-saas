<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\OptimizedSessionGarbageCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Generate comprehensive performance reports for tenant infrastructure.
 */
class TenantPerformanceReport extends Command
{
    protected $signature = 'tenant:performance-report 
                          {--tenant=* : Specific tenant IDs to analyze}
                          {--format=table : Output format (table|json)}
                          {--export= : Export to file path}';

    protected $description = 'Generate performance report for tenant infrastructure';

    public function handle(): int
    {
        $this->info('🚀 Generating Tenant Infrastructure Performance Report...');

        $tenantIds = $this->option('tenant');
        $format = $this->option('format');
        $exportPath = $this->option('export');

        $report = $this->generateReport($tenantIds);

        if ($format === 'json') {
            $this->outputJson($report);
        } else {
            $this->outputTable($report);
        }

        if ($exportPath) {
            $this->exportReport($report, $exportPath);
        }

        return Command::SUCCESS;
    }

    private function generateReport(array $tenantIds = []): array
    {
        $report = [
            'timestamp' => now()->toISOString(),
            'overall_metrics' => $this->getOverallMetrics(),
            'session_metrics' => $this->getSessionMetrics(),
            'cache_metrics' => $this->getCacheMetrics(),
            'rate_limiting_metrics' => $this->getRateLimitingMetrics(),
            'tenant_specific' => $this->getTenantSpecificMetrics($tenantIds),
            'performance_recommendations' => $this->getPerformanceRecommendations(),
        ];

        return $report;
    }

    private function getOverallMetrics(): array
    {
        $totalTenants = Company::count();
        $activeTenants = Company::whereHas('domains')->count();

        return [
            'total_tenants' => $totalTenants,
            'active_tenants' => $activeTenants,
            'central_domains' => count(config('tenancy.central_domains', [])),
            'cache_enabled' => config('tenant.performance.cache_tenant_data', false),
            'hybrid_mode_enabled' => config('tenant.hybrid.enabled', false),
        ];
    }

    private function getSessionMetrics(): array
    {
        $collector = app(OptimizedSessionGarbageCollector::class);
        $stats = $collector->getStatistics();

        return [
            'total_sessions' => $stats['total_sessions'],
            'active_sessions' => $stats['active_sessions'],
            'expired_sessions' => $stats['expired_sessions'],
            'session_driver' => config('session.driver'),
            'session_lifetime' => config('session.lifetime') . ' minutes',
            'gc_probability' => config('session.lottery')[0] . '/' . config('session.lottery')[1],
        ];
    }

    private function getCacheMetrics(): array
    {
        $cacheMetrics = [
            'driver' => config('cache.default'),
            'tenant_cache_enabled' => config('tenant.performance.cache_tenant_data', false),
            'cache_ttl' => config('tenant.performance.cache_ttl', 3600) . ' seconds',
        ];

        // Try to get cache statistics if available
        try {
            $cacheMetrics['cache_hits'] = Cache::get('cache_hits', 0);
            $cacheMetrics['cache_misses'] = Cache::get('cache_misses', 0);

            if ($cacheMetrics['cache_hits'] > 0 || $cacheMetrics['cache_misses'] > 0) {
                $total = $cacheMetrics['cache_hits'] + $cacheMetrics['cache_misses'];
                $cacheMetrics['hit_ratio'] = round(($cacheMetrics['cache_hits'] / $total) * 100, 2) . '%';
            }
        } catch (\Exception $e) {
            $cacheMetrics['cache_stats_available'] = false;
        }

        return $cacheMetrics;
    }

    private function getRateLimitingMetrics(): array
    {
        return [
            'rate_limiting_enabled' => config('tenant.security.rate_limiting_enabled', false),
            'max_login_attempts' => config('tenant.security.max_login_attempts', 5),
            'login_throttle_minutes' => config('tenant.security.login_throttle_minutes', 1),
            'tenant_aware_limiting' => app()->bound('tenant.rate_limiter'),
        ];
    }

    private function getTenantSpecificMetrics(array $tenantIds = []): array
    {
        $query = Company::with('domains');

        if (! empty($tenantIds)) {
            $query->whereIn('id', $tenantIds);
        }

        $tenants = $query->limit(10)->get(); // Limit for performance

        return $tenants->map(function ($tenant) {
            return [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'domain_count' => $tenant->domains->count(),
                'primary_domain' => $tenant->domains->first()?->domain ?? 'N/A',
                'user_count' => $tenant->users()->count() ?? 0,
                'created_at' => $tenant->created_at->toISOString(),
                'performance_tier' => $this->getTenantPerformanceTier($tenant),
            ];
        })->toArray();
    }

    private function getTenantPerformanceTier(Company $tenant): string
    {
        $userCount = $tenant->users()->count();
        $thresholds = config('tenant.hybrid.separate_db_criteria', []);

        if ($userCount >= ($thresholds['user_count'] ?? 1000)) {
            return 'enterprise';
        } elseif ($userCount >= 100) {
            return 'pro';
        } else {
            return 'basic';
        }
    }

    private function getPerformanceRecommendations(): array
    {
        $recommendations = [];

        // Check if caching is enabled
        if (! config('tenant.performance.cache_tenant_data', false)) {
            $recommendations[] = 'Enable tenant data caching for better performance';
        }

        // Check session configuration
        if (config('session.driver') === 'file') {
            $recommendations[] = 'Consider using database or Redis for session storage in production';
        }

        // Check if rate limiting is enabled
        if (! config('tenant.security.rate_limiting_enabled', false)) {
            $recommendations[] = 'Enable rate limiting for enhanced security';
        }

        // Check for large number of expired sessions
        $collector = app(OptimizedSessionGarbageCollector::class);
        $stats = $collector->getStatistics();

        if ($stats['expired_sessions'] > ($stats['total_sessions'] * 0.3)) {
            $recommendations[] = 'High number of expired sessions detected - consider running session cleanup';
        }

        // Check for hybrid tenancy opportunity
        $largeTenants = Company::withCount('users')
            ->having('users_count', '>', config('tenant.hybrid.separate_db_criteria.user_count', 1000))
            ->count();

        if ($largeTenants > 0 && ! config('tenant.hybrid.enabled', false)) {
            $recommendations[] = "Consider enabling hybrid tenancy - {$largeTenants} tenant(s) exceed enterprise threshold";
        }

        if (empty($recommendations)) {
            $recommendations[] = 'All systems performing optimally ✅';
        }

        return $recommendations;
    }

    private function outputTable(array $report): void
    {
        $this->info("\n📊 Overall Metrics");
        $this->table(
            ['Metric', 'Value'],
            collect($report['overall_metrics'])->map(fn ($value, $key) => [$key, $value])->values()
        );

        $this->info("\n💾 Session Metrics");
        $this->table(
            ['Metric', 'Value'],
            collect($report['session_metrics'])->map(fn ($value, $key) => [$key, $value])->values()
        );

        $this->info("\n🚀 Cache Metrics");
        $this->table(
            ['Metric', 'Value'],
            collect($report['cache_metrics'])->map(fn ($value, $key) => [$key, $value])->values()
        );

        if (! empty($report['tenant_specific'])) {
            $this->info("\n🏢 Tenant Metrics");
            $this->table(
                ['ID', 'Name', 'Domains', 'Users', 'Tier'],
                collect($report['tenant_specific'])->map(fn ($tenant) => [
                    $tenant['tenant_id'],
                    $tenant['tenant_name'],
                    $tenant['domain_count'],
                    $tenant['user_count'],
                    $tenant['performance_tier'],
                ])->values()
            );
        }

        $this->info("\n💡 Performance Recommendations");
        foreach ($report['performance_recommendations'] as $index => $recommendation) {
            $this->line(($index + 1) . '. ' . $recommendation);
        }
    }

    private function outputJson(array $report): void
    {
        $this->line(json_encode($report, JSON_PRETTY_PRINT));
    }

    private function exportReport(array $report, string $path): void
    {
        $content = json_encode($report, JSON_PRETTY_PRINT);
        file_put_contents($path, $content);
        $this->info("📁 Report exported to: {$path}");
    }
}
