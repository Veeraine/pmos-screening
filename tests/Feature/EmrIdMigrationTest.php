<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Symptom;
use App\Models\User;
use App\Models\Visit;
use App\Role;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The migration that links the screening tables to EMR IDs must keep every
 * existing symptom, alert and correction.
 */
class EmrIdMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private const MIGRATION = 'database/migrations/2026_10_25_090000_link_screening_tables_to_emr_ids.php';

    public function test_existing_screening_data_is_copied_to_emr_ids(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();

        $doctor = User::factory()->role(Role::Doctor)->create();
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create(['consulted_by' => $doctor->id, 'consulted_at' => '2026-10-15 09:40:00']);
        $acneId = Symptom::where('code', 'acne')->value('id');

        DB::table('visit_symptom')->insert(['visit_id' => $visit->id, 'symptom_id' => $acneId, 'source' => 'detected', 'matched_text' => 'Pimples on her chin']);
        $alertId = DB::table('alerts')->insertGetId([
            'patient_id' => $patient->id, 'raised_at_visit_id' => $visit->id, 'status' => 'closed', 'findings' => '[]',
            'referred' => true, 'decided_by' => $doctor->id, 'decided_at' => '2026-10-15 10:00:00',
            'created_at' => '2026-10-15 09:41:00', 'updated_at' => '2026-10-15 10:00:00',
        ]);
        DB::table('alert_corrections')->insert([
            'alert_id' => $alertId, 'field' => 'decision', 'previous_values' => '{}', 'new_values' => '{}',
            'reason' => 'Mistake', 'corrected_by' => $doctor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();

        $confirmed = DB::table('confirmed_symptoms')->sole();
        $this->assertSame((string) $patient->id, $confirmed->emr_patient_id);
        $this->assertSame((string) $visit->id, $confirmed->emr_visit_id);
        $this->assertSame($acneId, (int) $confirmed->symptom_id);
        $this->assertSame('detected', $confirmed->source);
        $this->assertSame('Pimples on her chin', $confirmed->matched_text);
        $this->assertSame((string) $doctor->id, $confirmed->confirmed_by);

        $alert = DB::table('alerts')->sole();
        $this->assertSame((string) $patient->id, $alert->emr_patient_id);
        $this->assertSame((string) $visit->id, $alert->raised_at_emr_visit_id);
        $this->assertSame((string) $doctor->id, $alert->decided_by);

        $this->assertSame((string) $doctor->id, DB::table('alert_corrections')->sole()->corrected_by);
    }
}
