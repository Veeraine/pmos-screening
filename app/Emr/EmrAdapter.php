<?php

namespace App\Emr;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Connection to the hospital EMR. Patients, visits, notes and logins live in the EMR, so we ask it through here
 * and only store screening data. Each EMR gets its own class (set in config/emr.php). IDs are strings as EMRs differ.
 */
interface EmrAdapter
{
    
    public function systemName(): string;

    // A patient, or null if the EMR has no patient with this ID.
     
    public function findPatient(string $patientId): ?EmrPatient;

    /**
     * Several patients at once, keyed by ID. IDs the EMR does not know are left out.
     *
     * @param  list<string>  $patientIds
     * @return Collection<string, EmrPatient>
     */
    public function findPatients(array $patientIds): Collection;

    /**
     * Searches by patient number, name or phone. 
     *
     * @return LengthAwarePaginator<int, EmrPatient>
     */
    public function searchPatients(string $search, int $perPage): LengthAwarePaginator;

    /**
     * A visit with its vital signs and doctor's notes, or null if there is none with this ID.
     */
    public function findVisit(string $visitId): ?EmrVisit;

    /**
     * All of a patient's visits, newest first, each with its vital signs and doctor's notes.
     *
     * @return Collection<int, EmrVisit>
     */
    public function visitsForPatient(string $patientId): Collection;

    /**
     * Latest vital signs for each patient, keyed by patient ID. Patients without any are left out.
     *
     * @param  list<string>  $patientIds
     * @return Collection<string, EmrVitalSigns>
     */
    public function latestVitalSigns(array $patientIds): Collection;

    /**
     * Every visit checked in today, in the order patients arrived.
     *
     * @return Collection<int, EmrVisit>
     */
    public function todaysVisits(): Collection;

    //Save the doctor's notes, menstrual history and diagnosis for a visit.
   
    public function saveConsultationNotes(string $visitId, ConsultationNotes $notes, string $doctorId): void;

    /**
     * Staff names by staff ID, to show who made a decision. Unknown IDs are left out.
     *
     * @param  list<string>  $staffIds
     * @return array<string, string>
     */
    public function staffNames(array $staffIds): array;

    // How staff log in. Laravel uses it to check passwords and find the logged-in user.
     
    public function loginProvider(): UserProvider;
}
