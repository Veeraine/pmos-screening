<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keeps every correction to an alert: old value, new value, why and who.
     */
    public function up(): void
    {
        Schema::create('alert_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained()->restrictOnDelete();
            $table->string('field', 20);
            $table->json('previous_values');
            $table->json('new_values');
            $table->text('reason');
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_corrections');
    }
};
