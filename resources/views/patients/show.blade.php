<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $patient->fullName() }}</h2>
            <span class="font-mono text-gray-500">{{ $patient->patientNumber }}</span>
            <span class="text-sm text-gray-600">
                {{ __(':age years', ['age' => $patient->age()]) }} · {{ __('born :date', ['date' => $patient->dateOfBirth->format('d M Y')]) }}
                @if ($patient->phone) · {{ $patient->phone }} @endif
                @if ($patient->address) · {{ $patient->address }} @endif
            </span>
        </div>
    </x-slot>

    @php
        $latestVitals = $latestVitalsVisit?->vitalSigns;
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Screening status and any open alert (requirements 5–7) --}}
            @include('patients.partials.screening-status')

            {{-- Patient summary (requirement 8) --}}
            <section class="p-4 sm:p-6 bg-white shadow sm:rounded-lg" aria-labelledby="summary-heading">
                <h3 id="summary-heading" class="text-lg font-medium text-gray-900">{{ __('Summary') }}</h3>

                <div class="mt-4 grid gap-6 lg:grid-cols-3">
                    <div>
                        <h4 class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Latest vital signs') }}</h4>
                        @if ($latestVitals)
                            <dl class="mt-2 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <dt class="text-gray-500">{{ __('Weight') }}</dt>
                                    <dd class="text-lg font-semibold text-gray-900">{{ $latestVitals->weightKg }} kg</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500">{{ __('BMI') }}</dt>
                                    <dd class="text-lg font-semibold text-gray-900">{{ $latestVitals->bmi }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500">{{ __('Blood pressure') }}</dt>
                                    <dd class="text-lg font-semibold text-gray-900">{{ $latestVitals->bloodPressure() }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500">{{ __('Temperature') }}</dt>
                                    <dd class="text-lg font-semibold text-gray-900">{{ $latestVitals->temperatureC }} °C</dd>
                                </div>
                            </dl>
                            <p class="mt-2 text-xs text-gray-500">
                                {{ __('Measured :date', ['date' => $latestVitalsVisit->date->format('d M Y')]) }}
                            </p>
                        @else
                            <p class="mt-2 text-sm text-gray-600">{{ __('No vital signs recorded yet.') }}</p>
                        @endif
                    </div>

                    <div>
                        <h4 class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Menstrual history') }}</h4>
                        @if ($latestMenstrualHistoryVisit)
                            <p class="mt-2 whitespace-pre-line text-sm text-gray-900">{{ $latestMenstrualHistoryVisit->menstrualHistory }}</p>
                            <p class="mt-2 text-xs text-gray-500">{{ __('Recorded :date', ['date' => $latestMenstrualHistoryVisit->date->format('d M Y')]) }}</p>
                        @else
                            <p class="mt-2 text-sm text-gray-600">{{ __('Not recorded yet.') }}</p>
                        @endif
                    </div>

                    <div>
                        <h4 class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Previous diagnoses') }}</h4>
                        @if ($diagnosedVisits->isEmpty() && blank($patient->previousDiagnoses))
                            <p class="mt-2 text-sm text-gray-600">{{ __('None recorded.') }}</p>
                        @else
                            <ul class="mt-2 space-y-1 text-sm text-gray-900">
                                @foreach ($diagnosedVisits as $visit)
                                    <li>
                                        {{ $visit->diagnosis }}
                                        <span class="text-xs text-gray-500">({{ $visit->date->format('d M Y') }})</span>
                                    </li>
                                @endforeach
                                @if (filled($patient->previousDiagnoses))
                                    <li class="whitespace-pre-line">
                                        {{ $patient->previousDiagnoses }}
                                        <span class="text-xs text-gray-500">({{ __('before registration') }})</span>
                                    </li>
                                @endif
                            </ul>
                        @endif
                    </div>
                </div>
            </section>

            {{-- Screening check against the Rotterdam signs (doctors only) --}}
            @if ($screening)
                <section class="p-4 sm:p-6 bg-white shadow sm:rounded-lg" aria-labelledby="screening-heading">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h3 id="screening-heading" class="text-lg font-medium text-gray-900">{{ __('Screening check (Rotterdam signs)') }}</h3>
                        <p @class([
                            'text-sm font-medium',
                            'text-red-700' => $screening->meetsThreshold(),
                            'text-gray-600' => ! $screening->meetsThreshold(),
                        ])>
                            {{ __(':found of :total signs found (threshold: :threshold).', [
                                'found' => $screening->foundCount(),
                                'total' => count($screening->features),
                                'threshold' => $screening->threshold,
                            ]) }}
                        </p>
                    </div>

                    <ul class="mt-4 grid gap-4 md:grid-cols-3">
                        @foreach ($screening->features as $feature)
                            <li @class([
                                'rounded-md border p-3 text-sm',
                                'border-red-200 bg-red-50' => $feature->isFound(),
                                'border-gray-200' => ! $feature->isFound(),
                            ])>
                                <p class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-gray-900">{{ $feature->label }}</span>
                                    @if ($feature->isFound())
                                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">{{ __('Found') }}</span>
                                    @else
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ __('Not found') }}</span>
                                    @endif
                                </p>

                                @if ($feature->isFound())
                                    <ul class="mt-2 space-y-1 text-gray-700">
                                        @foreach ($feature->evidence as $item)
                                            <li>
                                                {{ $item['confirmed']->symptom->name }}
                                                <span class="text-xs text-gray-500">— {{ $item['visit']->date->format('d M Y') }}</span>
                                                @if ($item['confirmed']->matched_text)
                                                    <span class="block text-xs italic text-gray-500">"{{ $item['confirmed']->matched_text }}"</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Today's visit: actions for the nurse or doctor --}}
            @if (auth()->user()->hasRole(App\Role::Nurse))
                <section class="p-4 sm:p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900">{{ __("Today's visit") }}</h3>

                    @if ($todaysVisit)
                        <p class="mt-2 text-sm text-gray-700">
                            {{ __('Checked in at :time for ":reason".', ['time' => $todaysVisit->checkedInAt->format('H:i'), 'reason' => $todaysVisit->reason]) }}
                        </p>
                        <a href="{{ route('vital-signs.edit', $todaysVisit->id) }}"
                           class="mt-4 inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            {{ $todaysVisit->vitalSigns ? __('Correct vital signs') : __('Record vital signs') }}
                        </a>
                    @else
                        <form method="POST" action="{{ route('visits.store', $patient->id) }}" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-start">
                            @csrf
                            <div class="grow">
                                <x-input-label for="reason" :value="__('Reason for visit')" class="sr-only" />
                                <x-text-input id="reason" name="reason" type="text" class="block w-full" :value="old('reason')"
                                              :placeholder="__('Reason for visit, e.g. headache, follow-up')" required />
                                <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                            </div>
                            <x-primary-button>{{ __('Check in') }}</x-primary-button>
                        </form>
                    @endif
                </section>
            @endif

            @if (auth()->user()->hasRole(App\Role::Doctor) && $todaysVisit)
                <section class="p-4 sm:p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900">{{ __("Today's visit") }}</h3>
                    <p class="mt-2 text-sm text-gray-700">
                        {{ __('Checked in at :time for ":reason".', ['time' => $todaysVisit->checkedInAt->format('H:i'), 'reason' => $todaysVisit->reason]) }}
                    </p>
                    <a href="{{ route('consultations.edit', $todaysVisit->id) }}"
                       class="mt-4 inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        {{ $todaysVisit->isConsulted() ? __('Edit consultation') : __('Start consultation') }}
                    </a>
                </section>
            @endif

            {{-- Full visit history (requirement 3), including unrelated illnesses --}}
            <section aria-labelledby="history-heading">
                <h3 id="history-heading" class="px-4 sm:px-0 text-lg font-medium text-gray-900">
                    {{ __('Visit history') }}
                    <span class="text-sm font-normal text-gray-500">({{ trans_choice(':count visit|:count visits', $visits->count()) }})</span>
                </h3>

                @if ($visits->isEmpty())
                    <p class="mt-2 p-4 sm:p-6 bg-white shadow sm:rounded-lg text-sm text-gray-600">{{ __('No visits yet.') }}</p>
                @else
                    <ol class="mt-2 space-y-3">
                        @foreach ($visits as $visit)
                            <li class="p-4 sm:p-6 bg-white shadow sm:rounded-lg">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <p class="font-medium text-gray-900">
                                        {{ $visit->date->format('d M Y') }}
                                        <span class="font-normal text-gray-700">— {{ $visit->reason }}</span>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ __('Checked in by :name', ['name' => $visit->checkedInByName]) }}
                                        @if ($visit->consultedByName) · {{ __('Seen by :name', ['name' => $visit->consultedByName]) }} @endif
                                    </p>
                                </div>

                                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                                    <div class="sm:col-span-2">
                                        <dt class="sr-only">{{ __('Vital signs') }}</dt>
                                        <dd class="text-gray-700">
                                            @if ($visit->vitalSigns)
                                                {{ $visit->vitalSigns->weightKg }} kg
                                                · {{ __('BMI') }} {{ $visit->vitalSigns->bmi }}
                                                · {{ __('BP') }} {{ $visit->vitalSigns->bloodPressure() }}
                                                · {{ $visit->vitalSigns->temperatureC }} °C
                                                · {{ $visit->vitalSigns->pulseRate }} bpm
                                            @else
                                                <span class="text-gray-500">{{ __('Vital signs not recorded') }}</span>
                                            @endif
                                        </dd>
                                    </div>

                                    @php($visitSymptoms = $confirmedSymptomsByVisit->get($visit->id, collect()))
                                    <div class="sm:col-span-2">
                                        <dt class="text-gray-500">{{ __('Symptoms') }}</dt>
                                        <dd class="mt-1">
                                            @if (! $visit->isConsulted())
                                                <span class="text-gray-500">{{ __('Not seen by doctor yet') }}</span>
                                            @elseif ($visitSymptoms->isEmpty())
                                                <span class="text-gray-700">{{ __('None') }}</span>
                                            @else
                                                <ul class="flex flex-wrap gap-2">
                                                    @foreach ($visitSymptoms as $confirmed)
                                                        <li class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-800"
                                                            title="{{ $confirmed->source === App\Models\ConfirmedSymptom::SOURCE_DETECTED ? __('From the notes: ":text"', ['text' => $confirmed->matched_text]) : __('Added by hand') }}">{{ $confirmed->symptom->name }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </dd>
                                    </div>

                                    @if (filled($visit->notes))
                                        <div class="sm:col-span-2">
                                            <dt class="text-gray-500">{{ __('Notes') }}</dt>
                                            <dd class="whitespace-pre-line text-gray-900">{{ $visit->notes }}</dd>
                                        </div>
                                    @endif

                                    @if (filled($visit->menstrualHistory))
                                        <div>
                                            <dt class="text-gray-500">{{ __('Menstrual history') }}</dt>
                                            <dd class="whitespace-pre-line text-gray-900">{{ $visit->menstrualHistory }}</dd>
                                        </div>
                                    @endif

                                    @if (filled($visit->diagnosis))
                                        <div>
                                            <dt class="text-gray-500">{{ __('Diagnosis') }}</dt>
                                            <dd class="text-gray-900">{{ $visit->diagnosis }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            {{-- Alert history: every alert, decision and outcome (doctors only) --}}
            @if (auth()->user()->hasRole(App\Role::Doctor) && ! $alerts->isEmpty())
                <section class="p-4 sm:p-6 bg-white shadow sm:rounded-lg" aria-labelledby="alert-history-heading">
                    <h3 id="alert-history-heading" class="text-lg font-medium text-gray-900">{{ __('Alert history') }}</h3>
                    <ol class="mt-3 divide-y divide-gray-200 text-sm">
                        @foreach ($alerts->alerts as $alert)
                            <li class="py-3">
                                <p class="font-medium text-gray-900">
                                    {{ __('Alert raised :date', ['date' => $alert->created_at->format('d M Y')]) }}
                                    <span class="font-normal text-gray-600">— {{ collect($alert->findings)->pluck('label')->join(', ') }}</span>
                                </p>
                                <p class="text-gray-700">
                                    @if ($alert->isOpen())
                                        {{ __('Waiting for a decision.') }}
                                    @elseif ($alert->referred)
                                        {{ __('Referred :date by :name.', ['date' => $alert->decided_at->format('d M Y'), 'name' => $staffNames[$alert->decided_by] ?? __('unknown')]) }}
                                        @if ($alert->outcome)
                                            {{ __('Result: :outcome (:date).', ['outcome' => $alert->outcome->label(), 'date' => $alert->outcome_date->format('d M Y')]) }}
                                        @else
                                            {{ __('Awaiting results.') }}
                                        @endif
                                    @else
                                        {{ __('Not referred :date by :name: ":reason"', [
                                            'date' => $alert->decided_at->format('d M Y'),
                                            'name' => $staffNames[$alert->decided_by] ?? __('unknown'),
                                            'reason' => $alert->not_referred_reason,
                                        ]) }}
                                    @endif
                                </p>

                                @foreach ($alert->corrections as $correction)
                                    <p class="mt-1 rounded bg-yellow-50 px-2 py-1 text-xs text-gray-800">
                                        {{ __('Corrected :date by :name: :before → :after. Reason: :reason', [
                                            'date' => $correction->created_at->format('d M Y'),
                                            'name' => $staffNames[$correction->corrected_by] ?? __('unknown'),
                                            'before' => $correction->previousSummary(),
                                            'after' => $correction->newSummary(),
                                            'reason' => $correction->reason,
                                        ]) }}
                                    </p>
                                @endforeach

                                @if ($loop->first && ! $alert->isOpen())
                                    @include('patients.partials.correct-alert', ['alert' => $alert])
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif

            <p class="px-4 sm:px-0 text-xs text-gray-500">
                {{ __('Registered :date by :name', ['date' => $patient->registeredAt->format('d M Y'), 'name' => $patient->registeredByName]) }}
                · {{ __('Patient details, visits and vital signs come from :emr.', ['emr' => $emrName]) }}
            </p>
        </div>
    </div>
</x-app-layout>
