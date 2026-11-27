<?php

namespace App\Http\Controllers;

use App\Emr\EmrAdapter;
use App\Emr\EmrVisit;
use App\Models\ConfirmedSymptom;
use App\Role;
use App\Screening\AlertHistory;
use App\Screening\ScreeningCheck;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function __construct(private EmrAdapter $emr) {}

    /**
     * Searches patients by number, name or phone. 
     */
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();

        return view('patients.index', [
            'patients' => $this->emr->searchPatients($search, 25)->withQueryString(),
            'search' => $search,
        ]);
    }

    /**
     * Shows the patient record: details and visits from the EMR plus our screening data (symptoms, alerts, outcomes).
     */
    public function show(Request $request, string $patient, ScreeningCheck $screeningCheck): View
    {
        $emrPatient = $this->emr->findPatient($patient);

        abort_if($emrPatient === null, 404);

        $visits = $this->emr->visitsForPatient($emrPatient->id);
        $alerts = AlertHistory::forPatient($emrPatient->id, ['corrections']);

        return view('patients.show', [
            'patient' => $emrPatient,
            'emrName' => $this->emr->systemName(),
            'visits' => $visits,
            'confirmedSymptomsByVisit' => ConfirmedSymptom::forPatient($emrPatient->id)
                ->with('symptom')
                ->get()
                ->sortBy('symptom.sort_order')
                ->groupBy('emr_visit_id'),
            'latestVitalsVisit' => $visits->first(fn (EmrVisit $visit): bool => $visit->vitalSigns !== null),
            'latestMenstrualHistoryVisit' => $visits->first(fn (EmrVisit $visit): bool => filled($visit->menstrualHistory)),
            'diagnosedVisits' => $visits->filter(fn (EmrVisit $visit): bool => filled($visit->diagnosis))->values(),
            'todaysVisit' => $visits->first(fn (EmrVisit $visit): bool => $visit->isToday()),
            'alerts' => $alerts,
            'staffNames' => $this->emr->staffNames($alerts->staffIds()),
            'screening' => $request->user()->hasRole(Role::Doctor) ? $screeningCheck->run($emrPatient->id, $visits) : null,
        ]);
    }
}
