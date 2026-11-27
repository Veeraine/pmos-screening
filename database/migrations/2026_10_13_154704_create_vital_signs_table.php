<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vital_signs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('weight_kg', 5, 1);
            $table->decimal('height_cm', 4, 1);
            $table->decimal('bmi', 4, 1);
            $table->unsignedSmallInteger('systolic_bp');
            $table->unsignedSmallInteger('diastolic_bp');
            $table->decimal('temperature_c', 3, 1);
            $table->unsignedSmallInteger('pulse_rate');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vital_signs');
    }
};
