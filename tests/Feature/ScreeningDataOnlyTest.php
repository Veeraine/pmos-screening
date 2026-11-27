<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Checks the screening tables only keep screening data, not patient details.
 */
class ScreeningDataOnlyTest extends TestCase
{
    use RefreshDatabase;

// The add-on's own tables.

    private const SCREENING_TABLES = ['symptoms', 'confirmed_symptoms', 'alerts', 'alert_corrections'];

    
// Tables that belong to the EMR (the simulated Medix).
     
    private const EMR_TABLES = ['patients', 'visits', 'vital_signs', 'users'];

    public function test_screening_tables_have_no_personal_details(): void
    {
        $personalDetails = [
            'first_name', 'last_name', 'full_name', 'date_of_birth', 'phone', 'address', 'email',
            'patient_number', 'notes', 'menstrual_history', 'diagnosis', 'previous_diagnoses', 'weight_kg', 'height_cm', 'bmi',
        ];

        foreach (self::SCREENING_TABLES as $table) {
            $this->assertSame([], array_values(array_intersect(Schema::getColumnListing($table), $personalDetails)), "The {$table} table holds personal details.");
        }
    }

    public function test_screening_tables_have_no_database_links_to_emr_tables(): void
    {
        foreach (self::SCREENING_TABLES as $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $this->assertNotContains($foreignKey['foreign_table'], self::EMR_TABLES, "The {$table} table links to the EMR's {$foreignKey['foreign_table']} table.");
            }
        }
    }

    public function test_screening_records_refer_to_the_emr_by_its_ids(): void
    {
        $this->assertTrue(Schema::hasColumns('confirmed_symptoms', ['emr_patient_id', 'emr_visit_id', 'confirmed_by']));
        $this->assertTrue(Schema::hasColumns('alerts', ['emr_patient_id', 'raised_at_emr_visit_id', 'decided_by', 'outcome_recorded_by']));
        $this->assertTrue(Schema::hasColumns('alert_corrections', ['corrected_by']));
        $this->assertFalse(Schema::hasTable('visit_symptom'));
    }
}
