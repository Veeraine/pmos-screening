<?php

namespace App\Reports;

use Carbon\CarbonImmutable;

/**
 * The dates a report covers
 */
final readonly class ReportPeriod
{
    public const PAST_12_MONTHS = '12m';

    public const PAST_6_MONTHS = '6m';

    public const THIS_YEAR = 'year';

    public const CUSTOM = 'custom';

    public function __construct(
        public string $choice,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    /**
     * The periods the doctor can choose from, with their wording on screen.
     *
     * @return array<string, string>
     */
    public static function choices(): array
    {
        return [
            self::PAST_12_MONTHS => __('Past 12 months'),
            self::PAST_6_MONTHS => __('Past 6 months'),
            self::THIS_YEAR => __('This year'),
            self::CUSTOM => __('Custom dates'),
        ];
    }

    //Makes the period for a choice. Past 12/6 months use whole months so the chart has one bar per month.

    public static function fromChoice(string $choice, ?string $from = null, ?string $to = null): self
    {
        $today = CarbonImmutable::today();

        return match ($choice) {
            self::PAST_6_MONTHS => new self($choice, $today->startOfMonth()->subMonthsNoOverflow(5), $today),
            self::THIS_YEAR => new self($choice, $today->startOfYear(), $today),
            self::CUSTOM => new self($choice, CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->startOfDay()),
            default => new self(self::PAST_12_MONTHS, $today->startOfMonth()->subMonthsNoOverflow(11), $today),
        };
    }

    // The start of the first day, for comparing with timestamps.
    public function startsAt(): CarbonImmutable
    {
        return $this->from->startOfDay();
    }

    public function endsBefore(): CarbonImmutable
    {
        return $this->to->addDay()->startOfDay();
    }

    /**
     * The first day of every month the period touches, in order.
     *
     * @return list<CarbonImmutable>
     */
    public function months(): array
    {
        $months = [];

        for ($month = $this->from->startOfMonth(); $month->lte($this->to); $month = $month->addMonthNoOverflow()) {
            $months[] = $month;
        }

        return $months;
    }

    public function description(): string
    {
        return __(':from to :to', ['from' => $this->from->format('j M Y'), 'to' => $this->to->format('j M Y')]);
    }
}
