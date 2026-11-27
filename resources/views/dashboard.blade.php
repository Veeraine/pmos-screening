<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{ __('Welcome, :name. You are logged in as :role.', ['name' => auth()->user()->staffName(), 'role' => auth()->user()->staffRole()->label()]) }}
                </div>
            </div>

            @if (auth()->user()->hasRole(App\Role::Doctor))
                <div class="grid gap-6 md:grid-cols-2">
                    <section class="bg-white shadow-sm sm:rounded-lg p-6" aria-labelledby="open-alerts-heading">
                        <h3 id="open-alerts-heading" class="text-lg font-medium text-gray-900">
                            {{ __('Open alerts') }}
                            @if ($openAlerts->isNotEmpty())
                                <span class="ms-1 rounded-full bg-red-100 px-2 py-0.5 text-sm font-medium text-red-800">{{ $openAlerts->count() }}</span>
                            @endif
                        </h3>
                        @if ($openAlerts->isEmpty())
                            <p class="mt-2 text-sm text-gray-600">{{ __('No open screening alerts.') }}</p>
                        @else
                            <ul class="mt-3 divide-y divide-gray-200 text-sm">
                                @foreach ($openAlerts as $alert)
                                    <li class="flex items-center justify-between gap-2 py-2">
                                        <a href="{{ route('patients.show', $alert->emr_patient_id) }}" class="font-medium text-indigo-600 hover:underline">{{ $patients->get($alert->emr_patient_id)?->fullName() ?? __('Patient :id', ['id' => $alert->emr_patient_id]) }}</a>
                                        <span class="text-gray-600">{{ __(':count signs · raised :date', ['count' => $alert->featureCount(), 'date' => $alert->created_at->format('d M Y')]) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    <section class="bg-white shadow-sm sm:rounded-lg p-6" aria-labelledby="awaiting-heading">
                        <h3 id="awaiting-heading" class="text-lg font-medium text-gray-900">{{ __('Referred: awaiting results') }}</h3>
                        @if ($awaitingResults->isEmpty())
                            <p class="mt-2 text-sm text-gray-600">{{ __('No referrals are awaiting results.') }}</p>
                        @else
                            <ul class="mt-3 divide-y divide-gray-200 text-sm">
                                @foreach ($awaitingResults as $alert)
                                    <li class="flex items-center justify-between gap-2 py-2">
                                        <a href="{{ route('patients.show', $alert->emr_patient_id) }}" class="font-medium text-indigo-600 hover:underline">{{ $patients->get($alert->emr_patient_id)?->fullName() ?? __('Patient :id', ['id' => $alert->emr_patient_id]) }}</a>
                                        <span class="text-gray-600">{{ __('referred :date', ['date' => $alert->decided_at->format('d M Y')]) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>
            @endif

            @if (auth()->user()->hasRole(App\Role::Nurse, App\Role::Doctor))
                <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Find a patient') }}</h3>

                    <form method="GET" action="{{ route('patients.index') }}" class="flex gap-2">
                        <x-text-input name="search" type="search" class="block w-full"
                                      :placeholder="__('Patient number, name or phone')" autofocus />
                        <x-primary-button>{{ __('Search') }}</x-primary-button>
                    </form>

                    @if (auth()->user()->hasRole(App\Role::Nurse))
                        <p class="text-sm text-gray-600">
                            {{ __('New patient?') }}
                            <a href="{{ route('patients.create') }}" class="font-medium text-indigo-600 hover:underline">{{ __('Register patient') }}</a>
                        </p>
                    @endif
                </div>

                <div class="bg-white overflow-x-auto shadow-sm sm:rounded-lg">
                    <h3 class="px-4 pt-4 sm:px-6 text-lg font-medium text-gray-900">{{ __("Today's visits") }}</h3>

                    @if ($todaysVisits->isEmpty())
                        <p class="p-4 sm:px-6 text-sm text-gray-600">{{ __('No patients have been checked in today.') }}</p>
                    @else
                        <table class="mt-2 min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                <tr>
                                    <th class="px-6 py-3">{{ __('Checked in') }}</th>
                                    <th class="px-6 py-3">{{ __('Patient') }}</th>
                                    <th class="px-6 py-3">{{ __('Reason') }}</th>
                                    <th class="px-6 py-3">{{ __('Status') }}</th>
                                    <th class="px-6 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-gray-900">
                                @foreach ($todaysVisits as $visit)
                                    @php($visitPatient = $patients->get($visit->patientId))
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $visit->checkedInAt->format('H:i') }}</td>
                                        <td class="px-6 py-4">
                                            <a href="{{ route('patients.show', $visit->patientId) }}" class="font-medium hover:underline">{{ $visitPatient?->fullName() }}</a>
                                            <span class="ms-1 font-mono text-xs text-gray-500">{{ $visitPatient?->patientNumber }}</span>
                                        </td>
                                        <td class="px-6 py-4">{{ $visit->reason }}</td>
                                        <td class="px-6 py-4">
                                            @if ($visit->isConsulted())
                                                <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">{{ __('Seen') }}</span>
                                            @elseif ($visit->vitalSigns)
                                                <span class="inline-flex rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800">{{ __('Waiting for doctor') }}</span>
                                            @else
                                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800">{{ __('Waiting for vitals') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            @if (auth()->user()->hasRole(App\Role::Nurse) && ! $visit->vitalSigns)
                                                <a href="{{ route('vital-signs.edit', $visit->id) }}" class="font-medium text-indigo-600 hover:underline">{{ __('Record vitals') }}</a>
                                            @elseif (auth()->user()->hasRole(App\Role::Doctor) && ! $visit->isConsulted())
                                                <a href="{{ route('consultations.edit', $visit->id) }}" class="font-medium text-indigo-600 hover:underline">{{ __('Consult') }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
