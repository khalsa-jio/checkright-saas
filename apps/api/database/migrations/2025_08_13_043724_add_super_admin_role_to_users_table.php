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
            // Drop the old role column
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            // Add the new role column as string after email_verified_at
            $table->string('role', 20)->default('operator')->after('email_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the string role column
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            // Add back the ENUM role column after email_verified_at
            $table->enum('role', ['admin', 'manager', 'operator'])->default('operator')->after('email_verified_at');
        });
    }
};
