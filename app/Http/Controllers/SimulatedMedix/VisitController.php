<?php

namespace App\Http\Controllers\SimulatedMedix;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVisitRequest;
use App\Models\Patient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;

/**
 * Checking patients in, in the simulated Medix 
 */
class VisitController extends Controller
{
    // Checks a patient in for today, then goes straight to vital signs.

    public function store(StoreVisitRequest $request, Patient $patient): RedirectResponse
    {
        $visit = $patient->visits()->make([
            'visit_date' => today(),
            'reason' => $request->validated('reason'),
        ]);
        $visit->checked_in_by = $request->user()->id;

        try {
            $visit->save();
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors(['reason' => __('This patient has already been checked in today.')]);
        }

        return redirect()
            ->route('vital-signs.edit', $visit)
            ->with('status', __(':name has been checked in.', ['name' => $patient->full_name]));
    }
}
