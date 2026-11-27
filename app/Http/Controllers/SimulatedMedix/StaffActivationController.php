<?php

namespace App\Http\Controllers\SimulatedMedix;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Turns staff accounts on or off in the simulated Medix. Accounts are never deleted,
 * so old records still show who made them.
 */
class StaffActivationController extends Controller
{
    public function store(User $user): RedirectResponse
    {
        $user->update(['is_active' => true]);

        return back()->with('status', __(':name can log in again.', ['name' => $user->name]));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->withErrors([
                'staff' => __('You cannot deactivate your own account.'),
            ]);
        }

        $user->update(['is_active' => false]);

        return back()->with('status', __(':name has been deactivated.', ['name' => $user->name]));
    }
}
