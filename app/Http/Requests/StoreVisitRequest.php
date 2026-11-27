<?php

namespace App\Http\Requests;

use App\Models\Patient;
use App\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVisitRequest extends FormRequest
{
    /**
     * Only nurses check patients in.
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
            'reason' => ['required', 'string', 'max:255'],
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
            'reason.required' => __('Please enter the reason for the visit.'),
        ];
    }

    /**
     * A patient can only be checked in once per day.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Patient $patient */
                $patient = $this->route('patient');

                if ($patient->visits()->today()->exists()) {
                    $validator->errors()->add('reason', __('This patient has already been checked in today.'));
                }
            },
        ];
    }
}
