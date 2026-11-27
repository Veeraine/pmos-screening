<?php

namespace App\Http\Requests;

use App\Models\Alert;
use App\Role;
use App\ScreeningOutcome;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The result of a referral: PMOS confirmed or ruled out, and on what date.
 */
class RecordOutcomeRequest extends FormRequest
{
    /**
     * Only doctors record referral outcomes.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole(Role::Doctor);
    }

    /**
     * Get the validation rules that apply to the request. The date can't be in the future or before the referral.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Alert $alert */
        $alert = $this->route('alert');

        return [
            'outcome' => ['required', Rule::enum(ScreeningOutcome::class)],
            'outcome_date' => [
                'required',
                'date',
                'before_or_equal:today',
                'after_or_equal:'.($alert->decided_at?->toDateString() ?? 'today'),
            ],
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
            'outcome.required' => __('Please choose the result: PMOS confirmed or ruled out.'),
            'outcome_date.required' => __('Please enter the date of the result.'),
            'outcome_date.before_or_equal' => __('The date of the result cannot be in the future.'),
            'outcome_date.after_or_equal' => __('The date of the result cannot be before the patient was referred.'),
        ];
    }
}
