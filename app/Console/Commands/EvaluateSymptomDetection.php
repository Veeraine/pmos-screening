<?php

namespace App\Console\Commands;

use App\Screening\DetectionEvaluation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Shows how many symptoms were found, missed or wrong for each set of test notes
 * Unseen sets only show totals (unless --show-unseen) so they don't get used for tuning
 */
#[Signature('pmos:evaluate-detection
    {--all : List every sample note, not just the ones with a miss or a wrong suggestion}
    {--show-unseen : Also list the unseen notes (both sets) with a miss or a wrong suggestion}')]
#[Description('Measure symptom detection against the sample and unseen doctor\'s notes')]
class EvaluateSymptomDetection extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DetectionEvaluation $evaluation): int
    {
        foreach (DetectionEvaluation::sets() as $title => $set) {
            $this->report($title, $evaluation->run($set['notes']), listNotes: ! $set['unseen'] || $this->option('show-unseen'));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{notes: list<array<string, mixed>>, expected: int, found: int, missed: int, suggested: int, wrong: int, correctNotes: int}  $result
     */
    private function report(string $title, array $result, bool $listNotes): void
    {
        $this->newLine();
        $this->info($title);

        if ($listNotes) {
            $rows = [];

            foreach ($result['notes'] as $index => $note) {
                if (! $this->option('all') && $note['missed'] === [] && $note['wrong'] === []) {
                    continue;
                }

                $rows[] = [
                    $index + 1,
                    $note['note'],
                    implode(', ', $note['missed']) ?: '-',
                    implode(', ', $note['wrong']) ?: '-',
                ];
            }

            if ($rows !== []) {
                $this->table(['#', 'Note', 'Missed', 'Wrong suggestion'], $rows);
            }
        }

        $this->line('Notes checked:              '.count($result['notes']));
        $this->line("Notes fully correct:        {$result['correctNotes']} ({$this->percent($result['correctNotes'], count($result['notes']))})");
        $this->line("Expected symptoms:          {$result['expected']}");
        $this->line("  Found:                    {$result['found']} ({$this->percent($result['found'], $result['expected'])})");
        $this->line("  Missed:                   {$result['missed']} ({$this->percent($result['missed'], $result['expected'])})");
        $this->line("Suggestions made:           {$result['suggested']}");
        $this->line("  Wrong suggestions:        {$result['wrong']} ({$this->percent($result['wrong'], $result['suggested'])} of suggestions)");
    }

    private function percent(int $part, int $whole): string
    {
        return $whole === 0 ? '0%' : round($part / $whole * 100, 1).'%';
    }
}
