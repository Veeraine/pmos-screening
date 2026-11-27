<?php

namespace App\Http\Requests;

use App\Models\Patient;
use App\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePatientRequest extends FormRequest
{
    /**
     * Only nurses register patients.
     */
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
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s]+$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'previous_diagnoses' => ['nullable', 'string', 'max:2000'],
            'confirmed_different_person' => ['boolean'],
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
            'date_of_birth.before_or_equal' => __('The date of birth cannot be in the future.'),
            'phone.regex' => __('The phone number may only contain digits, spaces, + and -.'),
        ];
    }

    /**
     * Stops the same patient being registered twice, unless the nurse confirms it's a different person.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['first_name', 'last_name', 'date_of_birth'])
                    || $this->boolean('confirmed_different_person')) {
                    return;
                }

                $existingNumbers = Patient::possibleDuplicatesOf($this->only('first_name', 'last_name', 'date_of_birth'))
                    ->pluck('patient_number');

                if ($existingNumbers->isNotEmpty()) {
                    $validator->errors()->add('duplicate', __(
                        'A patient with this name and date of birth is already registered (:numbers). Open their record instead, or tick the box below if this is a different person.',
                        ['numbers' => $existingNumbers->join(', ')],
                    ));
                }
            },
        ];
    }
}
