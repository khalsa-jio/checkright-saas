<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Services\ErrorHandling\TenantCleanupService;
use Exception;
use Illuminate\Console\Command;

class CleanupOrphanedTenantResources extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:cleanup-orphaned 
                           {--dry-run : Show what would be cleaned up without actually doing it}
                           {--force : Force cleanup without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphaned tenant resources (companies without domains, expired invitations, etc.)';

    protected TenantCleanupService $cleanupService;

    /**
     * Create a new command instance.
     */
    public function __construct(TenantCleanupService $cleanupService)
    {
        parent::__construct();
        $this->cleanupService = $cleanupService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $isForced = $this->option('force');

        $this->info('Starting tenant resource cleanup...');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        try {
            if ($isDryRun) {
                $this->performDryRun();
            } else {
                if (! $isForced && ! $this->confirm('Are you sure you want to cleanup orphaned tenant resources?')) {
                    $this->info('Operation cancelled.');

                    return self::SUCCESS;
                }

                $cleaned = $this->cleanupService->cleanupOrphanedResources();
                $this->displayResults($cleaned);
            }

            $this->info('Tenant resource cleanup completed successfully.');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Failed to cleanup tenant resources: ' . $e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    /**
     * Perform a dry run to show what would be cleaned up.
     */
    protected function performDryRun(): void
    {
        $this->info('Scanning for orphaned resources...');

        // Count orphaned companies
        $orphanedCompaniesCount = Company::doesntHave('domains')->count();
        $this->line("Companies without domains: {$orphanedCompaniesCount}");

        // Count expired invitations
        $expiredInvitationsCount = Invitation::expired()->count();
        $this->line("Expired invitations: {$expiredInvitationsCount}");

        // Count orphaned users
        $orphanedUsersCount = User::whereDoesntHave('company')->count();
        $this->line("Users without companies: {$orphanedUsersCount}");

        $total = $orphanedCompaniesCount + $expiredInvitationsCount + $orphanedUsersCount;

        if ($total > 0) {
            $this->warn("Total orphaned resources that would be cleaned: {$total}");
            $this->info('Run without --dry-run to perform actual cleanup.');
        } else {
            $this->info('No orphaned resources found.');
        }
    }

    /**
     * Display cleanup results.
     */
    protected function displayResults(array $cleaned): void
    {
        $this->info('Cleanup Results:');
        $this->table(
            ['Resource Type', 'Cleaned Count'],
            [
                ['Companies', $cleaned['companies']],
                ['Invitations', $cleaned['invitations']],
                ['Users', $cleaned['users']],
                ['Total', array_sum($cleaned)],
            ]
        );

        if (array_sum($cleaned) === 0) {
            $this->info('No orphaned resources found to clean up.');
        }
    }
}
