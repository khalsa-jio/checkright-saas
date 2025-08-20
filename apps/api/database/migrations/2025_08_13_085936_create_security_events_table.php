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
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('tenant_id')->nullable()->index();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_id')->nullable()->index();
            $table->string('session_id')->nullable();
            $table->json('context')->nullable();
            $table->decimal('risk_score', 3, 2)->default(0.00)->index();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            // Foreign key constraint (nullable because some events may not have a user)
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            // Indexes for performance
            $table->index(['user_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
            $table->index(['risk_score', 'occurred_at']);
            $table->index(['tenant_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
