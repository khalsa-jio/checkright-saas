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
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('provider'); // google, facebook, instagram
            $table->string('provider_id'); // Provider's unique user ID
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('avatar')->nullable(); // Avatar URL
            $table->text('token')->nullable(); // OAuth access token
            $table->text('refresh_token')->nullable(); // OAuth refresh token
            $table->integer('expires_in')->nullable(); // Token expiration time
            $table->timestamps();

            // Ensure one account per provider per user
            $table->unique(['user_id', 'provider']);

            // Index for finding accounts by provider
            $table->index(['provider', 'provider_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
