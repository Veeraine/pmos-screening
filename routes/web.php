<?php

use App\Http\Controllers\AlertDecisionController;
use App\Http\Controllers\AlertOutcomeController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SimulatedMedix\PatientRegistrationController;
use App\Http\Controllers\SimulatedMedix\StaffActivationController;
use App\Http\Controllers\SimulatedMedix\StaffController;
use App\Http\Controllers\SimulatedMedix\VisitController;
use App\Http\Controllers\SimulatedMedix\VitalSignController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Stand-in Medix screens (registration, check-in, vital signs). With the real Medix these are done there.
    Route::middleware('role:nurse')->group(function () {
        Route::get('/patients/create', [PatientRegistrationController::class, 'create'])->name('patients.create');
        Route::post('/patients', [PatientRegistrationController::class, 'store'])->name('patients.store');

        Route::post('/patients/{patient}/visits', [VisitController::class, 'store'])->name('visits.store');

        Route::get('/visits/{visit}/vital-signs', [VitalSignController::class, 'edit'])->name('vital-signs.edit');
        Route::put('/visits/{visit}/vital-signs', [VitalSignController::class, 'update'])->name('vital-signs.update');
    });

    Route::middleware('role:doctor')->group(function () {
        Route::get('/visits/{visit}/consultation', [ConsultationController::class, 'edit'])->name('consultations.edit');
        Route::put('/visits/{visit}/consultation', [ConsultationController::class, 'update'])->name('consultations.update');

        Route::post('/alerts/{alert}/decision', [AlertDecisionController::class, 'store'])->name('alert-decisions.store');
        Route::post('/alerts/{alert}/outcome', [AlertOutcomeController::class, 'store'])->name('alert-outcomes.store');

        Route::put('/alerts/{alert}/decision', [AlertDecisionController::class, 'update'])->name('alert-decisions.update');
        Route::put('/alerts/{alert}/outcome', [AlertOutcomeController::class, 'update'])->name('alert-outcomes.update');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    });

    Route::middleware('role:nurse,doctor')->group(function () {
        Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('/patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
    });

    // Staff accounts also stand in for Medix, which holds the real staff logins.
    Route::middleware('role:admin')->group(function () {
        Route::resource('staff', StaffController::class)
            ->only(['index', 'create', 'store'])
            ->parameters(['staff' => 'user']);

        Route::post('/staff/{user}/activation', [StaffActivationController::class, 'store'])
            ->name('staff.activation.store');
        Route::delete('/staff/{user}/activation', [StaffActivationController::class, 'destroy'])
            ->name('staff.activation.destroy');
    });
});

require __DIR__.'/auth.php';
