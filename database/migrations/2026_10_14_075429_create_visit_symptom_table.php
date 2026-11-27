<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which symptoms the doctor ticked at which visit.
     */
    public function up(): void
    {
        Schema::create('visit_symptom', function (Blueprint $table) {
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('symptom_id')->constrained()->restrictOnDelete();

            $table->primary(['visit_id', 'symptom_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_symptom');
    }
};
