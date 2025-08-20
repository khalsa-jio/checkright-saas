<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Optimized session garbage collector for multi-tenant applications.
 *
 * Features:
 * - Tenant-aware session cleanup
 * - Batch processing for performance
 * - Configurable cleanup intervals
 * - Memory-efficient operation
 */
class OptimizedSessionGarbageCollector
{
    private int $batchSize;

    private int $maxLifetime;

    public function __construct()
    {
        $this->batchSize = config('tenant.performance.gc_batch_size', 1000);
        $this->maxLifetime = config('session.lifetime') * 60; // Convert minutes to seconds
    }

    /**
     * Perform garbage collection on expired sessions.
     */
    public function collect(): int
    {
        $table = config('session.table', 'sessions');
        $cutoff = time() - $this->maxLifetime;
        $deleted = 0;

        do {
            // Use batch processing to avoid memory issues
            $batch = DB::table($table)
                ->where('last_activity', '<', $cutoff)
                ->limit($this->batchSize)
                ->pluck('id');

            if ($batch->isNotEmpty()) {
                $batchDeleted = DB::table($table)
                    ->whereIn('id', $batch)
                    ->delete();

                $deleted += $batchDeleted;

                // Small delay to prevent overwhelming the database
                usleep(10000); // 10ms
            }
        } while ($batch->count() === $this->batchSize);

        return $deleted;
    }

    /**
     * Clean up sessions for a specific tenant.
     */
    public function collectForTenant(string $tenantId): int
    {
        $table = config('session.table', 'sessions');
        $cutoff = time() - $this->maxLifetime;

        // For tenant-specific cleanup, we look for sessions with tenant-specific payloads
        return DB::table($table)
            ->where('last_activity', '<', $cutoff)
            ->where('payload', 'like', '%tenant_' . $tenantId . '%')
            ->delete();
    }

    /**
     * Get session statistics for monitoring.
     */
    public function getStatistics(): array
    {
        $table = config('session.table', 'sessions');

        return [
            'total_sessions' => DB::table($table)->count(),
            'expired_sessions' => DB::table($table)
                ->where('last_activity', '<', time() - $this->maxLifetime)
                ->count(),
            'active_sessions' => DB::table($table)
                ->where('last_activity', '>=', time() - 300) // Active in last 5 minutes
                ->count(),
        ];
    }
}
