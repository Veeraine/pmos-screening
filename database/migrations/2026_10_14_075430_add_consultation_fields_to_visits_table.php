<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the doctor records at a visit (empty until the consultation is saved).
     */
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->text('menstrual_history')->nullable()->after('reason');
            $table->text('notes')->nullable()->after('menstrual_history');
            $table->string('diagnosis')->nullable()->after('notes');
            $table->foreignId('consulted_by')->nullable()->after('checked_in_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('consulted_at')->nullable()->after('consulted_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consulted_by');
            $table->dropColumn(['menstrual_history', 'notes', 'diagnosis', 'consulted_at']);
        });
    }
};
