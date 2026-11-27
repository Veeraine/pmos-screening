<?php

namespace App\Http\Controllers;

use App\AlertStatus;
use App\Http\Requests\CorrectDecisionRequest;
use App\Http\Requests\DecideAlertRequest;
use App\Models\Alert;
use App\Models\AlertCorrection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AlertDecisionController extends Controller
{
    /**
     * Records if the patient was referred and closes the alert if they were.
     * Only open alerts can be decided, so two doctors can't both decide the same one.
     */
    public function store(DecideAlertRequest $request, Alert $alert): RedirectResponse
    {
        $referred = $request->boolean('referred');

        $updated = Alert::whereKey($alert->id)->open()->update([
            'status' => AlertStatus::Closed,
            'referred' => $referred,
            'not_referred_reason' => $referred ? null : $request->validated('not_referred_reason'),
            'decided_by' => $request->user()->staffId(),
            'decided_at' => now(),
        ]);

        if ($updated === 0) {
            return back()->withErrors(['decision' => __('This alert has already been decided.')]);
        }

        return redirect()
            ->route('patients.show', $alert->emr_patient_id)
            ->with('status', $referred
                ? __('Referral recorded. The patient is now awaiting results.')
                : __('Decision recorded: not referred.'));
    }

    /**
     * Corrects a decision, the old and new values and the reason are kept, so nothing is lost.
     */
    public function update(CorrectDecisionRequest $request, Alert $alert): RedirectResponse
    {
        $referred = $request->boolean('referred');
        $newValues = [
            'referred' => $referred,
            'not_referred_reason' => $referred ? null : $request->validated('not_referred_reason'),
        ];

        $problem = DB::transaction(function () use ($request, $alert, $newValues): ?string {
            $alert = Alert::lockForUpdate()->findOrFail($alert->id);

            if ($alert->isOpen() || $alert->outcome !== null || ! $alert->isLatestForPatient()) {
                return __('This decision can no longer be corrected.');
            }

            $previousValues = ['referred' => $alert->referred, 'not_referred_reason' => $alert->not_referred_reason];

            if ($previousValues === $newValues) {
                return __('The corrected decision is the same as the current one.');
            }

            $correction = new AlertCorrection;
            $correction->forceFill([
                'alert_id' => $alert->id,
                'field' => AlertCorrection::FIELD_DECISION,
                'previous_values' => $previousValues,
                'new_values' => $newValues,
                'reason' => $request->validated('correction_reason'),
                'corrected_by' => $request->user()->staffId(),
            ])->save();

            $alert->forceFill($newValues)->save();

            return null;
        });

        if ($problem) {
            return back()->withErrors(['correction' => $problem])->withInput();
        }

        return redirect()
            ->route('patients.show', $alert->emr_patient_id)
            ->with('status', __('Decision corrected. The original is kept in the alert history.'));
    }
}
