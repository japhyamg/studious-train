<?php

namespace App\Services;

/**
 * Scored watchlist name matching (CBN 5.3(a)(ii)).
 *
 * Replaces plain LIKE matching with a 0–100 score built from exact,
 * normalised, token-reorder/subset and phonetic (metaphone) comparisons.
 * Exact identifier matches (BVN/NIN/account) are decisive (score 100).
 */
class WatchListMatcher
{
    private int $threshold;

    public function __construct(?int $threshold = null)
    {
        $this->threshold = $threshold
            ?? (int) settings('watchlist_match_threshold', config('watchlists.match_threshold', 75));
    }

    /**
     * Score a screening subject against one list record.
     *
     * @param array $subject        subject attributes (first_name, bvn, …)
     * @param array $record         list record attributes
     * @param array $nameFields     fields treated as names
     * @param array $identifierFields fields matched exactly (BVN/NIN/account)
     * @return array{matched: bool, score: int, fields: array}
     */
    public function match(array $subject, array $record, array $nameFields, array $identifierFields): array
    {
        // 1. Exact identifier hits are decisive.
        foreach ($identifierFields as $field) {
            if (!empty($subject[$field]) && !empty($record[$field])
                && $this->normalizeIdent($subject[$field]) === $this->normalizeIdent($record[$field])) {
                return ['matched' => true, 'score' => 100, 'fields' => [$field]];
            }
        }

        // 2. Fuzzy name scoring.
        $subjectName = $this->composeName($subject, $nameFields);
        $entryName = $this->composeName($record, $nameFields);
        $score = $this->scoreName($subjectName, $entryName, $record['aliases'] ?? []);

        return [
            'matched' => $score >= $this->threshold,
            'score' => $score,
            'fields' => $score >= $this->threshold ? $nameFields : [],
        ];
    }

    /**
     * Score a name against an entry's primary name and any aliases.
     */
    public function scoreName(string $subjectName, string $entryName, array $aliases = []): int
    {
        $subject = $this->normalizeName($subjectName);
        if ($subject === '') return 0;

        $best = 0;
        foreach (array_merge([$entryName], $aliases) as $target) {
            $t = $this->normalizeName((string) $target);
            if ($t === '') continue;
            $best = max($best, $this->scorePair($subject, $t));
        }

        return $best;
    }

    private function scorePair(string $a, string $b): int
    {
        if ($a === $b) return 100;

        $ta = $this->tokens($a);
        $tb = $this->tokens($b);
        if ($ta === [] || $tb === []) return 0;

        // Same token set, any order (e.g. "John Doe" vs "Doe John").
        if (count($ta) === count($tb) && array_diff($ta, $tb) === [] && array_diff($tb, $ta) === []) {
            return 98;
        }

        // One name is a token-subset of the other (missing middle name, etc.).
        [$small, $large] = count($ta) <= count($tb) ? [$ta, $tb] : [$tb, $ta];
        $smallMatches = array_filter($small, fn($t) => in_array($t, $large, true));
        if (count($smallMatches) === count($small) && count($small) > 0) {
            return (int) round(80 + 15 * (count($small) / count($large)));
        }

        // Jaccard-style overlap.
        $intersection = count(array_intersect($ta, $tb));
        $union = count(array_unique(array_merge($ta, $tb)));
        $overlap = $union > 0 ? $intersection / $union : 0.0;

        // Phonetic (metaphone) overlap for misspelling tolerance.
        $pa = array_map(fn($t) => metaphone($t), $ta);
        $pb = array_map(fn($t) => metaphone($t), $tb);
        $phoneticIntersection = count(array_intersect($pa, $pb));
        $phoneticOverlap = $union > 0 ? $phoneticIntersection / $union : 0.0;

        return (int) round(max($overlap, $phoneticOverlap) * 100);
    }

    private function composeName(array $row, array $nameFields): string
    {
        $fields = array_values(array_unique(array_merge(['first_name', 'middle_name', 'last_name'], $nameFields)));
        $parts = [];
        foreach ($fields as $field) {
            if (!empty($row[$field])) $parts[] = trim((string) $row[$field]);
        }
        return implode(' ', $parts);
    }

    private function normalizeName(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9\s]/', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        return trim($name);
    }

    private function normalizeIdent($value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? (string) $value;
    }

    private function tokens(string $name): array
    {
        return array_values(array_filter(explode(' ', $name), fn($t) => $t !== ''));
    }
}
