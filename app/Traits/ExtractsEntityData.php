<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait ExtractsEntityData
{
    private function extractFromSnippets(string $fullName, array $searchResults): array
    {
        $corpus = collect($searchResults)
            ->map(fn($r) => implode(' ', array_filter([$r['title'] ?? '', $r['snippet'] ?? '', $r['displayed_link'] ?? ''])))
            ->implode(' ');
        $corpus = html_entity_decode($corpus, ENT_QUOTES | ENT_HTML5);
        $corpus = preg_replace('/\s+/', ' ', $corpus);

        $extractionMethod = 'snippet_regex';

        // Try Gemini first
        $aiResult = $this->extractWithGemini($fullName, $corpus);
        if ($aiResult !== null) {
            $extractionMethod = 'gemini_flash';
            $result = $aiResult;
        } else {
            // Full regex extraction
            $providedParts = $this->parseNameParts($fullName);
            $foundNames = $this->extractNamesFromCorpus($corpus, $fullName);
            $nameConfidence = $this->scoreNameConfidence($providedParts, $foundNames);

            $result = [
                'provided_name' => ['first_name' => $providedParts['first'], 'middle_name' => $providedParts['middle'], 'last_name' => $providedParts['last'], 'full_name' => $fullName],
                'found_name' => ['first_name' => $foundNames['first'], 'middle_name' => $foundNames['middle'], 'last_name' => $foundNames['last'], 'full_name' => $foundNames['full'], 'variants' => $foundNames['variants']],
                'name_match' => $nameConfidence,
                'date_of_birth' => $this->extractDobFromText($corpus),
                'age' => $this->extractAgeFromText($corpus),
                'state_of_origin' => $this->extractStateFromText($corpus),
                'nationality' => $this->extractNationalityFromText($corpus),
                'number_of_children' => null,
                'marital_status' => $this->extractMaritalStatusFromText($corpus),
                'spouse' => '',
                'current_positions' => $this->extractPositionsFromText($corpus),
                'previous_roles' => $this->extractPreviousRolesFromText($corpus),
                'schools_attended' => [],
                'political_party' => $this->extractPartyFromText($corpus),
                'religion' => $this->extractReligionFromText($corpus),
                'net_worth' => '',
                'aliases' => [],
                'summary' => $this->buildSummary($corpus, $fullName),
            ];
        }

        $result['_meta'] = ['extraction_method' => $extractionMethod, 'corpus_length' => strlen($corpus), 'result_count' => count($searchResults), 'extracted_at' => now()->toISOString()];
        return $result;
    }

    private function extractWithGemini(string $fullName, string $corpus): ?array
    {
        $apiKey = config('services.gemini.key');
        if (!$apiKey) return null;

        try {
            $prompt = "Extract KYC data about \"{$fullName}\" from these search snippets. Return ONLY valid JSON:\n{\"provided_name\":{\"first_name\":\"\",\"middle_name\":\"\",\"last_name\":\"\",\"full_name\":\"\"},\"found_name\":{\"first_name\":\"\",\"middle_name\":\"\",\"last_name\":\"\",\"full_name\":\"\",\"variants\":[]},\"name_match\":{\"confidence\":\"HIGH|MEDIUM|LOW|NO_MATCH\",\"score\":0,\"matched_parts\":[],\"mismatched_parts\":[],\"notes\":[]},\"date_of_birth\":\"\",\"age\":null,\"state_of_origin\":\"\",\"nationality\":\"\",\"marital_status\":\"\",\"spouse\":\"\",\"current_positions\":[],\"previous_roles\":[],\"schools_attended\":[],\"political_party\":\"\",\"religion\":\"\",\"net_worth\":\"\",\"aliases\":[],\"summary\":\"\"}\n\nText:\n" . substr($corpus, 0, 6000);

            $response = Http::timeout(15)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}", [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 1500, 'responseMimeType' => 'application/json'],
            ]);

            if (!$response->successful()) return null;
            $text = $response->json('candidates.0.content.parts.0.text', '');
            if (empty($text)) return null;
            $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
            $text = preg_replace('/\s*```$/', '', $text);
            $decoded = json_decode($text, true);
            return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
        } catch (\Exception $e) {
            Log::warning('Gemini extraction failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function emptyEntityData(): array
    {
        return [
            'provided_name' => ['first_name' => '', 'middle_name' => '', 'last_name' => '', 'full_name' => ''],
            'found_name' => ['first_name' => '', 'middle_name' => '', 'last_name' => '', 'full_name' => '', 'variants' => []],
            'name_match' => ['confidence' => 'NO_MATCH', 'score' => 0, 'matched_parts' => [], 'mismatched_parts' => [], 'notes' => []],
            'date_of_birth' => '', 'age' => null, 'state_of_origin' => '', 'nationality' => '',
            'number_of_children' => null, 'marital_status' => '', 'spouse' => '',
            'current_positions' => [], 'previous_roles' => [], 'schools_attended' => [],
            'political_party' => '', 'religion' => '', 'net_worth' => '', 'aliases' => [],
            'summary' => '', '_meta' => [],
        ];
    }

    // ═══════════ NAME PARSING ═══════════
    private function parseNameParts(string $fullName): array
    {
        $cleaned = preg_replace('/^(?:Dr\.?|Prof\.?|Mr\.?|Mrs\.?|Ms\.?|Sir|Chief|Alhaji|Alhaja|Hon\.?|Rt\.?\s*Hon\.?|Engr\.?|Arc\.?)\s+/i', '', trim($fullName));
        if (str_contains($cleaned, ',')) {
            [$last, $rest] = explode(',', $cleaned, 2);
            $parts = array_values(array_filter(explode(' ', trim($rest))));
            return ['first' => $parts[0] ?? '', 'middle' => implode(' ', array_slice($parts, 1)), 'last' => trim($last)];
        }
        $parts = array_values(array_filter(explode(' ', $cleaned)));
        $count = count($parts);
        return match (true) {
            $count === 1 => ['first' => $parts[0], 'middle' => '', 'last' => ''],
            $count === 2 => ['first' => $parts[0], 'middle' => '', 'last' => $parts[1]],
            default => ['first' => $parts[0], 'middle' => implode(' ', array_slice($parts, 1, $count - 2)), 'last' => $parts[$count - 1]],
        };
    }

    // ═══════════ NAME EXTRACTION FROM CORPUS ═══════════
    private function extractNamesFromCorpus(string $corpus, string $fullName): array
    {
        $empty = ['first' => '', 'middle' => '', 'last' => '', 'full' => '', 'variants' => []];
        $provided = $this->parseNameParts($fullName);
        $allVariants = [];

        // Clean corpus
        $clean = preg_replace('/https?:\/\/\S+/', '', $corpus);
        $clean = preg_replace('/\b(?:Wikipedia|Youtube|Facebook|Twitter|Google|Reuters|Bloomberg)\s+/i', '', $clean);
        $clean = preg_replace('/\s+/', ' ', $clean);

        // Extract capitalized name sequences
        if (preg_match_all('/\b([A-Z][a-z]{1,20}(?:\s+[A-Z][a-z]{1,20}){1,3})\b/', $clean, $matches)) {
            foreach ($matches[1] as $candidate) {
                if ($this->isPlausibleName($candidate)) $allVariants[] = $candidate;
            }
        }

        // Structured patterns
        $patterns = [
            '/(?:Mr\.?|Mrs\.?|Dr\.?|Prof\.?|Hon\.?|Chief|Alhaji)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,3})/m',
            '/^([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,3})\s+(?:\(born|is a|was a)/m',
            '/(?:^|\.\s)([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,3})\s+(?:served|was|is|has|became|appointed)/m',
        ];
        foreach ($patterns as $pat) {
            if (preg_match_all($pat, $clean, $m)) {
                foreach ($m[1] as $c) { if ($this->isPlausibleName(trim($c))) $allVariants[] = trim($c); }
            }
        }

        $allVariants = array_values(array_unique($allVariants));
        if (empty($allVariants)) return $empty;

        // Score variants against provided name
        $scored = [];
        foreach ($allVariants as $v) {
            $score = 0;
            $vl = strtolower($v);
            foreach (array_filter([$provided['first'], $provided['middle'], $provided['last']]) as $part) {
                $pl = strtolower($part);
                if (str_contains($vl, $pl)) $score += ($part === $provided['last'] ? 5 : 3);
                else {
                    foreach (explode(' ', $vl) as $w) {
                        $d = levenshtein($pl, $w);
                        if (max(strlen($pl), strlen($w)) > 0 && ($d / max(strlen($pl), strlen($w))) <= 0.25) $score += 2;
                    }
                }
            }
            if (str_contains($vl, strtolower($fullName)) || str_contains(strtolower($fullName), $vl)) $score += 4;
            if ($score > 0) $scored[$v] = $score;
        }

        if (empty($scored)) return $empty;
        arsort($scored);
        $best = array_key_first($scored);
        $variants = array_values(array_filter(array_keys($scored), fn($v) => $v !== $best));

        // Parse best name into parts
        $bestParts = $this->parseNameParts($best);

        return [
            'first' => $bestParts['first'], 'middle' => $bestParts['middle'],
            'last' => $bestParts['last'], 'full' => $best,
            'variants' => array_slice($variants, 0, 5),
        ];
    }

    private function isPlausibleName(string $candidate): bool
    {
        $wc = str_word_count($candidate);
        if ($wc < 2 || $wc > 4 || strlen($candidate) < 5 || strlen($candidate) > 55) return false;
        $stops = ['The','This','When','Where','Which','How','Who','What','New','United','Federal','State','House','Senate','National','Supreme','High','All','Rights','Privacy','Policy','Terms','Read','More','Click','View','Wikipedia','Youtube','Africa','Nigeria','He','She','His','Her','They','Mr','Mrs','Former','Current','Late','President','Governor'];
        foreach (explode(' ', $candidate) as $word) {
            if (in_array($word, $stops, true)) return false;
            if (!preg_match('/^[A-Z][a-zA-Z\'-]{1,}$/', $word)) return false;
        }
        return true;
    }

    // ═══════════ NAME CONFIDENCE SCORING ═══════════
    private function scoreNameConfidence(array $provided, array $found): array
    {
        if (empty($found['full'])) return ['confidence' => 'NO_MATCH', 'score' => 0, 'matched_parts' => [], 'mismatched_parts' => ['first', 'middle', 'last'], 'notes' => ['No recognizable name found.']];

        $score = 0; $matched = []; $mismatched = []; $notes = [];
        $weights = ['first' => 30, 'middle' => 20, 'last' => 40];
        $foundWords = array_map('strtolower', explode(' ', $found['full']));

        foreach (['first', 'middle', 'last'] as $part) {
            $pVal = strtolower(trim($provided[$part] ?? ''));
            $fVal = strtolower(trim($found[$part] ?? ''));

            if ($part === 'middle' && !$pVal && !$fVal) { $score += $weights[$part]; continue; }
            if (!$pVal) { $score += (int)($weights[$part] * 0.5); continue; }

            if ($pVal === $fVal) { $score += $weights[$part]; $matched[] = $part; }
            elseif ($fVal && levenshtein($pVal, $fVal) <= max(strlen($pVal), strlen($fVal)) * 0.25) { $score += (int)($weights[$part] * 0.6); $matched[] = $part; $notes[] = ucfirst($part) . ' matched as fuzzy.'; }
            elseif (in_array($pVal, $foundWords)) { $score += (int)($weights[$part] * 0.7); $matched[] = $part; $notes[] = ucfirst($part) . " found in full name."; }
            else { $mismatched[] = $part; }
        }

        // String similarity bonus
        $pFull = strtolower(implode(' ', array_filter([$provided['first'], $provided['middle'], $provided['last']])));
        similar_text($pFull, strtolower($found['full']), $sim);
        if ($sim >= 70) { $score = min(100, $score + ($sim >= 90 ? 15 : ($sim >= 80 ? 10 : 5))); $notes[] = 'Full name strings are ' . round($sim) . '% similar.'; }

        $score = min(100, max(0, $score));
        return [
            'confidence' => match(true) { $score >= 85 => 'HIGH', $score >= 60 => 'MEDIUM', $score >= 35 => 'LOW', default => 'NO_MATCH' },
            'score' => $score, 'matched_parts' => $matched, 'mismatched_parts' => $mismatched, 'notes' => $notes,
        ];
    }

    // ═══════════ FIELD EXTRACTORS ═══════════
    private function extractDobFromText(string $text): string
    {
        $patterns = ['/born\s+(?:on\s+)?(\w+\s+\d{1,2},?\s+\d{4})/i', '/born\s+(?:on\s+)?(\d{1,2}\s+\w+\s+\d{4})/i', '/\(born\s+(\w+\s+\d{1,2},?\s+\d{4})\)/i', '/born[:\s]+(\d{4})(?!\s*[-–])/i'];
        foreach ($patterns as $p) { if (preg_match($p, $text, $m)) { try { $ts = strtotime(trim($m[1])); return $ts ? date('d M Y', $ts) : trim($m[1]); } catch (\Exception $e) { return trim($m[1]); } } }
        return '';
    }

    private function extractAgeFromText(string $text): ?int
    {
        $patterns = ['/(?:aged?|is)\s+(\d{2,3})\s+years?\s+old/i', '/\(age\s+(\d{2,3})\)/i', '/(\d{2,3})[- ]year[- ]old/i'];
        foreach ($patterns as $p) { if (preg_match($p, $text, $m)) { $age = (int)$m[1]; if ($age >= 18 && $age <= 100) return $age; } }
        return null;
    }

    private function extractStateFromText(string $text): string
    {
        $states = ['Abia','Adamawa','Akwa Ibom','Anambra','Bauchi','Bayelsa','Benue','Borno','Cross River','Delta','Ebonyi','Edo','Ekiti','Enugu','Gombe','Imo','Jigawa','Kaduna','Kano','Katsina','Kebbi','Kogi','Kwara','Lagos','Nasarawa','Niger','Ogun','Ondo','Osun','Oyo','Plateau','Rivers','Sokoto','Taraba','Yobe','Zamfara','FCT','Abuja'];
        foreach ($states as $s) { if (preg_match('/\b' . preg_quote($s, '/') . '\b/i', $text)) return $s; }
        return '';
    }

    private function extractNationalityFromText(string $text): string
    {
        $nationalities = ['Nigerian','Ghanaian','Kenyan','South African','American','British','Canadian','French','German','Chinese','Indian','Brazilian'];
        if (preg_match('/(?:nationality|citizen)[:\s]+([A-Z][a-zA-Z]+)/i', $text, $m)) return trim($m[1]);
        foreach ($nationalities as $n) { if (preg_match('/\b' . preg_quote($n, '/') . '\b/i', $text)) return $n; }
        return '';
    }

    private function extractMaritalStatusFromText(string $text): string
    {
        $map = ['married' => ['married','wife','husband','spouse'], 'single' => ['single','unmarried'], 'divorced' => ['divorced','ex-wife','ex-husband'], 'widowed' => ['widowed','widow','widower']];
        foreach ($map as $status => $kws) { foreach ($kws as $kw) { if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $text)) return $status; } }
        return '';
    }

    private function extractPositionsFromText(string $text): array
    {
        $positions = [];
        $titles = ['President','Vice President','Prime Minister','Minister','Governor','Deputy Governor','Senator','Representative','Commissioner','Director General','Director','Chairman','CEO','Secretary','Ambassador','Speaker'];
        $current = ['current','serving','incumbent','is the','is a','serves as'];
        foreach ($titles as $t) {
            foreach ($current as $ind) {
                if (preg_match('/' . preg_quote($ind, '/') . '\s+(?:\w+\s+){0,3}' . preg_quote($t, '/') . '(?:\s+of\s+([A-Z][a-zA-Z\s]+?))?(?:[,.\n]|$)/i', $text, $m)) {
                    $positions[] = ['title' => $t, 'organisation' => isset($m[1]) ? trim($m[1]) : '', 'from' => '', 'to' => 'present'];
                    break;
                }
            }
        }
        // Deduplicate
        $seen = []; $unique = [];
        foreach ($positions as $p) { $key = strtolower($p['title'] . '|' . $p['organisation']); if (!isset($seen[$key])) { $seen[$key] = true; $unique[] = $p; } }
        return array_slice($unique, 0, 5);
    }

    private function extractPreviousRolesFromText(string $text): array
    {
        $roles = [];
        $titles = ['President','Vice President','Minister','Governor','Deputy Governor','Senator','Commissioner','Director General','Director','Chairman','CEO','Ambassador','Speaker'];
        $past = ['former','ex-','previously','served as','was the','was a'];
        foreach ($past as $ind) {
            foreach ($titles as $t) {
                if (preg_match('/' . preg_quote($ind, '/') . '\s+(?:\w+\s+){0,3}' . preg_quote($t, '/') . '(?:\s+of\s+([A-Z][a-zA-Z\s]+?))?(?:\s+(?:from\s+)?(\d{4})(?:\s*[-–to]+\s*(\d{4}))?)?(?:[,.\n]|$)/i', $text, $m)) {
                    $roles[] = ['title' => $t, 'organisation' => isset($m[1]) ? trim($m[1]) : '', 'from' => $m[2] ?? '', 'to' => $m[3] ?? ''];
                }
            }
        }
        $seen = []; $unique = [];
        foreach ($roles as $p) { $key = strtolower($p['title'] . '|' . $p['organisation']); if (!isset($seen[$key])) { $seen[$key] = true; $unique[] = $p; } }
        return array_slice($unique, 0, 5);
    }

    private function extractPartyFromText(string $text): string
    {
        $parties = ['APC','PDP','Labour Party','APGA','NNPP','SDP','ADC','YPP','NPP','NDC','UDA','ODM','ANC','DA','EFF','Republican','Democrat'];
        foreach ($parties as $p) { if (preg_match('/\b' . preg_quote($p, '/') . '\b/i', $text)) return $p; }
        return '';
    }

    private function extractReligionFromText(string $text): string
    {
        $map = ['Islam' => ['muslim','islam','islamic','alhaji','sheikh','mallam'], 'Christianity' => ['christian','church','pastor','bishop','reverend','catholic'], 'Judaism' => ['jewish','rabbi'], 'Hinduism' => ['hindu']];
        foreach ($map as $rel => $kws) { foreach ($kws as $kw) { if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $text)) return $rel; } }
        return '';
    }

    private function buildSummary(string $corpus, string $fullName): string
    {
        $clean = preg_replace('/https?:\/\/\S+/', ' ', $corpus);
        $clean = preg_replace('/\s+/', ' ', $clean);
        $sentences = preg_split('/(?<=[.!?])\s+/', $clean);
        $nameParts = array_filter(explode(' ', $fullName));
        $lastName = end($nameParts);
        $bio = array_filter($sentences, fn($s) => (stripos($s, $fullName) !== false || stripos($s, $lastName) !== false) && strlen(strip_tags($s)) > 30);
        $summary = implode(' ', array_slice(array_values($bio), 0, 3));
        return trim(substr($summary ?: substr($clean, 0, 300), 0, 500));
    }
}
