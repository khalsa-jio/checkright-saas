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
        Schema::table('users', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            $table->enum('role', ['admin', 'manager', 'operator'])->default('operator')->after('email_verified_at');
            $table->timestamp('last_login_at')->nullable()->after('role');
            $table->boolean('must_change_password')->default(false)->after('last_login_at');
            $table->softDeletes()->after('updated_at');

            // Add index for tenant_id for performance
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'tenant_id',
                'role',
                'last_login_at',
                'must_change_password',
                'deleted_at',
            ]);
        });
    }
};
