<?php

namespace App\Screening;

/**
 * Tests how well symptom detection works on notes where we know the answer (database/evaluation).
 * The unseen sets were never used for tuning, so they show how it copes with new wording.
 */
final class DetectionEvaluation
{
    //  Where the sample notes are kept.
    public const NOTES_FILE = 'database/evaluation/symptom-detection-notes.php';

    // Where unseen set 1 is kept.
    public const UNSEEN_NOTES_FILE = 'database/evaluation/symptom-detection-unseen-notes.php';

    // Where unseen set 2 is kept.
    public const UNSEEN_NOTES_2_FILE = 'database/evaluation/symptom-detection-unseen-notes-2.php';

    public function __construct(private SymptomDetector $detector) {}

    /**
     * All the note sets by name, and whether each one is unseen.
     *
     * @return array<string, array{notes: list<array{note: string, expected: list<string>}>, unseen: bool}>
     */
    public static function sets(): array
    {
        return [
            'Sample notes' => ['notes' => self::sampleNotes(), 'unseen' => false],
            'Unseen notes, set 1' => ['notes' => self::unseenNotes(), 'unseen' => true],
            'Unseen notes, set 2' => ['notes' => self::unseenNotes2(), 'unseen' => true],
        ];
    }

    /**
     * The sample notes, each with its expected symptom codes.
     *
     * @return list<array{note: string, expected: list<string>}>
     */
    public static function sampleNotes(): array
    {
        return require base_path(self::NOTES_FILE);
    }

    /**
     * Unseen set 1, each note with its expected symptom codes.
     *
     * @return list<array{note: string, expected: list<string>}>
     */
    public static function unseenNotes(): array
    {
        return require base_path(self::UNSEEN_NOTES_FILE);
    }

    /**
     * Unseen set 2, each note with its expected symptom codes.
     *
     * @return list<array{note: string, expected: list<string>}>
     */
    public static function unseenNotes2(): array
    {
        return require base_path(self::UNSEEN_NOTES_2_FILE);
    }

    /**
     * Runs detection on every note and counts found, missed and wrong.
     *
     * @param  list<array{note: string, expected: list<string>}>  $cases
     * @return array{
     *     notes: list<array{note: string, expected: list<string>, suggested: list<string>, missed: list<string>, wrong: list<string>}>,
     *     expected: int, found: int, missed: int, suggested: int, wrong: int, correctNotes: int
     * }
     */
    public function run(array $cases): array
    {
        $notes = [];

        foreach ($cases as $case) {
            $suggested = array_map(fn (DetectedSymptom $symptom): string => $symptom->code, $this->detector->detect($case['note']));

            $notes[] = [
                'note' => $case['note'],
                'expected' => $case['expected'],
                'suggested' => $suggested,
                'missed' => array_values(array_diff($case['expected'], $suggested)),
                'wrong' => array_values(array_diff($suggested, $case['expected'])),
            ];
        }

        $expected = array_sum(array_map(fn (array $note): int => count($note['expected']), $notes));
        $missed = array_sum(array_map(fn (array $note): int => count($note['missed']), $notes));

        return [
            'notes' => $notes,
            'expected' => $expected,
            'found' => $expected - $missed,
            'missed' => $missed,
            'suggested' => array_sum(array_map(fn (array $note): int => count($note['suggested']), $notes)),
            'wrong' => array_sum(array_map(fn (array $note): int => count($note['wrong']), $notes)),
            'correctNotes' => count(array_filter($notes, fn (array $note): bool => $note['missed'] === [] && $note['wrong'] === [])),
        ];
    }
}
