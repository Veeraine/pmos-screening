<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for the report, which searches by decision date and result date.
     */
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->index('decided_at');
            $table->index(['outcome', 'outcome_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex(['decided_at']);
            $table->dropIndex(['outcome', 'outcome_date']);
        });
    }
};
