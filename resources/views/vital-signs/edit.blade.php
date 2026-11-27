<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Vital signs') }} —
            <a href="{{ route('patients.show', $visit->patient) }}" class="hover:underline">{{ $visit->patient->full_name }}</a>
            <span class="ms-2 font-mono text-base font-normal text-gray-500">{{ $visit->patient->patient_number }}</span>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <p class="text-sm text-gray-600">
                    {{ __('Visit on :date for ":reason".', ['date' => $visit->visit_date->format('d M Y'), 'reason' => $visit->reason]) }}
                </p>

                <form method="POST" action="{{ route('vital-signs.update', $visit) }}" class="mt-6 max-w-xl space-y-6">
                    @csrf
                    @method('PUT')

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <x-input-label for="weight_kg" :value="__('Weight (kg)')" />
                            <x-text-input id="weight_kg" name="weight_kg" type="number" step="0.1" inputmode="decimal" class="mt-1 block w-full"
                                          :value="old('weight_kg', $vitalSigns?->weight_kg)" required autofocus />
                            <x-input-error :messages="$errors->get('weight_kg')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="height_cm" :value="$lastRecordedHeight ? __('Height (cm, optional)') : __('Height (cm)')" />
                            <x-text-input id="height_cm" name="height_cm" type="number" step="0.1" inputmode="decimal" class="mt-1 block w-full"
                                          :value="old('height_cm', $vitalSigns?->height_cm)" :required="! $lastRecordedHeight" />
                            @if ($lastRecordedHeight)
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ __('Last recorded: :height cm on :date. Leave blank to use it.', [
                                        'height' => $lastRecordedHeight->height_cm,
                                        'date' => $lastRecordedHeight->visit->visit_date->format('d M Y'),
                                    ]) }}
                                </p>
                            @else
                                <p class="mt-1 text-xs text-gray-500">{{ __("Required at the patient's first visit.") }}</p>
                            @endif
                            <x-input-error :messages="$errors->get('height_cm')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="systolic_bp" :value="__('Blood pressure (mmHg)')" />
                        <div class="mt-1 flex items-center gap-2">
                            <x-text-input id="systolic_bp" name="systolic_bp" type="number" step="1" inputmode="numeric" class="block w-full"
                                          :value="old('systolic_bp', $vitalSigns?->systolic_bp)" :placeholder="__('Top')" required
                                          :aria-label="__('Systolic blood pressure')" />
                            <span class="text-gray-500">/</span>
                            <x-text-input id="diastolic_bp" name="diastolic_bp" type="number" step="1" inputmode="numeric" class="block w-full"
                                          :value="old('diastolic_bp', $vitalSigns?->diastolic_bp)" :placeholder="__('Bottom')" required
                                          :aria-label="__('Diastolic blood pressure')" />
                        </div>
                        <x-input-error :messages="$errors->get('systolic_bp')" class="mt-2" />
                        <x-input-error :messages="$errors->get('diastolic_bp')" class="mt-2" />
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <x-input-label for="temperature_c" :value="__('Temperature (°C)')" />
                            <x-text-input id="temperature_c" name="temperature_c" type="number" step="0.1" inputmode="decimal" class="mt-1 block w-full"
                                          :value="old('temperature_c', $vitalSigns?->temperature_c)" required />
                            <x-input-error :messages="$errors->get('temperature_c')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="pulse_rate" :value="__('Pulse rate (beats per minute)')" />
                            <x-text-input id="pulse_rate" name="pulse_rate" type="number" step="1" inputmode="numeric" class="mt-1 block w-full"
                                          :value="old('pulse_rate', $vitalSigns?->pulse_rate)" required />
                            <x-input-error :messages="$errors->get('pulse_rate')" class="mt-2" />
                        </div>
                    </div>

                    <p class="text-sm text-gray-600">{{ __('BMI is calculated automatically from the weight and the height (or the last recorded height).') }}</p>

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Save vital signs') }}</x-primary-button>
                        <a href="{{ route('patients.show', $visit->patient) }}" class="text-sm text-gray-600 underline hover:text-gray-900">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
