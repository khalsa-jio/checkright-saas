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
        Schema::create('mobile_token_registries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('device_id')->index();
            $table->unsignedBigInteger('access_token_id')->nullable();
            $table->unsignedBigInteger('refresh_token_id')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('access_token_id')->references('id')->on('personal_access_tokens')->onDelete('set null');
            $table->foreign('refresh_token_id')->references('id')->on('personal_access_tokens')->onDelete('set null');

            // Indexes for performance
            $table->index(['user_id', 'device_id']);
            $table->index(['user_id', 'expires_at']);
            $table->index(['device_id', 'expires_at']);

            // Unique constraint - one active token pair per user/device
            $table->unique(['user_id', 'device_id', 'access_token_id'], 'unique_user_device_access');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile_token_registries');
    }
};
