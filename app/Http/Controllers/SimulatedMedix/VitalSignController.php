<?php

namespace App\Http\Controllers\SimulatedMedix;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveVitalSignsRequest;
use App\Models\Visit;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Recording vital signs in the simulated Medix 
 */
class VitalSignController extends Controller
{

    // Shows the vital signs form. Height is only needed at the first visit, after that the last one is shown.

    public function edit(Visit $visit): View
    {
        $this->ensureVisitIsToday($visit);

        $visit->load(['patient', 'vitalSigns']);

        return view('vital-signs.edit', [
            'visit' => $visit,
            'vitalSigns' => $visit->vitalSigns,
            'lastRecordedHeight' => $visit->lastRecordedHeight(),
        ]);
    }

    // Save (or correct) the vital signs for a visit.
    
    public function update(SaveVitalSignsRequest $request, Visit $visit): RedirectResponse
    {
        $this->ensureVisitIsToday($visit);

        $vitalSigns = $visit->vitalSigns ?? $visit->vitalSigns()->make();
        $vitalSigns->fill($request->validated());
        $vitalSigns->recorded_by = $request->user()->id;
        $vitalSigns->save();

        return redirect()
            ->route('dashboard')
            ->with('status', __('Vital signs saved for :name.', ['name' => $visit->patient->full_name]));
    }

    private function ensureVisitIsToday(Visit $visit): void
    {
        abort_unless($visit->visit_date->isToday(), 403, __('Vital signs can only be recorded on the day of the visit.'));
    }
}
