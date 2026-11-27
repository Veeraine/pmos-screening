{{--
    Forms to correct the latest alert's decision or result. Needs a reason, and the original stays in the history.
--}}
<x-input-error :messages="$errors->get('correction')" class="mt-2" />

@if ($alert->outcome === null)
    <details class="mt-2" @if ($errors->hasAny(['referred', 'not_referred_reason', 'correction_reason']) && old('correcting') === 'decision') open @endif>
        <summary class="cursor-pointer text-sm font-medium text-indigo-600 hover:underline">{{ __('Correct decision') }}</summary>

        <form method="POST" action="{{ route('alert-decisions.update', $alert) }}" class="mt-3 max-w-xl space-y-3 rounded-md border border-gray-200 p-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="correcting" value="decision">

            <fieldset>
                <legend class="text-sm font-medium text-gray-700">{{ __('Correct decision') }}</legend>
                <div class="mt-1 flex gap-4 text-sm text-gray-900">
                    <label class="flex items-center gap-2">
                        <input type="radio" name="referred" value="1" @checked(old('referred', $alert->referred ? '1' : '0') === '1')
                               class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        {{ __('Referred') }}
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="referred" value="0" @checked(old('referred', $alert->referred ? '1' : '0') === '0')
                               class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        {{ __('Not referred') }}
                    </label>
                </div>
                <x-input-error :messages="$errors->get('referred')" class="mt-2" />
            </fieldset>

            <div>
                <x-input-label for="correct_not_referred_reason" :value="__('Reason for not referring (if not referred)')" />
                <textarea id="correct_not_referred_reason" name="not_referred_reason" rows="2"
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('not_referred_reason', $alert->not_referred_reason) }}</textarea>
                <x-input-error :messages="$errors->get('not_referred_reason')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="decision_correction_reason" :value="__('Why are you correcting this? (required)')" />
                <textarea id="decision_correction_reason" name="correction_reason" rows="2" required
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('correcting') === 'decision' ? old('correction_reason') : '' }}</textarea>
                <x-input-error :messages="old('correcting') === 'decision' ? $errors->get('correction_reason') : []" class="mt-2" />
            </div>

            <x-primary-button>{{ __('Save correction') }}</x-primary-button>
        </form>
    </details>
@else
    <details class="mt-2" @if ($errors->hasAny(['outcome', 'outcome_date', 'correction_reason']) && old('correcting') === 'outcome') open @endif>
        <summary class="cursor-pointer text-sm font-medium text-indigo-600 hover:underline">{{ __('Correct result') }}</summary>

        <form method="POST" action="{{ route('alert-outcomes.update', $alert) }}" class="mt-3 max-w-xl space-y-3 rounded-md border border-gray-200 p-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="correcting" value="outcome">

            <fieldset>
                <legend class="text-sm font-medium text-gray-700">{{ __('Correct result') }}</legend>
                <div class="mt-1 flex gap-4 text-sm text-gray-900">
                    @foreach (App\ScreeningOutcome::cases() as $outcome)
                        <label class="flex items-center gap-2">
                            <input type="radio" name="outcome" value="{{ $outcome->value }}" @checked(old('outcome', $alert->outcome->value) === $outcome->value)
                                   class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            {{ $outcome->label() }}
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('outcome')" class="mt-2" />
            </fieldset>

            <div>
                <x-input-label for="correct_outcome_date" :value="__('Date of result')" />
                <x-text-input id="correct_outcome_date" name="outcome_date" type="date" class="mt-1 block"
                              :value="old('outcome_date', $alert->outcome_date->toDateString())"
                              :min="$alert->decided_at->toDateString()" :max="now()->toDateString()" />
                <x-input-error :messages="$errors->get('outcome_date')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="outcome_correction_reason" :value="__('Why are you correcting this? (required)')" />
                <textarea id="outcome_correction_reason" name="correction_reason" rows="2" required
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('correcting') === 'outcome' ? old('correction_reason') : '' }}</textarea>
                <x-input-error :messages="old('correcting') === 'outcome' ? $errors->get('correction_reason') : []" class="mt-2" />
            </div>

            <x-primary-button>{{ __('Save correction') }}</x-primary-button>
        </form>
    </details>
@endif
