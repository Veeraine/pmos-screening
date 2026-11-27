<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Consultation') }} —
            <a href="{{ route('patients.show', $patient->id) }}" class="hover:underline">{{ $patient->fullName() }}</a>
            <span class="ms-2 font-mono text-base font-normal text-gray-500">{{ $patient->patientNumber }}</span>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg space-y-4">
                <p class="text-sm text-gray-600">
                    {{ __('Visit on :date for ":reason". Age :age.', [
                        'date' => $visit->date->format('d M Y'),
                        'reason' => $visit->reason,
                        'age' => $patient->age(),
                    ]) }}
                </p>

                @if ($visit->vitalSigns)
                    <dl class="grid grid-cols-2 gap-4 rounded-md bg-gray-50 p-4 text-sm sm:grid-cols-5">
                        <div>
                            <dt class="text-gray-500">{{ __('Weight') }}</dt>
                            <dd class="font-medium text-gray-900">{{ $visit->vitalSigns->weightKg }} kg</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('BMI') }}</dt>
                            <dd class="font-medium text-gray-900">{{ $visit->vitalSigns->bmi }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('Blood pressure') }}</dt>
                            <dd class="font-medium text-gray-900">{{ $visit->vitalSigns->bloodPressure() }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('Temperature') }}</dt>
                            <dd class="font-medium text-gray-900">{{ $visit->vitalSigns->temperatureC }} °C</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('Pulse') }}</dt>
                            <dd class="font-medium text-gray-900">{{ $visit->vitalSigns->pulseRate }} bpm</dd>
                        </div>
                    </dl>
                @else
                    <p class="rounded-md bg-amber-50 p-4 text-sm text-amber-900">
                        {{ __('The nurse has not recorded vital signs for this visit yet.') }}
                    </p>
                @endif
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <form method="POST" action="{{ route('consultations.update', $visit->id) }}" class="max-w-3xl space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="notes" :value="__('Symptoms and notes')" />
                        <textarea id="notes" name="notes" rows="4" autofocus
                                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $visit->notes) }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="menstrual_history" :value="__('Menstrual history')" />
                        <textarea id="menstrual_history" name="menstrual_history" rows="2"
                                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('menstrual_history', $visit->menstrualHistory) }}</textarea>
                        <x-input-error :messages="$errors->get('menstrual_history')" class="mt-2" />
                    </div>

                    @if ($suggestions !== [])
                        <fieldset class="rounded-md border border-amber-300 bg-amber-50 p-4">
                            <legend class="px-1 font-medium text-amber-900">{{ __('Possible PMOS symptoms found in your notes') }}</legend>
                            <p class="text-sm text-amber-900">{{ __('Confirm or remove each one. Only confirmed symptoms are saved and used for screening.') }}</p>
                            <x-input-error :messages="$errors->get('decisions')" class="mt-2" />

                            <ul class="mt-4 space-y-3">
                                @foreach ($suggestions as $suggestion)
                                    @php($parts = $suggestion->excerptParts())
                                    <li class="flex flex-col gap-3 rounded-md bg-white p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                                        <div class="text-sm">
                                            <p class="font-medium text-gray-900">{{ $symptomsByCode[$suggestion->code]->name }}</p>
                                            <p class="text-gray-600">"{{ $parts['before'] }}<mark class="rounded bg-amber-200 px-0.5 text-gray-900">{{ $parts['match'] }}</mark>{{ $parts['after'] }}"</p>
                                        </div>
                                        <div class="flex shrink-0 gap-2" role="radiogroup" aria-label="{{ $symptomsByCode[$suggestion->code]->name }}">
                                            <label>
                                                <input type="radio" name="decisions[{{ $suggestion->code }}]" value="confirm" class="peer sr-only"
                                                       @checked($decisions[$suggestion->code] === 'confirm')>
                                                <span class="inline-flex cursor-pointer items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 peer-checked:border-green-600 peer-checked:bg-green-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500">
                                                    ✓ {{ __('Confirm') }}
                                                </span>
                                            </label>
                                            <label>
                                                <input type="radio" name="decisions[{{ $suggestion->code }}]" value="remove" class="peer sr-only"
                                                       @checked($decisions[$suggestion->code] === 'remove')>
                                                <span class="inline-flex cursor-pointer items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 peer-checked:border-gray-700 peer-checked:bg-gray-700 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500">
                                                    ✗ {{ __('Remove') }}
                                                </span>
                                            </label>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </fieldset>
                    @endif

                    <div>
                        <x-input-label for="diagnosis" :value="__('Diagnosis')" />
                        <x-text-input id="diagnosis" name="diagnosis" type="text" class="mt-1 block w-full" :value="old('diagnosis', $visit->diagnosis)" />
                        <x-input-error :messages="$errors->get('diagnosis')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="added_symptom" :value="__('Add a symptom the scan missed (optional)')" />

                        @if ($addedSymptomIds !== [])
                            <div class="mt-2 flex flex-wrap gap-3">
                                @foreach ($symptomsByCode->whereIn('id', $addedSymptomIds) as $symptom)
                                    <label class="flex items-center gap-2 text-sm text-gray-900">
                                        <input type="checkbox" name="added_symptoms[]" value="{{ $symptom->id }}" checked
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        {{ $symptom->name }} <span class="text-xs text-gray-500">({{ __('added by hand') }})</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif

                        <select id="added_symptom" name="added_symptoms[]"
                                class="mt-2 block w-full sm:w-80 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                            <option value="">{{ __('— None —') }}</option>
                            @foreach ($symptomsByCode as $symptom)
                                <option value="{{ $symptom->id }}">{{ $symptom->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('added_symptoms.*')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Save consultation') }}</x-primary-button>
                        <a href="{{ route('patients.show', $patient->id) }}" class="text-sm text-gray-600 underline hover:text-gray-900">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
