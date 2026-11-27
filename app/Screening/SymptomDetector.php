<?php

namespace App\Screening;

use InvalidArgumentException;

/**
 * Looks for PMOS symptoms in the doctor's notes using the word lists in config/screening.php.
 * It's only word matching so it can get things wrong, which is why the doctor confirms each one.
 */
final class SymptomDetector
{
    private const ANY_NUMBER = '(?:\d+(?:\s*(?:-|to)\s*\d+)?|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|a\s+few|few|several|many)';

    // Number words and their values. "few", "several" and "many" count as 3.
    private const NUMBER_WORDS = [
        'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8,
        'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12, 'few' => 3, 'several' => 3, 'many' => 3,
    ];

    /**
     * Patterns already built for each keyword, plus the spelling/number checks to run on them.
     *
     * @var array<string, array{pattern: string, checks: list<array{0: 'word'|'number', 1: string}>}>
     */
    private array $compiledPhrases = [];

    /**
     * Spelling settings, loaded once per set of notes.
     *
     * @var array{enabled: bool, one_mistake_from_letters: int, two_mistakes_from_letters: int, first_letter_must_match: bool, never_fuzzy: array<string, int>}
     */
    private array $spelling;

    /**
     * Cache of spelling comparisons, keyed by "typed|keyword".
     *
     * @var array<string, bool>
     */
    private array $closeEnoughPairs = [];

    /**
     * Maps each position in the expanded notes back to the doctor's original text.
     *
     * @var list<int>
     */
    private array $originalOffsets = [];

    /**
     * [start, end] positions of list items cancelled by a negative, e.g. "no acne or hirsutism".
     *
     * @var list<array{0: int, 1: int}>
     */
    private array $negatedListParts = [];

    /**
     * Finds the symptoms mentioned in the notes, one result per symptom at most.
     *
     * @return list<DetectedSymptom>
     */
    public function detect(?string $notes): array
    {
        if (blank($notes)) {
            return [];
        }

        $this->loadSpellingSettings();

        $text = str_replace(["\u{2018}", "\u{2019}"], "'", $notes);
        [$expandedText, $this->originalOffsets] = $this->expandShorthand(strtolower($text));
        $lowercaseText = $this->withoutIgnoredPhrases($expandedText);
        $clauses = $this->splitIntoClauses($lowercaseText);
        $sentences = $this->splitIntoSentences($lowercaseText);
        $this->negatedListParts = $this->negatedListParts($sentences);
        $found = [];

        foreach (config('screening.symptom_keywords') as $code => $phrases) {
            $detected = $this->firstMatch($code, $text, $clauses, fn (string $clause): array => array_merge(
                ...array_map(fn (string $phrase): array => $this->matchesOf($phrase, $clause), $phrases),
            ));

            // Nearby words can be split by a comma ("weight 92 kg, up from 78 kg") so check whole sentences.
            if (! $detected && config('screening.nearby_words.enabled')) {
                $groups = config("screening.nearby_words.groups.{$code}", []);

                $detected = $this->firstMatch($code, $text, $sentences, fn (string $sentence): array => array_merge(
                    ...array_map(fn (array $group): array => $this->nearbyMatchesOf($group, $sentence), $groups),
                ));
            }

            if ($detected) {
                $found[] = $detected;
            }
        }

        return $found;
    }

    /**
     * First match that isn't cancelled by a negative word. Negatives only count up to the
     * nearest comma, so in "no fever, weight up 5 kg" the "no" is only about fever.
     *
     * @param  list<array{0: string, 1: int}>  $clauses  Parts of sentences, or whole sentences.
     * @param  callable(string): list<array{0: string, 1: int}>  $matchesIn  The matches in one piece of text.
     */
    private function firstMatch(string $code, string $text, array $clauses, callable $matchesIn): ?DetectedSymptom
    {
        foreach ($clauses as [$clause, $clauseStart]) {
            foreach ($matchesIn($clause) as [$matchedWords, $offset]) {
                $before = substr($clause, 0, $offset);
                $after = substr($clause, $offset + strlen($matchedWords));

                if ($this->isNegated(substr($before, strrpos($before, ',') === false ? 0 : strrpos($before, ',') + 1))
                    || $this->isNegatedAfter(explode(',', $after)[0])
                    || $this->isInNegatedList($clauseStart + $offset)) {
                    continue;
                }

                return new DetectedSymptom(
                    code: $code,
                    matchedWords: $this->originalTextBetween($text, $clauseStart + $offset, $clauseStart + $offset + strlen($matchedWords)),
                    excerpt: trim($this->originalTextBetween($text, $clauseStart, $clauseStart + strlen($clause))),
                );
            }
        }

        return null;
    }

    /**
     * Finds list items cancelled by a negative at the start, e.g. "acne or alopecia" in "No hirsutism,
     * acne or alopecia". The list ends at "or"/"and", or at a part that isn't a list item.
     *
     * @param  list<array{0: string, 1: int}>  $sentences
     * @return list<array{0: int, 1: int}>
     */
    private function negatedListParts(array $sentences): array
    {
        $settings = config('screening.negative_lists');

        if (! $settings['enabled']) {
            return [];
        }

        $negatedParts = [];

        foreach ($sentences as [$sentence, $sentenceStart]) {
            $parts = preg_split('/,/', $sentence, -1, PREG_SPLIT_OFFSET_CAPTURE);

            for ($opening = 0; $opening < count($parts) - 1; $opening++) {
                $openingPart = $parts[$opening][0];

                // "no acne or hirsutism" already ends here, so it doesn't carry on.
                if (! $this->containsNegativeWord($openingPart) || $this->hasJoiningWord($openingPart, $settings['joining_words'])) {
                    continue;
                }

                $items = [];
                $reachedJoiningWord = false;

                for ($next = $opening + 1; $next < count($parts) && $this->isListItem($parts[$next][0], $settings); $next++) {
                    $items[] = $parts[$next];

                    if ($this->hasJoiningWord($parts[$next][0], $settings['joining_words'])) {
                        $reachedJoiningWord = true;

                        break;
                    }
                }

                if ($items === [] || ($settings['needs_joining_word'] && ! $reachedJoiningWord)) {
                    continue;
                }

                foreach ($items as [$item, $itemStart]) {
                    $negatedParts[] = [$sentenceStart + $itemStart, $sentenceStart + $itemStart + strlen($item)];
                }

                $opening += count($items);
            }
        }

        return $negatedParts;
    }

    /**
     * Checks if a comma part looks like another list item and not a new finding
     * (no numbers, no "finding_words" like "up" or "is", and short).
     *
     * @param  array{joining_words: list<string>, max_item_words: int, finding_words: list<string>}  $settings
     */
    private function isListItem(string $part, array $settings): bool
    {
        if (preg_match('/\d/', $part)) {
            return false;
        }

        $words = preg_split("/[^a-z']+/", $part, -1, PREG_SPLIT_NO_EMPTY);

        if ($words === [] || array_intersect($words, $settings['finding_words']) !== []) {
            return false;
        }

        $joining = implode('|', array_map(fn (string $word): string => preg_quote($word, '/'), $settings['joining_words']));

        foreach (preg_split("/(?<![a-z'])(?:{$joining})(?![a-z'])/", $part) as $item) {
            $itemWords = preg_split("/[^a-z']+/", $item, -1, PREG_SPLIT_NO_EMPTY);

            if (count($itemWords) > $settings['max_item_words']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks for a joining word like "or" / "and".
     *
     * @param  list<string>  $joiningWords
     */
    private function hasJoiningWord(string $text, array $joiningWords): bool
    {
        $words = preg_split("/[^a-z']+/", $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_intersect($words, $joiningWords) !== [];
    }

    // Checks if a position is inside a cancelled list item.
    private function isInNegatedList(int $position): bool
    {
        foreach ($this->negatedListParts as [$start, $end]) {
            if ($position >= $start && $position < $end) {
                return true;
            }
        }

        return false;
    }

    // Gets the doctor's original wording back, e.g. "LMP 4/12 ago" instead of "lmp 4 months ago".
    private function originalTextBetween(string $text, int $start, int $end): string
    {
        $originalStart = $this->originalOffsets[$start];

        return substr($text, $originalStart, $this->originalOffsets[$end] - $originalStart);
    }

    /**
     * Writes out clinical shorthand so the keywords can match it, e.g. "4/12" -> "4 months", "q2" -> "every 2".
     * Also returns where each position came from in the original text.
     *
     * @return array{0: string, 1: list<int>}
     */
    private function expandShorthand(string $lowercaseText): array
    {
        $offsets = range(0, strlen($lowercaseText));
        $shorthand = config('screening.shorthand');

        // "4/12" or "2-3/52" style numbers, but not dates or blood pressure.
        $fractions = implode('|', array_map(fn ($below): string => preg_quote((string) $below, '/'), array_keys($shorthand['fractions'])));
        [$lowercaseText, $offsets] = $this->rewrite(
            $lowercaseText,
            $offsets,
            '/(?<![\d\/])(\d+(?:\s*-\s*\d+)?)\s*\/\s*('.$fractions.')(?![\d\/])/',
            fn (array $match): string => "{$match[1]} {$shorthand['fractions'][$match[2]]}",
        );

        // A letter written just before a number, e.g. "q3" (every 3) or "x 18" (for 18).
        $letters = implode('|', array_map(fn ($letter): string => preg_quote($letter, '/'), array_keys($shorthand['before_numbers'])));
        [$lowercaseText, $offsets] = $this->rewrite(
            $lowercaseText,
            $offsets,
            '/(?<![a-z0-9])('.$letters.')\s*(?=\d)/',
            fn (array $match): string => $shorthand['before_numbers'][$match[1]].' ',
        );

        // Short words, e.g. "yrs" (years).
        $words = implode('|', array_map(fn ($word): string => preg_quote($word, '/'), array_keys($shorthand['words'])));
        [$lowercaseText, $offsets] = $this->rewrite(
            $lowercaseText,
            $offsets,
            "/(?<![a-z0-9'])({$words})(?![a-z0-9'])/",
            fn (array $match): string => $shorthand['words'][$match[1]],
        );

        return [$lowercaseText, $offsets];
    }

    /**
     * Replaces every match of a pattern and keeps the position map up to date.
     *
     * @param  list<int>  $offsets  For each position in the text, its position in the original.
     * @param  callable(list<string>): string  $replacement
     * @return array{0: string, 1: list<int>}
     */
    private function rewrite(string $text, array $offsets, string $pattern, callable $replacement): array
    {
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $newText = '';
        $newOffsets = [];
        $position = 0;

        foreach ($matches as $match) {
            [$whole, $start] = $match[0];
            $end = $start + strlen($whole);
            $words = $replacement(array_column($match, 0));

            $newText .= substr($text, $position, $start - $position).$words;
            array_push($newOffsets, ...array_slice($offsets, $position, $start - $position));
            array_push($newOffsets, ...array_map(fn (int $index): int => $index === 0 ? $offsets[$start] : $offsets[$end], array_keys(str_split($words))));
            $position = $end;
        }

        $newText .= substr($text, $position);
        array_push($newOffsets, ...array_slice($offsets, $position));

        return [$newText, $newOffsets];
    }

    /**
     * Finds a group of nearby words (one from each set, any order, close together).
     * No match if there's a negative between them ("periods not heavy") or an "unless" word.
     *
     * @param  array<int|string, string>  $group  Sets of words like "hair|hairs", plus an optional "unless".
     * @return list<array{0: string, 1: int}>
     */
    private function nearbyMatchesOf(array $group, string $clause): array
    {
        preg_match_all("/[a-z0-9']+/", $clause, $found, PREG_OFFSET_CAPTURE);
        $words = $found[0];
        $typedWords = array_column($words, 0);

        if (isset($group['unless']) && array_intersect(explode('|', $group['unless']), $typedWords) !== []) {
            return [];
        }

        $positionsPerSet = [];

        foreach (array_filter($group, 'is_int', ARRAY_FILTER_USE_KEY) as $set) {
            $alternatives = explode('|', $set);
            $positions = array_keys(array_filter($typedWords, fn (string $typed): bool => $this->matchesAny($typed, $alternatives)));

            if ($positions === []) {
                return [];
            }

            $positionsPerSet[] = $positions;
        }

        $commaPieceOf = array_map(fn (array $word): int => substr_count($clause, ',', 0, $word[1]), $words);
        $closest = $this->closestCombination($positionsPerSet, fn (array $combination): bool => $this->isInSensibleOrder($combination, $commaPieceOf));

        if ($closest === null) {
            return [];
        }

        [$first, $last] = [min($closest), max($closest)];
        $wordsBetween = $last - $first + 1 - count($closest);

        if ($wordsBetween > config('screening.nearby_words.max_words_between')) {
            return [];
        }

        $start = $words[$first][1];
        $span = substr($clause, $start, $words[$last][1] + strlen($words[$last][0]) - $start);

        if ($this->containsNegativeWord($span)) {
            return [];
        }

        return [[$span, $start]];
    }

    /**
     * Checks if the word matches one of the alternatives, allowing small typos.
     *
     * @param  list<string>  $alternatives
     */
    private function matchesAny(string $typed, array $alternatives): bool
    {
        foreach ($alternatives as $alternative) {
            if ($typed === $alternative || ($this->mayBeMisspelled($alternative) && $this->isCloseEnough($typed, $alternative))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Any order is fine without a comma. Across a comma the words must be in set order
     * ("weight 92 kg, up"), otherwise they're probably separate findings ("BP up, weight 64 kg").
     *
     * @param  list<int>  $combination  One word position per set, in set order.
     * @param  list<int>  $commaPieceOf  Which comma-separated part each word is in.
     */
    private function isInSensibleOrder(array $combination, array $commaPieceOf): bool
    {
        $pieces = array_map(fn (int $position): int => $commaPieceOf[$position], $combination);

        if (count(array_unique($pieces)) === 1) {
            return true;
        }

        $inOrder = $combination;
        sort($inOrder);

        return $inOrder === $combination;
    }

    /**
     * Picks the closest-together combination of word positions (one per set) that passes the filter.
     *
     * @param  list<list<int>>  $positionsPerSet
     * @param  callable(list<int>): bool  $accept
     * @return list<int>|null
     */
    private function closestCombination(array $positionsPerSet, callable $accept): ?array
    {
        $combinations = [[]];

        foreach ($positionsPerSet as $positions) {
            $next = [];

            foreach ($combinations as $combination) {
                foreach ($positions as $position) {
                    if (! in_array($position, $combination, true)) {
                        $next[] = [...$combination, $position];
                    }
                }
            }

            $combinations = $next;
        }

        $closest = null;

        foreach (array_filter($combinations, $accept) as $combination) {
            if ($closest === null || max($combination) - min($combination) < max($closest) - min($closest)) {
                $closest = $combination;
            }
        }

        return $closest;
    }

    // Joins the text boxes so they can be checked together. The line break keeps them apart.
    public static function combine(?string ...$texts): string
    {
        return implode("\n", array_filter($texts, 'filled'));
    }

    // Blanks out ignored phrases like "polycystic ovary syndrome" (a diagnosis, not a symptom).
    private function withoutIgnoredPhrases(string $lowercaseText): string
    {
        foreach (config('screening.ignored_phrases') as $phrase) {
            foreach ($this->matchesOf($phrase, $lowercaseText) as [$matchedWords, $offset]) {
                $lowercaseText = substr_replace($lowercaseText, str_repeat(' ', strlen($matchedWords)), $offset, strlen($matchedWords));
            }
        }

        return $lowercaseText;
    }

    /**
     * Splits the notes into short parts (at commas, full stops, "but" etc).
     *
     * @return list<array{0: string, 1: int}>
     */
    private function splitIntoClauses(string $lowercaseText): array
    {
        return preg_split('/[.;,!?\n]+|\bbut\b|\bhowever\b/', $lowercaseText, -1, PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Splits the notes into sentences. Commas don't split, and "Hb 8.2" stays together.
     *
     * @return list<array{0: string, 1: int}>
     */
    private function splitIntoSentences(string $lowercaseText): array
    {
        return preg_split('/(?:(?<!\d)\.|\.(?!\d)|[;!?\n])+|\bbut\b|\bhowever\b/', $lowercaseText, -1, PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Finds every place a keyword appears, allowing small typos in longer words.
     *
     * @return list<array{0: string, 1: int}>
     */
    private function matchesOf(string $phrase, string $clause): array
    {
        ['pattern' => $pattern, 'checks' => $checks] = $this->compiledPhrases[$phrase] ??= $this->compile($phrase);

        preg_match_all($pattern, $clause, $sets, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $matches = [];

        foreach ($sets as $set) {
            foreach ($checks as $index => [$type, $expected]) {
                $captured = $set[$index + 2][0];

                $passes = $type === 'word'
                    ? $this->isCloseEnough($captured, $expected)
                    : $this->meetsNumberRule($captured, $expected);

                if (! $passes) {
                    continue 2;
                }
            }

            $matches[] = $set[1];
        }

        return $matches;
    }

    /**
     * Turns a keyword like "periods every # months" into a regex. Typos and number rules
     * are checked after matching. Uses a look-ahead so overlapping matches are still found.
     *
     * @return array{pattern: string, checks: list<array{0: 'word'|'number', 1: string}>}
     */
    private function compile(string $phrase): array
    {
        $parts = [];
        $checks = [];

        foreach (preg_split('/\s+/', strtolower(trim($phrase))) as $word) {
            if ($this->mayBeMisspelled($word)) {
                $parts[] = "([a-z']+)";
                $checks[] = ['word', $word];

                continue;
            }

            $part = '';

            foreach (preg_split('/(#|\{[^}]*\})/', $word, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $piece) {
                if ($piece === '#') {
                    $part .= self::ANY_NUMBER;
                } elseif (str_starts_with($piece, '{')) {
                    $part .= '('.self::ANY_NUMBER.')';
                    $checks[] = ['number', substr($piece, 1, -1)];
                } else {
                    $part .= preg_quote($piece, '/');
                }
            }

            $parts[] = $part;
        }

        return [
            'pattern' => '/(?<![a-z0-9])(?=('.implode('\s+', $parts).')(?![a-z0-9]))/',
            'checks' => $checks,
        ];
    }

    // Checks a number against a rule like "3+", "<21" or ">35" (several joined with "|").
    private function meetsNumberRule(string $writtenNumber, string $rule): bool
    {
        foreach ($this->numbersIn($writtenNumber) as $number) {
            foreach (explode('|', $rule) as $condition) {
                $meets = match (true) {
                    (bool) preg_match('/^(\d+)\+$/', $condition, $m) => $number >= (int) $m[1],
                    (bool) preg_match('/^<(\d+)$/', $condition, $m) => $number < (int) $m[1],
                    (bool) preg_match('/^>(\d+)$/', $condition, $m) => $number > (int) $m[1],
                    default => throw new InvalidArgumentException("Unknown number rule \"{$condition}\" in config/screening.php."),
                };

                if ($meets) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Gets the numbers out, e.g. [40, 60] for "40-60" or [3] for "three".
     *
     * @return list<int>
     */
    private function numbersIn(string $writtenNumber): array
    {
        preg_match_all('/\d+|[a-z]+/', $writtenNumber, $matches);

        return array_values(array_filter(array_map(
            fn (string $part): ?int => ctype_digit($part) ? (int) $part : (self::NUMBER_WORDS[$part] ?? null),
            $matches[0],
        ), fn (?int $number): bool => $number !== null));
    }

    // Short words, numbers and "never_fuzzy" words have to be typed exactly.
    private function mayBeMisspelled(string $keywordWord): bool
    {
        return $this->spelling['enabled']
            && preg_match("/^[a-z']+$/", $keywordWord)
            && $this->allowedMistakes($keywordWord) > 0
            && ! isset($this->spelling['never_fuzzy'][$keywordWord]);
    }

    // Checks if a typed word is the keyword or a small typo of it (cached).
    private function isCloseEnough(string $typed, string $keywordWord): bool
    {
        if ($typed === $keywordWord) {
            return true;
        }

        return $this->closeEnoughPairs["{$typed}|{$keywordWord}"] ??= $this->workOutCloseEnough($typed, $keywordWord);
    }

    private function workOutCloseEnough(string $typed, string $keywordWord): bool
    {
        if (isset($this->spelling['never_fuzzy'][$typed])) {
            return false;
        }

        if ($this->spelling['first_letter_must_match'] && $typed[0] !== $keywordWord[0]) {
            return false;
        }

        $allowed = $this->allowedMistakes($keywordWord);

        return abs(strlen($typed) - strlen($keywordWord)) <= $allowed
            && $this->mistakesBetween($typed, $keywordWord) <= $allowed;
    }

    // How many typos are allowed, based on word length.
    private function allowedMistakes(string $keywordWord): int
    {
        $letters = strlen(str_replace("'", '', $keywordWord));

        return match (true) {
            $letters >= $this->spelling['two_mistakes_from_letters'] => 2,
            $letters >= $this->spelling['one_mistake_from_letters'] => 1,
            default => 0,
        };
    }

    // Loads the spelling settings and clears the caches in case the settings changed.
    private function loadSpellingSettings(): void
    {
        $settings = config('screening.spelling_tolerance');

        $this->spelling = [
            'enabled' => (bool) $settings['enabled'],
            'one_mistake_from_letters' => (int) $settings['one_mistake_from_letters'],
            'two_mistakes_from_letters' => (int) $settings['two_mistakes_from_letters'],
            'first_letter_must_match' => (bool) $settings['first_letter_must_match'],
            'never_fuzzy' => array_flip($settings['never_fuzzy']),
        ];
        $this->closeEnoughPairs = [];
        $this->compiledPhrases = [];
    }

    // Counts typos between two words (added, removed, changed or swapped letters).
    private function mistakesBetween(string $a, string $b): int
    {
        $lengthA = strlen($a);
        $lengthB = strlen($b);
        $distance = [];

        for ($i = 0; $i <= $lengthA; $i++) {
            $distance[$i][0] = $i;
        }

        for ($j = 0; $j <= $lengthB; $j++) {
            $distance[0][$j] = $j;
        }

        for ($i = 1; $i <= $lengthA; $i++) {
            for ($j = 1; $j <= $lengthB; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;

                $distance[$i][$j] = min(
                    $distance[$i - 1][$j] + 1,
                    $distance[$i][$j - 1] + 1,
                    $distance[$i - 1][$j - 1] + $cost,
                );

                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $distance[$i][$j] = min($distance[$i][$j], $distance[$i - 2][$j - 2] + 1);
                }
            }
        }

        return $distance[$lengthA][$lengthB];
    }

    // Checks for a negative word ("no", "denies") just before the match.
    private function isNegated(string $textBeforeMatch): bool
    {
        $words = preg_split('/\s+/', trim($textBeforeMatch), -1, PREG_SPLIT_NO_EMPTY);

        return $this->containsNegativeWord(implode(' ', array_slice($words, -config('screening.negative_word_window'))));
    }

    // Checks if the text has a negative word in it.
    private function containsNegativeWord(string $text): bool
    {
        foreach (config('screening.negative_words') as $negativeWord) {
            if (preg_match('/(?<![a-z\'])'.preg_quote($negativeWord, '/').'(?![a-z\'])/', $text)) {
                return true;
            }
        }

        return false;
    }

    // Checks for a negative just after the match, e.g. "acne: none" or "hirsutism - nil".
    
    private function isNegatedAfter(string $textAfterMatch): bool
    {
        $words = preg_split("/[^a-z']+/", $textAfterMatch, -1, PREG_SPLIT_NO_EMPTY);

        foreach (config('screening.negative_words_after') as $negativeWords) {
            $negative = preg_split('/\s+/', $negativeWords);

            for ($start = 0; $start < config('screening.negative_word_window_after'); $start++) {
                if (array_slice($words, $start, count($negative)) === $negative) {
                    return true;
                }
            }
        }

        return false;
    }
}
