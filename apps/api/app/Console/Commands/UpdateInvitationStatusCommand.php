<?php

namespace App\Console\Commands;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Console\Command;

class UpdateInvitationStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invitations:update-status 
                           {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Update invitation statuses for cases where users already exist';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        $this->info('Checking for invitations where users already exist...');

        // Find invitations where user already exists
        $invitationsWithExistingUsers = Invitation::whereNull('accepted_at')
            ->whereExists(function ($query) {
                $query->select('id')
                    ->from('users')
                    ->whereColumn('users.email', 'invitations.email')
                    ->whereColumn('users.tenant_id', 'invitations.tenant_id');
            })
            ->with(['company:id,name'])
            ->get();

        if ($invitationsWithExistingUsers->isEmpty()) {
            $this->info('No invitations found where users already exist.');

            return Command::SUCCESS;
        }

        $this->table(
            ['ID', 'Email', 'Role', 'Company', 'Created At'],
            $invitationsWithExistingUsers->map(function ($invitation) {
                return [
                    $invitation->id,
                    $invitation->email,
                    $invitation->role,
                    $invitation->company->name ?? 'N/A',
                    $invitation->created_at->format('Y-m-d H:i:s'),
                ];
            })->toArray()
        );

        $this->warn("Found {$invitationsWithExistingUsers->count()} invitations where users already exist.");

        if ($isDryRun) {
            $this->info('This is a dry run. No changes will be made.');
            $this->info('Run without --dry-run to actually update the invitations.');

            return Command::SUCCESS;
        }

        if (! $this->confirm('Do you want to mark these invitations as having users that already exist?')) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        $updated = 0;
        foreach ($invitationsWithExistingUsers as $invitation) {
            // We could add a specific field for this, but for now we'll just log it
            // The status will be determined dynamically by the userAlreadyExists() method

            activity('invitation_user_exists')
                ->performedOn($invitation)
                ->withProperties([
                    'existing_user_email' => $invitation->email,
                    'tenant_id' => $invitation->tenant_id,
                    'original_status' => 'pending',
                    'new_status' => 'user_exists',
                ])
                ->log('Invitation marked as user already exists');

            $updated++;
        }

        $this->info("Logged status updates for {$updated} invitations.");
        $this->info('The invitation list will now show these as "User Already Exists" status.');

        return Command::SUCCESS;
    }
}
