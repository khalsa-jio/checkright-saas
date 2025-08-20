<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            // Make tenant_id nullable to support super admin invitations
            $table->string('tenant_id')->nullable()->change();
        });

        // Update role column to support super-admin (database-agnostic approach)
        // For SQLite (used in tests) we can use a simple column change
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite doesn't enforce enum constraints, so we just need to change column type
            DB::statement('ALTER TABLE invitations ADD COLUMN role_new VARCHAR(20) DEFAULT \'operator\'');
            DB::statement('UPDATE invitations SET role_new = role');
            DB::statement('ALTER TABLE invitations DROP COLUMN role');
            DB::statement('ALTER TABLE invitations RENAME COLUMN role_new TO role');
        } else {
            // For MySQL, change enum to varchar
            DB::statement("ALTER TABLE invitations MODIFY role VARCHAR(20) NOT NULL DEFAULT 'operator'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            // Revert tenant_id to NOT NULL
            $table->string('tenant_id')->nullable(false)->change();
        });

        // Revert role column back to enum (database-specific)
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE invitations MODIFY role ENUM('admin', 'manager', 'operator') NOT NULL DEFAULT 'operator'");
        }
    }
};
