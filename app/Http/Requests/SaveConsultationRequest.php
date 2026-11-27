<?php

namespace App\Http\Requests;

use App\Models\Symptom;
use App\Role;
use App\Screening\DetectedSymptom;
use App\Screening\SymptomDetector;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Checks the doctor's consultation. Any PMOS symptoms found in the notes must be confirmed or removed first.
 * Notes with nothing PMOS-related (e.g. malaria) save straight away.
 */
class SaveConsultationRequest extends FormRequest
{
    /**
     * Symptoms found in the notes (worked out once per request).
     *
     * @var list<DetectedSymptom>|null
     */
    private ?array $detectedSymptoms = null;

    // Only doctors record symptoms, notes and diagnoses.
    
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
            'notes' => ['nullable', 'string', 'max:5000'],
            'menstrual_history' => ['nullable', 'string', 'max:2000'],
            'diagnosis' => ['nullable', 'string', 'max:255'],
            'decisions' => ['nullable', 'array'],
            'decisions.*' => [Rule::in(['confirm', 'remove'])],
            'added_symptoms' => ['nullable', 'array'],
            'added_symptoms.*' => ['nullable', 'integer', Rule::exists(Symptom::class, 'id')],
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
            'added_symptoms.*.exists' => __('The added symptom is not on the symptom list.'),
        ];
    }

    /**
     * Every symptom found in the notes needs the doctor's decision before saving.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['notes', 'menstrual_history'])) {
                    return;
                }

                foreach ($this->detectedSymptoms() as $detected) {
                    if ($this->decisionFor($detected->code) === null) {
                        $validator->errors()->add('decisions', __('Possible PMOS symptoms were found in your notes. Please confirm or remove each one, then save again.'));

                        return;
                    }
                }
            },
        ];
    }

    /**
     * PMOS symptoms found in the notes and menstrual history.
     *
     * @return list<DetectedSymptom>
     */
    public function detectedSymptoms(): array
    {
        return $this->detectedSymptoms ??= app(SymptomDetector::class)->detect(
            SymptomDetector::combine($this->input('notes'), $this->input('menstrual_history')),
        );
    }

    /**
     * The doctor's choice for a symptom: "confirm", "remove" or null.
     */
    public function decisionFor(string $symptomCode): ?string
    {
        return $this->input("decisions.{$symptomCode}");
    }

    /**
     * The symptoms the doctor added by hand from the dropdown.
     *
     * @return list<int>
     */
    public function addedSymptomIds(): array
    {
        return array_values(array_unique(array_map('intval', array_filter($this->validated('added_symptoms') ?? []))));
    }
}
