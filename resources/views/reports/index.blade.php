<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('PMOS screening statistics') }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ $report->period->description() }}</p>
            </div>
            <x-secondary-button onclick="window.print()" class="print:hidden">{{ __('Print') }}</x-secondary-button>
        </div>
    </x-slot>

    @php($summary = $report->summary)

    <div class="py-12 print:py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6 print:max-w-none print:px-0 print:space-y-4">
            <p class="hidden text-xs text-gray-600 print:block">
                {{ __('Printed :date by :name. Figures come from the screening records and :emr.', ['date' => now()->format('j M Y, H:i'), 'name' => auth()->user()->staffName(), 'emr' => app(App\Emr\EmrAdapter::class)->systemName()]) }}
            </p>

            {{-- Period --}}
            <form method="GET" action="{{ route('reports.index') }}"
                  x-data="{ period: @js(old('period', $report->period->choice)) }"
                  class="bg-white shadow-sm sm:rounded-lg p-6 print:hidden">
                <div class="flex flex-wrap items-end gap-4">
                    <div>
                        <x-input-label for="period" :value="__('Period')" />
                        <select id="period" name="period" x-model="period"
                                class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($choices as $value => $label)
                                <option value="{{ $value }}" @selected($report->period->choice === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="period === '{{ App\Reports\ReportPeriod::CUSTOM }}'" @if ($report->period->choice !== App\Reports\ReportPeriod::CUSTOM) x-cloak @endif class="flex flex-wrap gap-4">
                        <div>
                            <x-input-label for="from" :value="__('From')" />
                            <x-text-input id="from" name="from" type="date" class="mt-1 block" :max="today()->toDateString()"
                                          :value="old('from', $report->period->from->toDateString())" />
                        </div>
                        <div>
                            <x-input-label for="to" :value="__('To')" />
                            <x-text-input id="to" name="to" type="date" class="mt-1 block" :max="today()->toDateString()"
                                          :value="old('to', $report->period->to->toDateString())" />
                        </div>
                    </div>

                    <x-primary-button>{{ __('Show') }}</x-primary-button>
                </div>

                <x-input-error :messages="array_merge($errors->get('period'), $errors->get('from'), $errors->get('to'))" class="mt-2" />

                <p class="mt-3 text-sm text-gray-600">
                    {{ __('Each event is counted by its own date: alerts by the day they were raised, decisions by the day they were made, and results by the result date.') }}
                </p>
            </form>

            {{-- 1. Summary --}}
            <section class="bg-white shadow-sm sm:rounded-lg p-6 break-inside-avoid print:shadow-none print:border print:border-gray-300 print:p-4" aria-labelledby="summary-heading">
                <h3 id="summary-heading" class="text-lg font-medium text-gray-900">{{ __('Summary') }}</h3>
                <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6 print:grid-cols-6">
                    @foreach ([
                        ['label' => __('Alerts raised'), 'value' => $summary['raised']],
                        ['label' => __('Referred'), 'value' => $summary['referred']],
                        ['label' => __('Not referred'), 'value' => $summary['not_referred']],
                        ['label' => __('Diagnosed with PMOS'), 'value' => $summary['confirmed']],
                        ['label' => __('PMOS ruled out'), 'value' => $summary['ruled_out']],
                        ['label' => __('Still awaiting results'), 'value' => $summary['awaiting']],
                    ] as $item)
                        <div class="rounded-md border border-gray-200 p-4">
                            <dt class="text-sm text-gray-600">{{ $item['label'] }}</dt>
                            <dd class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ $item['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="mt-3 text-sm text-gray-600">{{ __('"Still awaiting results" counts patients referred in this period whose result has not been recorded yet.') }}</p>
            </section>

            <div class="grid gap-6 lg:grid-cols-2 print:grid-cols-2 print:gap-4">
                {{-- 2. Age groups --}}
                <section class="bg-white shadow-sm sm:rounded-lg p-6 break-inside-avoid print:shadow-none print:border print:border-gray-300 print:p-4" aria-labelledby="ages-heading">
                    <h3 id="ages-heading" class="text-lg font-medium text-gray-900">{{ __('Patients diagnosed with PMOS by age') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Age on the date PMOS was confirmed. :count patients diagnosed.', ['count' => $report->diagnosedPatients]) }}</p>

                    <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="py-2 pe-4">{{ __('Age group') }}</th>
                                <th class="py-2 text-right">{{ __('Patients') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-gray-900">
                            @foreach ($report->ageGroups as $group)
                                <tr>
                                    <td class="py-2 pe-4">{{ $group['label'] }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $group['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4">
                        @include('reports.partials.bar-rows', ['rows' => $report->ageGroups, 'label' => __('Bar chart of patients diagnosed with PMOS by age group')])
                    </div>
                </section>

                {{-- 4. Referral results --}}
                <section class="bg-white shadow-sm sm:rounded-lg p-6 break-inside-avoid print:shadow-none print:border print:border-gray-300 print:p-4" aria-labelledby="results-heading">
                    <h3 id="results-heading" class="text-lg font-medium text-gray-900">{{ __('How often referrals were confirmed') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Results recorded in this period.') }}</p>

                    @if ($report->confirmedPercent === null)
                        <p class="mt-4 text-sm text-gray-600">{{ __('No referral results were recorded in this period.') }}</p>
                    @else
                        <p class="mt-4 text-3xl font-semibold tabular-nums text-gray-900">{{ $report->confirmedPercent }}%</p>
                        <p class="text-sm text-gray-600">{{ __(':confirmed of :total referral results confirmed PMOS.', ['confirmed' => $summary['confirmed'], 'total' => $summary['confirmed'] + $summary['ruled_out']]) }}</p>

                        <div class="mt-4 flex h-6 overflow-hidden rounded bg-gray-100 print-exact" role="img"
                             aria-label="{{ __(':confirmed confirmed, :ruled ruled out', ['confirmed' => $summary['confirmed'], 'ruled' => $summary['ruled_out']]) }}">
                            <div class="bg-indigo-500 print-exact" style="width: {{ $report->confirmedPercent }}%"></div>
                            <div class="bg-gray-300 print-exact" style="width: {{ 100 - $report->confirmedPercent }}%"></div>
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="inline-block h-3 w-3 rounded-sm bg-indigo-500 print-exact" aria-hidden="true"></span>
                                <dt class="text-gray-700">{{ __('Confirmed') }}</dt>
                                <dd class="font-medium tabular-nums">{{ $summary['confirmed'] }}</dd>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-block h-3 w-3 rounded-sm bg-gray-300 print-exact" aria-hidden="true"></span>
                                <dt class="text-gray-700">{{ __('Ruled out') }}</dt>
                                <dd class="font-medium tabular-nums">{{ $summary['ruled_out'] }}</dd>
                            </div>
                        </dl>
                    @endif
                </section>
            </div>

            {{-- 3. Diagnoses per month --}}
            <section class="bg-white shadow-sm sm:rounded-lg p-6 break-inside-avoid print:shadow-none print:border print:border-gray-300 print:p-4" aria-labelledby="months-heading">
                <h3 id="months-heading" class="text-lg font-medium text-gray-900">{{ __('Diagnoses per month') }}</h3>
                <div class="mt-4 overflow-x-auto">
                    <div class="flex h-48 min-w-max items-end gap-2" role="img" aria-label="{{ __('Bar chart of PMOS diagnoses per month') }}">
                        @foreach ($report->diagnosesByMonth as $month)
                            <div class="flex h-full w-12 flex-col items-center justify-end">
                                <span class="text-xs font-medium tabular-nums text-gray-900">{{ $month['count'] }}</span>
                                <div class="mt-1 w-8 rounded-t bg-indigo-500 print-exact" style="height: {{ round($month['count'] / $report->busiestMonth() * 75) }}%"></div>
                                <span class="mt-1 whitespace-nowrap text-xs text-gray-600">{{ $month['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-2 print:grid-cols-2 print:gap-4">
                {{-- 5. Rotterdam signs --}}
                <section class="bg-white shadow-sm sm:rounded-lg p-6 break-inside-avoid print:shadow-none print:border print:border-gray-300 print:p-4" aria-labelledby="signs-heading">
                    <h3 id="signs-heading" class="text-lg font-medium text-gray-900">{{ __('Rotterdam signs among diagnosed patients') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('The signs found when the alert was raised.') }}</p>

                    <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="py-2 pe-4">{{ __('Sign') }}</th>
                                <th class="py-2 pe-4 text-right">{{ __('Patients') }}</th>
                                <th class="py-2 text-right">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-gray-900">
                            @foreach ($report->signs as $sign)
                                <tr>
                                    <td class="py-2 pe-4">{{ $sign['label'] }}</td>
                                    <td class="py-2 pe-4 text-right tabular-nums">{{ $sign['count'] }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $sign['percent'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <h4 class="mt-6 text-sm font-medium text-gray-900">{{ __('Combinations of signs') }}</h4>
                    <table class="mt-2 min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="py-2 pe-4">{{ __('Signs') }}</th>
                                <th class="py-2 pe-4 text-right">{{ __('Patients') }}</th>
                                <th class="py-2 text-right">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-gray-900">
                            @foreach ($report->signCombinations as $combination)
                                <tr>
                                    <td class="py-2 pe-4">{{ $combination['label'] }}</td>
                                    <td class="py-2 pe-4 text-right tabular-nums">{{ $combination['count'] }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $combination['percent'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>

                {{-- 6. BMI --}}
                <section class="bg-white shadow-sm sm:rounded-lg p-6 break-inside-avoid print:shadow-none print:border print:border-gray-300 print:p-4" aria-labelledby="bmi-heading">
                    <h3 id="bmi-heading" class="text-lg font-medium text-gray-900">{{ __('BMI of diagnosed patients') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Using each patient\'s latest BMI.') }}</p>

                    <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="py-2 pe-4">{{ __('BMI group') }}</th>
                                <th class="py-2 pe-4 text-right">{{ __('Patients') }}</th>
                                <th class="py-2 text-right">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-gray-900">
                            @foreach ($report->bmiGroups as $group)
                                <tr>
                                    <td class="py-2 pe-4">{{ $group['label'] }}</td>
                                    <td class="py-2 pe-4 text-right tabular-nums">{{ $group['count'] }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $group['percent'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4">
                        @include('reports.partials.bar-rows', ['rows' => $report->bmiGroups, 'label' => __('Bar chart of diagnosed patients by BMI group')])
                    </div>
                </section>
            </div>

            {{-- 7. Overdue referrals --}}
            <section class="bg-white shadow-sm sm:rounded-lg p-6 break-inside-avoid print:shadow-none print:border print:border-gray-300 print:p-4" aria-labelledby="overdue-heading">
                <h3 id="overdue-heading" class="text-lg font-medium text-gray-900">
                    {{ __('Referrals with no result after :weeks weeks', ['weeks' => $report->overdueAfterWeeks]) }}
                    @if ($report->overdueReferrals !== [])
                        <span class="ms-1 rounded-full bg-amber-100 px-2 py-0.5 text-sm font-medium text-amber-800 print-exact">{{ count($report->overdueReferrals) }}</span>
                    @endif
                </h3>
                <p class="mt-1 text-sm text-gray-600">{{ __('All referrals still waiting today, whatever period is chosen above.') }}</p>

                @if ($report->overdueReferrals === [])
                    <p class="mt-4 text-sm text-gray-600">{{ __('No referrals have been waiting more than :weeks weeks.', ['weeks' => $report->overdueAfterWeeks]) }}</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                <tr>
                                    <th class="py-2 pe-4">{{ __('Patient') }}</th>
                                    <th class="py-2 pe-4">{{ __('Patient number') }}</th>
                                    <th class="py-2 pe-4">{{ __('Referred') }}</th>
                                    <th class="py-2 text-right">{{ __('Weeks waiting') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-gray-900">
                                @foreach ($report->overdueReferrals as $row)
                                    <tr>
                                        <td class="py-2 pe-4">
                                            <a href="{{ route('patients.show', $row['alert']->emr_patient_id) }}" class="font-medium text-indigo-600 hover:underline print:text-gray-900 print:no-underline">
                                                {{ $row['patient']?->fullName() ?? __('Patient :id', ['id' => $row['alert']->emr_patient_id]) }}
                                            </a>
                                        </td>
                                        <td class="py-2 pe-4 font-mono text-xs">{{ $row['patient']?->patientNumber }}</td>
                                        <td class="py-2 pe-4 whitespace-nowrap">{{ $row['alert']->decided_at->format('j M Y') }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ $row['weeks'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
