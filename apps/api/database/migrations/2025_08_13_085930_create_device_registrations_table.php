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
        Schema::create('device_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('device_id')->index();
            $table->json('device_info')->nullable();
            $table->string('device_secret')->nullable();
            $table->boolean('is_trusted')->default(false);
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('trusted_at')->nullable();
            $table->timestamp('trusted_until')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Composite unique constraint - one device per user
            $table->unique(['user_id', 'device_id']);

            // Indexes for performance
            $table->index(['user_id', 'is_trusted']);
            $table->index(['device_id', 'is_trusted']);
            $table->index(['trusted_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_registrations');
    }
};
