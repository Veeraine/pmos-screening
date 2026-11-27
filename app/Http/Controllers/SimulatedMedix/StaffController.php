<?php

namespace App\Http\Controllers\SimulatedMedix;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffRequest;
use App\Models\User;
use App\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Staff accounts in the simulated Medix (the real Medix would manage its own).
 */
class StaffController extends Controller
{
    // List all staff accounts, active accounts first.

    public function index(): View
    {
        return view('staff.index', [
            'staff' => User::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('staff.create', [
            'roles' => Role::cases(),
        ]);
    }

    // Create a new staff account.

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        return redirect()
            ->route('staff.index')
            ->with('status', __(':name was added as :role.', [
                'name' => $user->name,
                'role' => $user->role->label(),
            ]));
    }
}
