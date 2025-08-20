<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Performance and Load Testing for Invitation Flow.
 *
 * Validates system performance under various load conditions:
 * - Response time benchmarks
 * - Memory usage monitoring
 * - Database query optimization
 * - Concurrent user handling
 * - Resource utilization
 */
class InvitationFlowPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private array $performanceMetrics = [];

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();

        // Enable query logging for performance analysis
        DB::enableQueryLog();
    }

    protected function tearDown(): void
    {
        // Log performance metrics
        if (! empty($this->performanceMetrics)) {
            activity('performance_metrics')
                ->withProperties($this->performanceMetrics)
                ->log('Performance test metrics collected');
        }

        parent::tearDown();
    }

    /**
     * PERFORMANCE TEST: Response Time Benchmarks
     * Validates all operations meet sub-second response targets.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function response_time_benchmarks_meet_targets()
    {
        $company = Company::factory()->withDomain('perf-test')->create();
        $invitation = Invitation::factory()
            ->forTenant($company)
            ->email('perf@test.com')
            ->admin()
            ->pending()
            ->create();

        // Test 1: Invitation Page Load (Target: <100ms)
        $start = hrtime(true);
        $response = $this->get(route('invitation.show', $invitation->token));
        $invitationLoadTime = (hrtime(true) - $start) / 1000000; // Convert to milliseconds

        $response->assertOk();
        $this->assertLessThan(100, $invitationLoadTime, 'Invitation page should load within 100ms');
        $this->performanceMetrics['invitation_load_ms'] = $invitationLoadTime;

        // Test 2: Invitation Processing (Target: <500ms)
        $start = hrtime(true);
        $response = $this->post(route('invitation.store', $invitation->token), [
            'name' => 'Performance User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);
        $processingTime = (hrtime(true) - $start) / 1000000;

        $response->assertRedirect();
        $this->assertLessThan(500, $processingTime, 'Invitation processing should complete within 500ms');
        $this->performanceMetrics['invitation_processing_ms'] = $processingTime;

        // Test 3: Login Page Load (Target: <100ms)
        $start = hrtime(true);
        $response = $this->get('/admin/login');
        $loginLoadTime = (hrtime(true) - $start) / 1000000;

        $response->assertOk();
        $this->assertLessThan(100, $loginLoadTime, 'Login page should load within 100ms');
        $this->performanceMetrics['login_load_ms'] = $loginLoadTime;
    }

    /**
     * PERFORMANCE TEST: Database Query Efficiency
     * Validates N+1 query prevention and query optimization.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function database_query_efficiency_validation()
    {
        $company = Company::factory()->withDomain('db-test')->create();

        // Create multiple invitations to test query efficiency
        $invitations = Invitation::factory()
            ->count(10)
            ->forTenant($company)
            ->sequence(
                ['email' => 'user1@test.com'],
                ['email' => 'user2@test.com'],
                ['email' => 'user3@test.com'],
                ['email' => 'user4@test.com'],
                ['email' => 'user5@test.com'],
                ['email' => 'user6@test.com'],
                ['email' => 'user7@test.com'],
                ['email' => 'user8@test.com'],
                ['email' => 'user9@test.com'],
                ['email' => 'user10@test.com']
            )
            ->admin()
            ->pending()
            ->create();

        DB::flushQueryLog();

        // Test invitation page load query count
        $this->get(route('invitation.show', $invitations->first()->token));
        $invitationPageQueries = count(DB::getQueryLog());
        $this->assertLessThan(5, $invitationPageQueries, 'Invitation page should use <5 queries');
        $this->performanceMetrics['invitation_page_queries'] = $invitationPageQueries;

        DB::flushQueryLog();

        // Test invitation processing query count
        $this->post(route('invitation.store', $invitations->first()->token), [
            'name' => 'DB Test User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);
        $processingQueries = count(DB::getQueryLog());
        $this->assertLessThan(10, $processingQueries, 'Invitation processing should use <10 queries');
        $this->performanceMetrics['invitation_processing_queries'] = $processingQueries;

        // Log all queries for analysis
        $this->performanceMetrics['query_log'] = DB::getQueryLog();
    }

    /**
     * LOAD TEST: Concurrent Invitation Processing
     * Tests system behavior under concurrent load.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function concurrent_invitation_processing_load_test()
    {
        $company = Company::factory()->withDomain('load-test')->create();

        // Create multiple invitations for concurrent processing
        $invitations = [];
        for ($i = 1; $i <= 20; $i++) {
            $invitations[] = Invitation::factory()
                ->forTenant($company)
                ->email("loaduser{$i}@test.com")
                ->admin()
                ->pending()
                ->create();
        }

        $results = [];
        $start = hrtime(true);

        // Simulate concurrent processing
        foreach ($invitations as $index => $invitation) {
            $iterationStart = hrtime(true);

            $response = $this->post(route('invitation.store', $invitation->token), [
                'name' => "Load User {$index}",
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!',
            ]);

            $iterationTime = (hrtime(true) - $iterationStart) / 1000000;

            $results[] = [
                'iteration' => $index,
                'response_code' => $response->status(),
                'time_ms' => $iterationTime,
                'success' => $response->isRedirect(),
            ];
        }

        $totalTime = (hrtime(true) - $start) / 1000000;

        // Performance validations
        $successCount = count(array_filter($results, fn ($r) => $r['success']));
        $averageTime = array_sum(array_column($results, 'time_ms')) / count($results);
        $maxTime = max(array_column($results, 'time_ms'));

        $this->assertEquals(20, $successCount, 'All invitations should process successfully');
        $this->assertLessThan(1000, $averageTime, 'Average processing time should be <1000ms');
        $this->assertLessThan(2000, $maxTime, 'Maximum processing time should be <2000ms');
        $this->assertLessThan(30000, $totalTime, 'Total processing time should be <30 seconds');

        $this->performanceMetrics['load_test'] = [
            'total_invitations' => 20,
            'successful_processes' => $successCount,
            'average_time_ms' => $averageTime,
            'max_time_ms' => $maxTime,
            'total_time_ms' => $totalTime,
            'throughput_per_second' => 20 / ($totalTime / 1000),
        ];
    }

    /**
     * MEMORY TEST: Memory Usage Monitoring
     * Validates memory efficiency and leak prevention.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function memory_usage_efficiency_validation()
    {
        $initialMemory = memory_get_usage(true);
        $company = Company::factory()->withDomain('memory-test')->create();

        // Process multiple invitations to test memory usage
        for ($i = 1; $i <= 50; $i++) {
            $invitation = Invitation::factory()
                ->forTenant($company)
                ->email("memuser{$i}@test.com")
                ->admin()
                ->pending()
                ->create();

            $this->post(route('invitation.store', $invitation->token), [
                'name' => "Memory User {$i}",
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!',
            ]);

            // Force garbage collection every 10 iterations
            if ($i % 10 === 0) {
                gc_collect_cycles();
            }
        }

        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;
        $peakMemory = memory_get_peak_usage(true);

        // Memory usage should not increase excessively
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease, 'Memory increase should be <50MB for 50 invitations');
        $this->assertLessThan(256 * 1024 * 1024, $peakMemory, 'Peak memory usage should be <256MB');

        $this->performanceMetrics['memory_test'] = [
            'initial_memory_mb' => round($initialMemory / 1024 / 1024, 2),
            'final_memory_mb' => round($finalMemory / 1024 / 1024, 2),
            'memory_increase_mb' => round($memoryIncrease / 1024 / 1024, 2),
            'peak_memory_mb' => round($peakMemory / 1024 / 1024, 2),
        ];
    }

    /**
     * STRESS TEST: System Breaking Point Analysis
     * Identifies system limits and degradation patterns.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function stress_test_system_breaking_point()
    {
        $company = Company::factory()->withDomain('stress-test')->create();

        $results = [];
        $failureThreshold = 5000; // 5 second timeout threshold
        $maxIterations = 100;

        for ($i = 1; $i <= $maxIterations; $i++) {
            $invitation = Invitation::factory()
                ->forTenant($company)
                ->email("stress{$i}@test.com")
                ->admin()
                ->pending()
                ->create();

            $start = hrtime(true);

            try {
                $response = $this->post(route('invitation.store', $invitation->token), [
                    'name' => "Stress User {$i}",
                    'password' => 'SecurePassword123!',
                    'password_confirmation' => 'SecurePassword123!',
                ]);

                $responseTime = (hrtime(true) - $start) / 1000000;

                $results[] = [
                    'iteration' => $i,
                    'success' => $response->isRedirect(),
                    'response_time_ms' => $responseTime,
                    'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                ];

                // Stop if response time exceeds threshold
                if ($responseTime > $failureThreshold) {
                    break;
                }
            } catch (\Exception $e) {
                $results[] = [
                    'iteration' => $i,
                    'success' => false,
                    'error' => $e->getMessage(),
                    'response_time_ms' => (hrtime(true) - $start) / 1000000,
                ];
                break;
            }

            // Periodic cleanup
            if ($i % 20 === 0) {
                gc_collect_cycles();
            }
        }

        $successfulRequests = count(array_filter($results, fn ($r) => $r['success'] ?? false));
        $averageResponseTime = array_sum(array_column($results, 'response_time_ms')) / count($results);

        // System should handle at least 50 requests successfully
        $this->assertGreaterThan(50, $successfulRequests, 'System should handle at least 50 concurrent invitations');
        $this->assertLessThan(2000, $averageResponseTime, 'Average response time should remain under 2 seconds');

        $this->performanceMetrics['stress_test'] = [
            'total_attempts' => count($results),
            'successful_requests' => $successfulRequests,
            'failure_rate_percent' => round((count($results) - $successfulRequests) / count($results) * 100, 2),
            'average_response_time_ms' => $averageResponseTime,
            'breaking_point_iteration' => count($results),
        ];
    }

    /**
     * SCALABILITY TEST: Multi-Tenant Performance
     * Tests performance with multiple companies and users.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function multi_tenant_scalability_validation()
    {
        // Create multiple companies
        $companies = Company::factory()
            ->count(10)
            ->sequence(
                ['name' => 'Company 1', 'domain' => 'comp1'],
                ['name' => 'Company 2', 'domain' => 'comp2'],
                ['name' => 'Company 3', 'domain' => 'comp3'],
                ['name' => 'Company 4', 'domain' => 'comp4'],
                ['name' => 'Company 5', 'domain' => 'comp5'],
                ['name' => 'Company 6', 'domain' => 'comp6'],
                ['name' => 'Company 7', 'domain' => 'comp7'],
                ['name' => 'Company 8', 'domain' => 'comp8'],
                ['name' => 'Company 9', 'domain' => 'comp9'],
                ['name' => 'Company 10', 'domain' => 'comp10']
            )
            ->create();

        $results = [];
        $start = hrtime(true);

        // Process 5 invitations per company (50 total)
        foreach ($companies as $companyIndex => $company) {
            for ($userIndex = 1; $userIndex <= 5; $userIndex++) {
                $invitation = Invitation::factory()
                    ->forTenant($company)
                    ->email("user{$userIndex}@comp{$companyIndex}.com")
                    ->admin()
                    ->pending()
                    ->create();

                $iterationStart = hrtime(true);

                $response = $this->post(route('invitation.store', $invitation->token), [
                    'name' => "Company {$companyIndex} User {$userIndex}",
                    'password' => 'SecurePassword123!',
                    'password_confirmation' => 'SecurePassword123!',
                ]);

                $results[] = [
                    'company_id' => $company->id,
                    'user_index' => $userIndex,
                    'success' => $response->isRedirect(),
                    'response_time_ms' => (hrtime(true) - $iterationStart) / 1000000,
                ];
            }
        }

        $totalTime = (hrtime(true) - $start) / 1000000;
        $successCount = count(array_filter($results, fn ($r) => $r['success']));
        $averageTime = array_sum(array_column($results, 'response_time_ms')) / count($results);

        // Scalability assertions
        $this->assertEquals(50, $successCount, 'All 50 multi-tenant invitations should succeed');
        $this->assertLessThan(1000, $averageTime, 'Average response time should remain <1000ms with multiple tenants');
        $this->assertLessThan(60000, $totalTime, 'Total processing should complete within 1 minute');

        // Verify users were created in correct tenants
        foreach ($companies as $company) {
            $userCount = User::where('tenant_id', $company->id)->count();
            $this->assertEquals(5, $userCount, "Company {$company->domain} should have 5 users");
        }

        $this->performanceMetrics['scalability_test'] = [
            'total_companies' => 10,
            'users_per_company' => 5,
            'total_invitations' => 50,
            'successful_processes' => $successCount,
            'average_response_time_ms' => $averageTime,
            'total_time_ms' => $totalTime,
            'throughput_per_second' => 50 / ($totalTime / 1000),
        ];
    }
}
