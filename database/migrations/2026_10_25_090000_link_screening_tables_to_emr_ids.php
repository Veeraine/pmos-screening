<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Patients, visits and staff belong to the EMR, so the screening tables store EMR IDs as text
 * instead of foreign keys. Existing data is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('confirmed_symptoms', function (Blueprint $table) {
            $table->id();
            $table->string('emr_patient_id', 64)->index();
            $table->string('emr_visit_id', 64);
            $table->foreignId('symptom_id')->constrained()->restrictOnDelete();
            $table->string('source', 20);
            $table->string('matched_text')->nullable();
            $table->string('confirmed_by', 64)->nullable();
            $table->timestamps();

            $table->unique(['emr_visit_id', 'symptom_id']);
        });

        DB::table('visit_symptom')
            ->join('visits', 'visits.id', '=', 'visit_symptom.visit_id')
            ->select('visit_symptom.*', 'visits.patient_id', 'visits.consulted_by', 'visits.consulted_at')
            ->orderBy('visit_symptom.visit_id')
            ->orderBy('visit_symptom.symptom_id')
            ->chunk(500, function ($rows): void {
                DB::table('confirmed_symptoms')->insert($rows->map(fn ($row): array => [
                    'emr_patient_id' => (string) $row->patient_id,
                    'emr_visit_id' => (string) $row->visit_id,
                    'symptom_id' => $row->symptom_id,
                    'source' => $row->source,
                    'matched_text' => $row->matched_text,
                    'confirmed_by' => $row->consulted_by === null ? null : (string) $row->consulted_by,
                    'created_at' => $row->consulted_at,
                    'updated_at' => $row->consulted_at,
                ])->all());
            });

        Schema::drop('visit_symptom');

        Schema::table('alerts', function (Blueprint $table) {
            $table->string('emr_patient_id', 64)->nullable()->after('id');
            $table->string('raised_at_emr_visit_id', 64)->nullable()->after('emr_patient_id');
        });

        DB::table('alerts')->update([
            'emr_patient_id' => DB::raw('patient_id'),
            'raised_at_emr_visit_id' => DB::raw('raised_at_visit_id'),
        ]);

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
            $table->dropForeign(['raised_at_visit_id']);
            $table->dropForeign(['decided_by']);
            $table->dropForeign(['outcome_recorded_by']);
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex(['patient_id', 'status']);
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn(['patient_id', 'raised_at_visit_id']);
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->string('emr_patient_id', 64)->nullable(false)->change();
            $table->string('raised_at_emr_visit_id', 64)->nullable(false)->change();
            $table->string('decided_by', 64)->nullable()->change();
            $table->string('outcome_recorded_by', 64)->nullable()->change();

            $table->index(['emr_patient_id', 'status']);
        });

        Schema::table('alert_corrections', function (Blueprint $table) {
            $table->dropForeign(['corrected_by']);
        });

        Schema::table('alert_corrections', function (Blueprint $table) {
            $table->string('corrected_by', 64)->change();
        });
    }

    /**
     * Puts the foreign keys back. Only works if every EMR ID exists in the simulated Medix tables.
     */
    public function down(): void
    {
        Schema::table('alert_corrections', function (Blueprint $table) {
            $table->unsignedBigInteger('corrected_by')->change();
            $table->foreign('corrected_by')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->unsignedBigInteger('patient_id')->nullable()->after('id');
            $table->unsignedBigInteger('raised_at_visit_id')->nullable()->after('patient_id');
        });

        DB::table('alerts')->update([
            'patient_id' => DB::raw('emr_patient_id'),
            'raised_at_visit_id' => DB::raw('raised_at_emr_visit_id'),
        ]);

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex(['emr_patient_id', 'status']);
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn(['emr_patient_id', 'raised_at_emr_visit_id']);
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->unsignedBigInteger('patient_id')->nullable(false)->change();
            $table->unsignedBigInteger('raised_at_visit_id')->nullable(false)->change();
            $table->unsignedBigInteger('decided_by')->nullable()->change();
            $table->unsignedBigInteger('outcome_recorded_by')->nullable()->change();

            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('raised_at_visit_id')->references('id')->on('visits')->restrictOnDelete();
            $table->foreign('decided_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('outcome_recorded_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['patient_id', 'status']);
        });

        Schema::create('visit_symptom', function (Blueprint $table) {
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('symptom_id')->constrained()->restrictOnDelete();
            $table->string('source', 20)->default('manual');
            $table->string('matched_text')->nullable();

            $table->primary(['visit_id', 'symptom_id']);
        });

        DB::table('confirmed_symptoms')->orderBy('id')->chunk(500, function ($rows): void {
            DB::table('visit_symptom')->insert($rows->map(fn ($row): array => [
                'visit_id' => (int) $row->emr_visit_id,
                'symptom_id' => $row->symptom_id,
                'source' => $row->source,
                'matched_text' => $row->matched_text,
            ])->all());
        });

        Schema::drop('confirmed_symptoms');
    }
};
