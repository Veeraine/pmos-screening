<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Height is only measured at the first visit (or if it changes), so it can be empty.
     */
    public function up(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->decimal('height_cm', 4, 1)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations. Fails if any visit has no height recorded.
     */
    public function down(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->decimal('height_cm', 4, 1)->nullable(false)->change();
        });
    }
};
