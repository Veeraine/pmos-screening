<?php

namespace App\Http\Requests;

use App\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The doctor's decision on an alert, the doctor decides, but needs a reason if not referring.
 */
class DecideAlertRequest extends FormRequest
{
    
    // Only doctors act on alerts.
   
    public function authorize(): bool
    {
        return $this->user()->hasRole(Role::Doctor);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'referred' => ['required', 'boolean'],
            'not_referred_reason' => [
                Rule::requiredIf(fn (): bool => $this->input('referred') === '0'),
                'nullable',
                'string',
                'max:1000',
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
            'referred.required' => __('Please choose whether the patient was referred for screening.'),
            'not_referred_reason.required' => __('Please give a reason for not referring the patient.'),
        ];
    }
}
