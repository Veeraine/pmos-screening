<?php

namespace App\Http\Requests;

use App\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Checks the vital signs. Ranges are wide but catch typos like 700 kg or 3.7 °C.
 * Height is only needed at the first visit.
 */
class SaveVitalSignsRequest extends FormRequest
{
    // Only nurses can record vital signs.
 
    public function authorize(): bool
    {
        return $this->user()->hasRole(Role::Nurse);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'weight_kg' => ['required', 'numeric', 'between:2,300'],
            'height_cm' => [
                Rule::requiredIf(fn (): bool => $this->route('visit')->lastRecordedHeight() === null),
                'nullable',
                'numeric',
                'between:40,250',
            ],
            'systolic_bp' => ['required', 'integer', 'between:50,260', 'gt:diastolic_bp'],
            'diastolic_bp' => ['required', 'integer', 'between:30,160'],
            'temperature_c' => ['required', 'numeric', 'between:30,45'],
            'pulse_rate' => ['required', 'integer', 'between:20,250'],
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'weight_kg' => __('weight'),
            'height_cm' => __('height'),
            'systolic_bp' => __('systolic blood pressure'),
            'diastolic_bp' => __('diastolic blood pressure'),
            'temperature_c' => __('temperature'),
            'pulse_rate' => __('pulse rate'),
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
            'height_cm.required' => __("Height is required at the patient's first visit."),
            'systolic_bp.gt' => __('The systolic (top) number must be higher than the diastolic (bottom) number.'),
        ];
    }
}
