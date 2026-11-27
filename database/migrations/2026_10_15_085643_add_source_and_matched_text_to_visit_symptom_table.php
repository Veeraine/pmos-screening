<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether each symptom was found in the notes (and which words) or added by hand.
     */
    public function up(): void
    {
        Schema::table('visit_symptom', function (Blueprint $table) {
            $table->string('source', 20)->default('manual');
            $table->string('matched_text')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visit_symptom', function (Blueprint $table) {
            $table->dropColumn(['source', 'matched_text']);
        });
    }
};
