<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The fixed PMOS symptom tick list. The rules use the codes, so don't change them.
     */
    public function up(): void
    {
        Schema::create('symptoms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->unsignedTinyInteger('sort_order');
            $table->timestamps();
        });

        $symptoms = [
            ['irregular_periods', 'Irregular periods'],
            ['absent_periods', 'Absent periods'],
            ['prolonged_bleeding', 'Prolonged bleeding'],
            ['excess_hair_growth', 'Excess hair growth'],
            ['acne', 'Acne'],
            ['hair_thinning', 'Hair thinning'],
            ['weight_gain', 'Weight gain'],
            ['dark_skin_patches', 'Dark skin patches'],
            ['difficulty_conceiving', 'Difficulty conceiving'],
            ['polycystic_ovaries_on_ultrasound', 'Ultrasound findings (polycystic ovaries)'],
        ];

        DB::table('symptoms')->insert(array_map(fn (array $symptom, int $index): array => [
            'code' => $symptom[0],
            'name' => $symptom[1],
            'sort_order' => $index + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $symptoms, array_keys($symptoms)));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('symptoms');
    }
};
