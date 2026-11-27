<?php

namespace Tests\Feature;

use App\Screening\DetectionEvaluation;
use Tests\TestCase;

/**
 * Checks symptom detection stays accurate on the sample notes.
 */
class SymptomDetectionEvaluationTest extends TestCase
{
    /**
     * @return array{notes: list<array<string, mixed>>, expected: int, found: int, missed: int, suggested: int, wrong: int, correctNotes: int}
     */
    private function evaluate(): array
    {
        return app(DetectionEvaluation::class)->run(DetectionEvaluation::sampleNotes());
    }

    public function test_the_sample_notes_are_valid(): void
    {
        $notes = DetectionEvaluation::sampleNotes();
        $symptomCodes = array_keys(config('screening.symptom_keywords'));

        $this->assertGreaterThanOrEqual(40, count($notes));

        foreach ($notes as $note) {
            $this->assertNotSame('', trim($note['note']));
            $this->assertSame([], array_diff($note['expected'], $symptomCodes), "Unknown symptom code in: {$note['note']}");
        }
    }

    public function test_the_unseen_notes_are_valid_and_kept_apart_from_the_other_sets(): void
    {
        $symptomCodes = array_keys(config('screening.symptom_keywords'));
        $sets = [
            'sample' => DetectionEvaluation::sampleNotes(),
            'unseen 1' => DetectionEvaluation::unseenNotes(),
            'unseen 2' => DetectionEvaluation::unseenNotes2(),
        ];

        $this->assertGreaterThanOrEqual(36, count($sets['unseen 1']));
        $this->assertGreaterThanOrEqual(25, count($sets['unseen 2']));

        $allNotes = array_merge(...array_map(fn (array $set): array => array_column($set, 'note'), array_values($sets)));
        $this->assertSame(count($allNotes), count(array_unique($allNotes)), 'A note appears in more than one set.');

        foreach ([$sets['unseen 1'], $sets['unseen 2']] as $unseen) {
            foreach ($unseen as $note) {
                $this->assertNotSame('', trim($note['note']));
                $this->assertSame([], array_diff($note['expected'], $symptomCodes), "Unknown symptom code in: {$note['note']}");
            }
        }
    }

    public function test_the_command_reports_each_unseen_set_separately_with_totals_only(): void
    {
        $unseen1 = app(DetectionEvaluation::class)->run(DetectionEvaluation::unseenNotes());
        $unseen2 = app(DetectionEvaluation::class)->run(DetectionEvaluation::unseenNotes2());

        $this->artisan('pmos:evaluate-detection')
            ->expectsOutputToContain('Unseen notes, set 1')
            ->expectsOutputToContain('Notes checked:              '.count($unseen1['notes']))
            ->expectsOutputToContain('Unseen notes, set 2')
            ->expectsOutputToContain('Notes checked:              '.count($unseen2['notes']))
            ->doesntExpectOutputToContain($unseen1['notes'][0]['note'])
            ->doesntExpectOutputToContain($unseen2['notes'][0]['note'])
            ->assertSuccessful();
    }

    public function test_at_least_90_percent_of_expected_symptoms_are_found(): void
    {
        $result = $this->evaluate();

        $this->assertGreaterThanOrEqual(0.9, $result['found'] / $result['expected'], "Only {$result['found']} of {$result['expected']} expected symptoms were found.");
    }

    public function test_at_most_5_percent_of_suggestions_are_wrong(): void
    {
        $result = $this->evaluate();

        $this->assertLessThanOrEqual(0.05, $result['wrong'] / $result['suggested'], "{$result['wrong']} of {$result['suggested']} suggestions were wrong.");
    }

    public function test_notes_about_unrelated_illnesses_or_negatives_get_no_suggestions(): void
    {
        foreach ($this->evaluate()['notes'] as $note) {
            if ($note['expected'] === []) {
                $this->assertSame([], $note['suggested'], "Wrong suggestion for: {$note['note']}");
            }
        }
    }

    public function test_the_command_reports_the_results_as_numbers_and_percentages(): void
    {
        $result = $this->evaluate();

        $this->artisan('pmos:evaluate-detection')
            ->expectsOutputToContain('Notes checked:              '.count($result['notes']))
            ->expectsOutputToContain("Expected symptoms:          {$result['expected']}")
            ->expectsOutputToContain("  Found:                    {$result['found']} (")
            ->expectsOutputToContain("  Wrong suggestions:        {$result['wrong']} (")
            ->assertSuccessful();
    }
}
