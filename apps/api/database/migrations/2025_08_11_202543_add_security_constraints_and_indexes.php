<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add unique constraint for email+tenant_id combination in invitations
        Schema::table('invitations', function (Blueprint $table) {
            // Add unique constraint to prevent duplicate invitations
            $table->unique(['email', 'tenant_id'], 'unique_invitation_per_tenant');

            // Add index on accepted_at for filtering (expires_at already exists)
            $table->index('accepted_at');
        });

        // Add additional indexes to tenants table for performance
        Schema::table('tenants', function (Blueprint $table) {
            // Add index on domain for fast lookup
            $table->index('domain');

            // Add index on name for search functionality
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropUnique('unique_invitation_per_tenant');
            $table->dropIndex(['accepted_at']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['domain']);
            $table->dropIndex(['name']);
        });
    }
};
