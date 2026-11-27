<?php

namespace App\Http\Controllers;

use App\Emr\EmrAdapter;
use App\Models\Alert;
use App\Role;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Shows the dashboard. Nurses and doctors see today's visits, doctors also see open alerts
     * and referrals waiting for results. Admins don't see patients.
     */
    public function __invoke(Request $request, EmrAdapter $emr): View
    {
        $user = $request->user();
        $isDoctor = $user->hasRole(Role::Doctor);

        $todaysVisits = $user->hasRole(Role::Nurse, Role::Doctor) ? $emr->todaysVisits() : collect();
        $openAlerts = $isDoctor ? Alert::open()->latest('id')->get() : collect();
        $awaitingResults = $isDoctor ? Alert::awaitingResults()->oldest('decided_at')->get() : collect();

        $patientIds = $todaysVisits->pluck('patientId')
            ->merge($openAlerts->pluck('emr_patient_id'))
            ->merge($awaitingResults->pluck('emr_patient_id'))
            ->unique()
            ->values()
            ->all();

        return view('dashboard', [
            'todaysVisits' => $todaysVisits,
            'openAlerts' => $openAlerts,
            'awaitingResults' => $awaitingResults,
            'patients' => $emr->findPatients($patientIds),
        ]);
    }
}
