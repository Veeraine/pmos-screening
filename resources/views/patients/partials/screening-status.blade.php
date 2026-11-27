{{--
    Screening status at the top of the record. Doctors see the alert details and forms,
    nurses only see the status line.
--}}
@php
    $isDoctor = auth()->user()->hasRole(App\Role::Doctor);
    $diagnosis = $alerts->pmosDiagnosis();
    $latestAlert = $alerts->latest();
@endphp

@if ($diagnosis)
    <section class="rounded-md border border-purple-300 bg-purple-50 p-4 sm:p-6" aria-label="{{ __('Screening status') }}">
        <p class="font-semibold text-purple-900">{{ __('Diagnosed with PMOS (:date)', ['date' => $diagnosis->outcome_date->format('d M Y')]) }}</p>
        <p class="mt-1 text-sm text-purple-900">{{ __('Screening alerts are no longer raised for this patient. Symptoms are still recorded as normal.') }}</p>
    </section>
@elseif ($latestAlert?->isOpen())
    @if ($isDoctor)
        @include('patients.partials.open-alert', ['alert' => $latestAlert])
    @else
        <section class="rounded-md border border-red-300 bg-red-50 p-4 text-sm font-medium text-red-900" aria-label="{{ __('Screening status') }}">
            {{ __('Screening alert open — waiting for the doctor to review it.') }}
        </section>
    @endif
@elseif ($latestAlert?->isAwaitingResults())
    <section class="rounded-md border border-amber-300 bg-amber-50 p-4 sm:p-6" aria-label="{{ __('Screening status') }}">
        <p class="font-semibold text-amber-900">{{ __('Referred: awaiting results') }}</p>
        <p class="mt-1 text-sm text-amber-900">
            {{ __('Referred for PMOS screening on :date by :name.', ['date' => $latestAlert->decided_at->format('d M Y'), 'name' => $staffNames[$latestAlert->decided_by] ?? __('unknown')]) }}
        </p>

        @if ($isDoctor)
            <form method="POST" action="{{ route('alert-outcomes.store', $latestAlert) }}" class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end">
                @csrf
                <fieldset>
                    <legend class="text-sm font-medium text-gray-700">{{ __('Result') }}</legend>
                    <div class="mt-1 flex gap-4 text-sm text-gray-900">
                        @foreach (App\ScreeningOutcome::cases() as $outcome)
                            <label class="flex items-center gap-2">
                                <input type="radio" name="outcome" value="{{ $outcome->value }}" @checked(old('outcome') === $outcome->value)
                                       class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                {{ $outcome->label() }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div>
                    <x-input-label for="outcome_date" :value="__('Date of result')" />
                    <x-text-input id="outcome_date" name="outcome_date" type="date" class="mt-1 block" :value="old('outcome_date')"
                                  :min="$latestAlert->decided_at->toDateString()" :max="now()->toDateString()" />
                </div>
                <x-primary-button>{{ __('Record result') }}</x-primary-button>
            </form>
            <x-input-error :messages="$errors->get('outcome')" class="mt-2" />
            <x-input-error :messages="$errors->get('outcome_date')" class="mt-2" />
        @endif
    </section>
@elseif ($latestAlert?->outcome === App\ScreeningOutcome::RuledOut)
    <section class="rounded-md border border-gray-300 bg-gray-50 p-4 sm:p-6" aria-label="{{ __('Screening status') }}">
        <p class="font-semibold text-gray-900">{{ __('PMOS ruled out (:date)', ['date' => $latestAlert->outcome_date->format('d M Y')]) }}</p>
        <p class="mt-1 text-sm text-gray-700">{{ __('A new alert will only be raised if new PMOS-related symptoms are confirmed after this date.') }}</p>
    </section>
@endif
