<?php

namespace App\Screening;

/**
 * A PMOS symptom found in a doctor's notes, waiting for the doctor to confirm or remove it.
 */
final readonly class DetectedSymptom
{
    /**
     * @param  string  $code  The symptom code, e.g. "excess_hair_growth".
     * @param  string  $matchedWords  The words in the notes that matched, as the doctor wrote them.
     * @param  string  $excerpt  The part of the sentence the words were found in.
     */
    public function __construct(
        public string $code,
        public string $matchedWords,
        public string $excerpt,
    ) {}

    /**
     * The excerpt split around the matched words, so a page can highlight them.
     *
     * @return array{before: string, match: string, after: string}
     */
    public function excerptParts(): array
    {
        $position = stripos($this->excerpt, $this->matchedWords);

        if ($position === false) {
            return ['before' => $this->excerpt, 'match' => '', 'after' => ''];
        }

        return [
            'before' => substr($this->excerpt, 0, $position),
            'match' => substr($this->excerpt, $position, strlen($this->matchedWords)),
            'after' => substr($this->excerpt, $position + strlen($this->matchedWords)),
        ];
    }
}
