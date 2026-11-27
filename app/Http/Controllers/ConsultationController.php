<?php

namespace App\Http\Controllers;

use App\Emr\ConsultationNotes;
use App\Emr\EmrAdapter;
use App\Emr\EmrVisit;
use App\Http\Requests\SaveConsultationRequest;
use App\Models\ConfirmedSymptom;
use App\Models\Symptom;
use App\Screening\AlertRaiser;
use App\Screening\ScreeningCheck;
use App\Screening\SymptomDetector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ConsultationController extends Controller
{
    public function __construct(private EmrAdapter $emr) {}

    /**
     * Shows the consultation form with the vital signs, the notes box, and any symptom suggestions to confirm.
     */
    public function edit(string $visit, SymptomDetector $detector): View
    {
        $emrVisit = $this->findTodaysVisit($visit);
        $confirmed = ConfirmedSymptom::forVisit($emrVisit->id)->get();

        $suggestions = $detector->detect(SymptomDetector::combine(
            old('notes', $emrVisit->notes),
            old('menstrual_history', $emrVisit->menstrualHistory),
        ));
        $symptomsByCode = Symptom::inListOrder()->get()->keyBy('code');
        $confirmedCodes = $symptomsByCode->whereIn('id', $confirmed->pluck('symptom_id'))->keys()->all();

        return view('consultations.edit', [
            'visit' => $emrVisit,
            'patient' => $this->emr->findPatient($emrVisit->patientId),
            'symptomsByCode' => $symptomsByCode,
            'suggestions' => $suggestions,
            'decisions' => collect($suggestions)->mapWithKeys(fn ($suggestion) => [
                $suggestion->code => old("decisions.{$suggestion->code}", $this->savedDecision($emrVisit, $suggestion->code, $confirmedCodes)),
            ]),
            'addedSymptomIds' => array_map('intval', array_filter(old('added_symptoms', $confirmed
                ->where('source', ConfirmedSymptom::SOURCE_MANUAL)
                ->pluck('symptom_id')
                ->all()))),
        ]);
    }

    // Saves the consultation. Notes go to the EMR, confirmed symptoms are stored here.
     
    public function update(SaveConsultationRequest $request, string $visit, ScreeningCheck $screeningCheck, AlertRaiser $alertRaiser): RedirectResponse
    {
        $emrVisit = $this->findTodaysVisit($visit);
        $doctorId = $request->user()->staffId();

        $symptomIdsByCode = Symptom::pluck('id', 'code');
        $symptomsToStore = [];

        foreach ($request->detectedSymptoms() as $detected) {
            if ($request->decisionFor($detected->code) === 'confirm') {
                $symptomsToStore[$symptomIdsByCode[$detected->code]] = [
                    'source' => ConfirmedSymptom::SOURCE_DETECTED,
                    'matched_text' => Str::limit($detected->excerpt, 250),
                ];
            }
        }

        foreach ($request->addedSymptomIds() as $symptomId) {
            $symptomsToStore[$symptomId] ??= ['source' => ConfirmedSymptom::SOURCE_MANUAL, 'matched_text' => null];
        }

        DB::transaction(function () use ($request, $emrVisit, $doctorId, $symptomsToStore): void {
            $this->emr->saveConsultationNotes($emrVisit->id, new ConsultationNotes(
                notes: $request->validated('notes'),
                menstrualHistory: $request->validated('menstrual_history'),
                diagnosis: $request->validated('diagnosis'),
            ), $doctorId);

            $this->replaceConfirmedSymptoms($emrVisit, $doctorId, $symptomsToStore);
        });

        $screening = $screeningCheck->run($emrVisit->patientId);
        $alert = $alertRaiser->raiseIfNeeded($emrVisit->patientId, $emrVisit->id);

        $message = __('Consultation saved for :name. Screening check: :found of :total Rotterdam signs found.', [
            'name' => $this->emr->findPatient($emrVisit->patientId)?->fullName(),
            'found' => $screening->foundCount(),
            'total' => count($screening->features),
        ]);

        if ($alert) {
            $message .= ' '.__('A screening alert has been raised. Please review it below.');
        }

        return redirect()
            ->route('patients.show', $emrVisit->patientId)
            ->with('status', $message);
    }

    /**
     * Updates the visit's confirmed symptoms. Kept ones stay as they were, removed ones are deleted.
     *
     * @param  array<int, array{source: string, matched_text: ?string}>  $symptomsToStore  Keyed by symptom ID.
     */
    private function replaceConfirmedSymptoms(EmrVisit $visit, string $doctorId, array $symptomsToStore): void
    {
        ConfirmedSymptom::forVisit($visit->id)->whereNotIn('symptom_id', array_keys($symptomsToStore))->delete();

        foreach ($symptomsToStore as $symptomId => $details) {
            $confirmed = ConfirmedSymptom::forVisit($visit->id)->where('symptom_id', $symptomId)->first() ?? new ConfirmedSymptom;
            $confirmed->emr_patient_id = $visit->patientId;
            $confirmed->emr_visit_id = $visit->id;
            $confirmed->symptom_id = $symptomId;
            $confirmed->source = $details['source'];
            $confirmed->matched_text = $details['matched_text'];
            $confirmed->confirmed_by ??= $doctorId;
            $confirmed->save();
        }
    }

    /**
     * When reopening today's consultation, shows the doctor's earlier choices so they aren't asked twice.
     *
     * @param  list<string>  $confirmedCodes
     */
    private function savedDecision(EmrVisit $visit, string $symptomCode, array $confirmedCodes): ?string
    {
        if (! $visit->isConsulted()) {
            return null;
        }

        return in_array($symptomCode, $confirmedCodes, true) ? 'confirm' : 'remove';
    }

    /**
     * Gets the visit from the EMR. Consultations can only be changed on the day of the visit.
     */
    private function findTodaysVisit(string $visitId): EmrVisit
    {
        $visit = $this->emr->findVisit($visitId);

        abort_if($visit === null, 404);
        abort_unless($visit->isToday(), 403, __('A consultation can only be recorded on the day of the visit.'));

        return $visit;
    }
}
