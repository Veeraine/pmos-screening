<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Screening alerts, with a copy of why each was raised, the decision and the referral result.
     */
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('raised_at_visit_id')->constrained('visits')->restrictOnDelete();
            $table->foreignId('previous_alert_id')->nullable()->constrained('alerts')->restrictOnDelete();
            $table->string('status', 20);
            $table->json('findings');

            $table->boolean('referred')->nullable();
            $table->text('not_referred_reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();

            $table->string('outcome', 20)->nullable();
            $table->date('outcome_date')->nullable();
            $table->foreignId('outcome_recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('outcome_recorded_at')->nullable();

            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
