<?php

namespace App\Http\Controllers;

use App\Http\Requests\CorrectOutcomeRequest;
use App\Http\Requests\RecordOutcomeRequest;
use App\Models\Alert;
use App\Models\AlertCorrection;
use App\ScreeningOutcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AlertOutcomeController extends Controller
{
    /**
     * Records the referral result (confirmed or ruled out). Only for alerts still waiting on results.
     */
    public function store(RecordOutcomeRequest $request, Alert $alert): RedirectResponse
    {
        $outcome = ScreeningOutcome::from($request->validated('outcome'));

        $updated = Alert::whereKey($alert->id)->awaitingResults()->update([
            'outcome' => $outcome,
            'outcome_date' => $request->validated('outcome_date'),
            'outcome_recorded_by' => $request->user()->staffId(),
            'outcome_recorded_at' => now(),
        ]);

        if ($updated === 0) {
            return back()->withErrors(['outcome' => __('This referral is not awaiting results.')]);
        }

        return redirect()
            ->route('patients.show', $alert->emr_patient_id)
            ->with('status', __('Result recorded: :outcome.', ['outcome' => $outcome->label()]));
    }

    // Corrects a referral result the old and new values and the reason are kept, so nothing is lost.

    public function update(CorrectOutcomeRequest $request, Alert $alert): RedirectResponse
    {
        $newValues = [
            'outcome' => $request->validated('outcome'),
            'outcome_date' => $request->validated('outcome_date'),
        ];

        $problem = DB::transaction(function () use ($request, $alert, $newValues): ?string {
            $alert = Alert::lockForUpdate()->findOrFail($alert->id);

            if ($alert->outcome === null || ! $alert->isLatestForPatient()) {
                return __('This result can no longer be corrected.');
            }

            $previousValues = ['outcome' => $alert->outcome->value, 'outcome_date' => $alert->outcome_date->toDateString()];

            if ($previousValues === $newValues) {
                return __('The corrected result is the same as the current one.');
            }

            $correction = new AlertCorrection;
            $correction->forceFill([
                'alert_id' => $alert->id,
                'field' => AlertCorrection::FIELD_OUTCOME,
                'previous_values' => $previousValues,
                'new_values' => $newValues,
                'reason' => $request->validated('correction_reason'),
                'corrected_by' => $request->user()->staffId(),
            ])->save();

            $alert->forceFill([
                'outcome' => ScreeningOutcome::from($newValues['outcome']),
                'outcome_date' => $newValues['outcome_date'],
            ])->save();

            return null;
        });

        if ($problem) {
            return back()->withErrors(['correction' => $problem])->withInput();
        }

        return redirect()
            ->route('patients.show', $alert->emr_patient_id)
            ->with('status', __('Result corrected. The original is kept in the alert history.'));
    }
}
