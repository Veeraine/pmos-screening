<?php

namespace App\Http\Requests;

use App\Reports\ReportPeriod;
use App\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The period picked for the report (past 12 months is the default).
 */
class ShowReportRequest extends FormRequest
{
    // Bad dates go back to the default report with the errors shown.
     
    protected $redirectRoute = 'reports.index';

    // Only doctors see the statistics report.
    
    public function authorize(): bool
    {
        return $this->user()->hasRole(Role::Doctor);
    }

    /**
     * Custom dates must both be given, in order, and not in the future.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(array_keys(ReportPeriod::choices()))],
            'from' => ['exclude_unless:period,'.ReportPeriod::CUSTOM, 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['exclude_unless:period,'.ReportPeriod::CUSTOM, 'required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period.in' => __('Please choose a period from the list.'),
            'from.required' => __('Please enter the first day of the period.'),
            'to.required' => __('Please enter the last day of the period.'),
            'from.date_format' => __('Please enter the first day as a date.'),
            'to.date_format' => __('Please enter the last day as a date.'),
            'from.before_or_equal' => __('The first day cannot be in the future.'),
            'to.before_or_equal' => __('The last day cannot be in the future.'),
            'to.after_or_equal' => __('The last day cannot be before the first day.'),
        ];
    }
    
    // The period to report on.
    
    public function period(): ReportPeriod
    {
        return ReportPeriod::fromChoice(
            $this->validated('period') ?? ReportPeriod::PAST_12_MONTHS,
            $this->validated('from'),
            $this->validated('to'),
        );
    }
}
