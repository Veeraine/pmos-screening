{{--
    An open alert: the signs found and which visits they came from, so the doctor can check it,
    plus the refer / don't refer form.
--}}
@php
    $previousAlert = $alerts->find($alert->previous_alert_id);
@endphp

<section class="rounded-md border-2 border-red-400 bg-red-50 p-4 sm:p-6" aria-labelledby="alert-heading">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h3 id="alert-heading" class="text-lg font-semibold text-red-900">
            {{ __('Screening alert: refer for PMOS screening?') }}
        </h3>
        <p class="text-sm text-red-900">{{ __('Raised :date', ['date' => $alert->created_at->format('d M Y, H:i')]) }}</p>
    </div>

    <p class="mt-1 text-sm text-red-900">
        {{ __(':count of the 3 Rotterdam signs have been recorded:', ['count' => $alert->featureCount()]) }}
    </p>

    @if ($previousAlert?->outcome === App\ScreeningOutcome::RuledOut)
        <p class="mt-3 rounded-md bg-white p-3 text-sm text-gray-800">
            <span class="font-medium">{{ __('Earlier result:') }}</span>
            {{ __('PMOS was ruled out on :date. Symptoms marked "new" were confirmed after that date.', ['date' => $previousAlert->outcome_date->format('d M Y')]) }}
        </p>
    @elseif ($previousAlert && $previousAlert->referred === false)
        <p class="mt-3 rounded-md bg-white p-3 text-sm text-gray-800">
            <span class="font-medium">{{ __('Earlier alert:') }}</span>
            {{ __('Not referred on :date (":reason"). Symptoms marked "new" were confirmed after that date.', [
                'date' => $previousAlert->decided_at->format('d M Y'),
                'reason' => $previousAlert->not_referred_reason,
            ]) }}
        </p>
    @endif

    <ul class="mt-4 grid gap-3 md:grid-cols-3">
        @foreach ($alert->findings as $feature)
            <li class="rounded-md bg-white p-3 text-sm shadow-sm">
                <p class="font-medium text-gray-900">{{ $feature['label'] }}</p>
                <ul class="mt-2 space-y-2 text-gray-700">
                    @foreach ($feature['evidence'] as $item)
                        <li>
                            {{ $item['symptom'] }}
                            @if ($item['is_new'])
                                <span class="ms-1 rounded-full bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-800">{{ __('new') }}</span>
                            @endif
                            <span class="block text-xs text-gray-500">
                                {{ __('Visit on :date', ['date' => \Illuminate\Support\Carbon::parse($item['visit_date'])->format('d M Y')]) }}
                                @if ($item['source'] === 'manual') · {{ __('added by hand') }} @endif
                            </span>
                            @if ($item['matched_text'])
                                <span class="block text-xs italic text-gray-500">"{{ $item['matched_text'] }}"</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </li>
        @endforeach
    </ul>

    <form method="POST" action="{{ route('alert-decisions.store', $alert) }}" class="mt-6 space-y-4" x-data="{ referred: @js(old('referred')) }">
        @csrf
        <fieldset>
            <legend class="text-sm font-medium text-gray-900">{{ __('Your decision') }}</legend>
            <div class="mt-2 flex flex-wrap gap-3">
                <label>
                    <input type="radio" name="referred" value="1" class="peer sr-only" x-model="referred" @checked(old('referred') === '1')>
                    <span class="inline-flex cursor-pointer items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 peer-checked:border-green-700 peer-checked:bg-green-700 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500">
                        {{ __('Refer for screening') }}
                    </span>
                </label>
                <label>
                    <input type="radio" name="referred" value="0" class="peer sr-only" x-model="referred" @checked(old('referred') === '0')>
                    <span class="inline-flex cursor-pointer items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 peer-checked:border-gray-700 peer-checked:bg-gray-700 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500">
                        {{ __("Don't refer") }}
                    </span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('referred')" class="mt-2" />
            <x-input-error :messages="$errors->get('decision')" class="mt-2" />
        </fieldset>

        <div x-show="referred === '0'" @if (old('referred') !== '0') x-cloak @endif>
            <x-input-label for="not_referred_reason" :value="__('Reason for not referring (required)')" />
            <textarea id="not_referred_reason" name="not_referred_reason" rows="2"
                      class="mt-1 block w-full max-w-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('not_referred_reason') }}</textarea>
            <x-input-error :messages="$errors->get('not_referred_reason')" class="mt-2" />
        </div>

        <x-primary-button>{{ __('Save decision') }}</x-primary-button>
    </form>
</section>
