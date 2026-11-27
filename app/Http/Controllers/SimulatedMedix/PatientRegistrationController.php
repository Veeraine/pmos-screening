<?php

namespace App\Http\Controllers\SimulatedMedix;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Patient registration in the simulated Medix 
 */
class PatientRegistrationController extends Controller
{
    //Show the form for registering a new patient.
    public function create(): View
    {
        return view('patients.create');
    }

    // Register a new patient. They get their patient number automatically.
     
    public function store(StorePatientRequest $request): RedirectResponse
    {
        $patient = new Patient($request->safe()->except('confirmed_different_person'));
        $patient->registered_by = $request->user()->id;
        $patient->save();

        return redirect()
            ->route('patients.show', $patient)
            ->with('status', __(':name has been registered with patient number :number.', [
                'name' => $patient->full_name,
                'number' => $patient->patient_number,
            ]));
    }
}
