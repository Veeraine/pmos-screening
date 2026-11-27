<?php

namespace App\Reports;

use App\Emr\EmrAdapter;
use App\Emr\EmrPatient;
use App\Emr\EmrVitalSigns;
use App\Models\Alert;
use App\ScreeningOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Statistics report for a period. Each event counts on its own date, so an alert raised
 */
final readonly class ScreeningReport
{
    /**
     * @param  array{raised: int, referred: int, not_referred: int, confirmed: int, ruled_out: int, awaiting: int}  $summary
     * @param  list<array{label: string, count: int}>  $ageGroups
     * @param  list<array{label: string, count: int}>  $diagnosesByMonth
     * @param  list<array{label: string, count: int, percent: int}>  $signs
     * @param  list<array{label: string, count: int, percent: int}>  $signCombinations
     * @param  list<array{label: string, count: int, percent: int}>  $bmiGroups
     * @param  list<array{alert: Alert, patient: ?EmrPatient, weeks: int}>  $overdueReferrals
     */
    public function __construct(
        public ReportPeriod $period,
        public array $summary,
        public int $diagnosedPatients,
        public array $ageGroups,
        public array $diagnosesByMonth,
        public ?int $confirmedPercent,
        public array $signs,
        public array $signCombinations,
        public array $bmiGroups,
        public array $overdueReferrals,
        public int $overdueAfterWeeks,
    ) {}

    public static function for(ReportPeriod $period, EmrAdapter $emr): self
    {
        $summary = self::summary($period);
        $diagnoses = self::diagnoses($period);
        $overdueAfterWeeks = (int) config('screening.report.overdue_results_after_weeks');
        $overdue = Alert::awaitingResults()
            ->where('decided_at', '<', CarbonImmutable::today()->subWeeks($overdueAfterWeeks)->toDateTimeString())
            ->oldest('decided_at')
            ->oldest('id')
            ->get(['id', 'emr_patient_id', 'decided_at']);

        $diagnosedIds = $diagnoses->pluck('emr_patient_id')->all();
        $patients = $emr->findPatients(array_values(array_unique([...$diagnosedIds, ...$overdue->pluck('emr_patient_id')->all()])));
        $vitalSigns = $emr->latestVitalSigns($diagnosedIds);

        $decided = $summary['confirmed'] + $summary['ruled_out'];

        return new self(
            period: $period,
            summary: $summary,
            diagnosedPatients: $diagnoses->count(),
            ageGroups: self::ageGroups($diagnoses, $patients),
            diagnosesByMonth: self::diagnosesByMonth($period, $diagnoses),
            confirmedPercent: $decided === 0 ? null : (int) round($summary['confirmed'] / $decided * 100),
            signs: self::signs($diagnoses),
            signCombinations: self::signCombinations($diagnoses),
            bmiGroups: self::bmiGroups($diagnoses, $vitalSigns),
            overdueReferrals: $overdue->map(fn (Alert $alert): array => [
                'alert' => $alert,
                'patient' => $patients->get($alert->emr_patient_id),
                'weeks' => (int) $alert->decided_at->copy()->startOfDay()->diffInWeeks(CarbonImmutable::today()),
            ])->all(),
            overdueAfterWeeks: $overdueAfterWeeks,
        );
    }

    // The most patients in any age group, for scaling the bar chart.

    public function largestAgeGroup(): int
    {
        return max([1, ...array_column($this->ageGroups, 'count')]);
    }

    // The most diagnoses in any month, for scaling the bar chart.
    public function busiestMonth(): int
    {
        return max([1, ...array_column($this->diagnosesByMonth, 'count')]);
    }

    /**
     * The summary numbers, counted in one query.
     *
     * @return array{raised: int, referred: int, not_referred: int, confirmed: int, ruled_out: int, awaiting: int}
     */
    private static function summary(ReportPeriod $period): array
    {
        $startsAt = $period->startsAt()->toDateTimeString();
        $endsBefore = $period->endsBefore()->toDateTimeString();
        $firstDay = $period->startsAt()->toDateString();
        $dayAfter = $period->endsBefore()->toDateString();

        $raised = '(created_at >= ? and created_at < ?)';
        $decided = '(decided_at >= ? and decided_at < ?)';
        $resulted = '(outcome_date >= ? and outcome_date < ?)';

        $row = Alert::query()
            ->toBase()
            ->selectRaw(
                "sum(case when {$raised} then 1 else 0 end) as raised,"
                ." sum(case when referred = true and {$decided} then 1 else 0 end) as referred,"
                ." sum(case when referred = false and {$decided} then 1 else 0 end) as not_referred,"
                ." sum(case when outcome = ? and {$resulted} then 1 else 0 end) as confirmed,"
                ." sum(case when outcome = ? and {$resulted} then 1 else 0 end) as ruled_out,"
                ." sum(case when referred = true and outcome is null and {$decided} then 1 else 0 end) as awaiting",
                [
                    $startsAt, $endsBefore,
                    $startsAt, $endsBefore,
                    $startsAt, $endsBefore,
                    ScreeningOutcome::Confirmed->value, $firstDay, $dayAfter,
                    ScreeningOutcome::RuledOut->value, $firstDay, $dayAfter,
                    $startsAt, $endsBefore,
                ],
            )
            ->where(fn ($query) => $query
                ->whereRaw($raised, [$startsAt, $endsBefore])
                ->orWhereRaw($decided, [$startsAt, $endsBefore])
                ->orWhereRaw($resulted, [$firstDay, $dayAfter]))
            ->first();

        return [
            'raised' => (int) $row->raised,
            'referred' => (int) $row->referred,
            'not_referred' => (int) $row->not_referred,
            'confirmed' => (int) $row->confirmed,
            'ruled_out' => (int) $row->ruled_out,
            'awaiting' => (int) $row->awaiting,
        ];
    }

    /**
     * The alerts where PMOS was confirmed in the period, one per patient.
     *
     * @return Collection<int, Alert>
     */
    private static function diagnoses(ReportPeriod $period): Collection
    {
        return Alert::query()
            ->where('outcome', ScreeningOutcome::Confirmed)
            ->where('outcome_date', '>=', $period->startsAt()->toDateString())
            ->where('outcome_date', '<', $period->endsBefore()->toDateString())
            ->orderBy('outcome_date')
            ->orderBy('id')
            ->get(['id', 'emr_patient_id', 'outcome_date', 'findings'])
            ->unique('emr_patient_id')
            ->values();
    }

    /**
     * Diagnosed patients by their age on the day PMOS was confirmed.
     *
     * @param  Collection<int, Alert>  $diagnoses
     * @param  Collection<string, EmrPatient>  $patients
     * @return list<array{label: string, count: int}>
     */
    private static function ageGroups(Collection $diagnoses, Collection $patients): array
    {
        $groups = array_map(fn (array $group): array => [...$group, 'count' => 0], config('screening.report.age_groups'));
        $unknown = 0;

        foreach ($diagnoses as $alert) {
            $patient = $patients->get($alert->emr_patient_id);
            $index = $patient ? self::groupIndex($groups, $patient->ageOn($alert->outcome_date), inclusiveEnd: true) : null;

            $index === null ? $unknown++ : $groups[$index]['count']++;
        }

        $rows = array_map(fn (array $group): array => ['label' => $group['label'], 'count' => $group['count']], $groups);

        if ($unknown > 0) {
            $rows[] = ['label' => __('Age not known'), 'count' => $unknown];
        }

        return $rows;
    }

    /**
     * Diagnoses in each month of the period.
     *
     * @param  Collection<int, Alert>  $diagnoses
     * @return list<array{label: string, count: int}>
     */
    private static function diagnosesByMonth(ReportPeriod $period, Collection $diagnoses): array
    {
        $perMonth = $diagnoses->countBy(fn (Alert $alert): string => $alert->outcome_date->format('Y-m'));

        return array_map(fn (CarbonImmutable $month): array => [
            'label' => $month->format('M Y'),
            'count' => $perMonth->get($month->format('Y-m'), 0),
        ], $period->months());
    }

    /**
     * How many diagnosed patients had each Rotterdam sign when their alert was raised.
     *
     * @param  Collection<int, Alert>  $diagnoses
     * @return list<array{label: string, count: int, percent: int}>
     */
    private static function signs(Collection $diagnoses): array
    {
        $rows = [];

        foreach (config('screening.rotterdam_features') as $key => $feature) {
            $count = $diagnoses->filter(fn (Alert $alert): bool => in_array($key, self::signKeys($alert), true))->count();
            $rows[] = ['label' => $feature['label'], 'count' => $count, 'percent' => self::percent($count, $diagnoses->count())];
        }

        return $rows;
    }

    /**
     * Diagnosed patients per combination of signs, shows every combination, even with 0.
     *
     * @param  Collection<int, Alert>  $diagnoses
     * @return list<array{label: string, count: int, percent: int}>
     */
    private static function signCombinations(Collection $diagnoses): array
    {
        $features = config('screening.rotterdam_features');
        $counts = [];

        foreach (self::combinations(array_keys($features), max(1, (int) config('screening.alert_threshold'))) as $keys) {
            $counts[implode('+', $keys)] = 0;
        }

        foreach ($diagnoses as $alert) {
            $combination = implode('+', self::signKeys($alert));
            $counts[$combination] = ($counts[$combination] ?? 0) + 1;
        }

        $rows = [];

        foreach ($counts as $combination => $count) {
            $labels = array_map(fn (string $key): string => $features[$key]['label'], array_filter(explode('+', $combination)));
            $rows[] = [
                'label' => $labels === [] ? __('No signs recorded') : implode(' + ', $labels),
                'count' => $count,
                'percent' => self::percent($count, $diagnoses->count()),
            ];
        }

        return $rows;
    }

    /**
     * Diagnosed patients by their latest BMI in the EMR.
     *
     * @param  Collection<int, Alert>  $diagnoses
     * @param  Collection<string, EmrVitalSigns>  $vitalSigns
     * @return list<array{label: string, count: int, percent: int}>
     */
    private static function bmiGroups(Collection $diagnoses, Collection $vitalSigns): array
    {
        $groups = array_map(fn (array $group): array => [...$group, 'count' => 0], config('screening.report.bmi_groups'));
        $unknown = 0;

        foreach ($diagnoses as $alert) {
            $bmi = $vitalSigns->get($alert->emr_patient_id)?->bmi;
            $index = $bmi === null ? null : self::groupIndex($groups, (float) $bmi, inclusiveEnd: false);

            $index === null ? $unknown++ : $groups[$index]['count']++;
        }

        $rows = array_map(fn (array $group): array => [
            'label' => $group['label'],
            'count' => $group['count'],
            'percent' => self::percent($group['count'], $diagnoses->count()),
        ], $groups);

        if ($unknown > 0) {
            $rows[] = ['label' => __('No BMI recorded'), 'count' => $unknown, 'percent' => self::percent($unknown, $diagnoses->count())];
        }

        return $rows;
    }

    /**
     * Which group a value is in. Age groups use "from"/"to", BMI groups use "from"/"below".
     *
     * @param  list<array<string, mixed>>  $groups
     */
    private static function groupIndex(array $groups, float|int $value, bool $inclusiveEnd): ?int
    {
        foreach ($groups as $index => $group) {
            $aboveStart = ! isset($group['from']) || $value >= $group['from'];
            $belowEnd = $inclusiveEnd
                ? ! isset($group['to']) || $value <= $group['to']
                : ! isset($group['below']) || $value < $group['below'];

            if ($aboveStart && $belowEnd) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The Rotterdam signs an alert found, in the order of config/screening.php.
     *
     * @return list<string>
     */
    private static function signKeys(Alert $alert): array
    {
        $found = array_column($alert->findings, 'key');

        return array_values(array_filter(array_keys(config('screening.rotterdam_features')), fn (string $key): bool => in_array($key, $found, true)));
    }

    /**
     * All combinations of at least $minimum keys, smallest first.
     *
     * @param  list<string>  $keys
     * @return list<list<string>>
     */
    private static function combinations(array $keys, int $minimum): array
    {
        $combinations = [[]];

        foreach ($keys as $key) {
            foreach ($combinations as $combination) {
                $combinations[] = [...$combination, $key];
            }
        }

        $combinations = array_values(array_filter($combinations, fn (array $combination): bool => count($combination) >= $minimum));
        usort($combinations, fn (array $a, array $b): int => count($a) <=> count($b));

        return $combinations;
    }

    private static function percent(int $count, int $total): int
    {
        return $total === 0 ? 0 : (int) round($count / $total * 100);
    }
}
