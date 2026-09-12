<?php

namespace App\Services;

use App\Models\ScreeningResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Traits\ExtractsEntityData;

class ScreeningService
{
    use ExtractsEntityData;

    public function screen(array $params, ?int $customerId = null): ScreeningResult
    {
        try {
            $context = $this->buildContext($params, $customerId);
            [$pepResult, $sanctionResult, $mediaResult] = $this->runAllChecks($context);
            $riskLevel = $this->computeRisk($pepResult, $sanctionResult, $mediaResult);

            return ScreeningResult::updateOrCreate(
                [
                    'first_name' => $context['first_name'],
                    'middle_name' => $context['middle_name'],
                    'last_name' => $context['last_name'],
                    'fullname' => $context['full_name'],
                    'slug' => $context['slug'],
                    'customer_id' => $context['customer_id'],
                ],
                [
                    'search_context' => $context,
                    'pep_status' => $pepResult['status'],
                    'pep_matches' => $pepResult['matches'],
                    'pep_meta' => $pepResult,
                    'sanction_status' => $sanctionResult['status'],
                    'sanction_matches' => $sanctionResult['matches'],
                    'adverse_media_status' => $mediaResult['status'],
                    'adverse_media_articles' => $mediaResult['articles'],
                    'risk_level' => $riskLevel,
                    'pep_detected' => $pepResult['status'] === 'MATCH',
                    'sanctions_detected' => $sanctionResult['status'] === 'LISTED',
                    'adverse_media_detected' => $mediaResult['status'] === 'MATCH',
                    'screened_at' => now(),
                    'screened_by' => auth()->user()?->id ?? null,
                ]
            );
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'screening_service' => 'Screening failed. ' . $th->getMessage() . ' Please try again later.',
            ]);
        }
    }

    private function buildContext(array $params, ?int $customerId): array
    {
        $first = strtolower(trim($params['first_name'] ?? ''));
        $middle = strtolower(trim($params['middle_name'] ?? ''));
        $last = strtolower(trim($params['last_name'] ?? ''));
        $fullName = trim(implode(' ', array_filter([$first, $middle, $last])));

        return [
            'first_name' => $first, 'middle_name' => $middle ?: null, 'last_name' => $last,
            'full_name' => $fullName, 'slug' => Str::snake($fullName),
            'entity_type' => $params['entity_type'] ?? 'individual',
            'date_of_birth' => !empty($params['date_of_birth']) ? $params['date_of_birth'] : null,
            'gender' => $params['gender'] ?? null,
            'country' => !empty($params['country']) ? $params['country'] : null,
            'rc_number' => $params['rc_number'] ?? null,
            'customer_id' => $customerId,
        ];
    }

    private function runAllChecks(array $context): array
    {
        return [$this->checkPep($context), $this->checkSanctions($context), $this->checkAdverseMedia($context)];
    }

    private function checkPep(array $context): array
    {
        $results = [];
        $pepKeywords = config('screening.pep_keywords', []);
        $apiKey = config('screening.searchapi_key');

        if (empty($apiKey)) {
            return ['status' => 'NO_MATCH', 'data' => $this->emptyEntityData(), 'matches' => []];
        }

        try {
            $query = $context['full_name'] . ' (' . implode(' OR ', array_slice($pepKeywords, 0, 5)) . ')';
            $response = Http::get('https://www.searchapi.io/api/v1/search', [
                'engine' => 'google', 'q' => $query, 'api_key' => $apiKey,
                'gl' => $this->countryToGl($context['country']),
            ]);

            if (!$response->successful()) {
                return ['status' => 'NO_MATCH', 'data' => $this->emptyEntityData(), 'matches' => []];
            }

            $searchResults = $response->json('organic_results', []);
            $strongKeywords = ['president', 'minister', 'senator', 'governor'];

            foreach ($searchResults as $item) {
                $title = strtolower($item['title'] ?? '');
                $snippet = strtolower($item['snippet'] ?? '');
                $matchedKeywords = array_filter($pepKeywords, fn($kw) => str_contains($title, $kw) || str_contains($snippet, $kw));

                if (empty($matchedKeywords)) continue;

                $hasStrong = !empty(array_intersect($matchedKeywords, $strongKeywords));
                $results[] = [
                    'type' => 'PEP', 'name' => $context['full_name'],
                    'title' => $item['title'] ?? '', 'snippet' => $item['snippet'] ?? '',
                    'url' => $item['link'] ?? '', 'matched_keywords' => array_values($matchedKeywords),
                    'confidence' => match (true) { $hasStrong => 'HIGH', count($matchedKeywords) > 1 => 'MEDIUM', default => 'LOW' },
                    'source' => 'searchapi_google', 'found_at' => now()->toISOString(),
                ];
            }

            $entityData = count($results) > 0
                ? $this->extractFromSnippets($context['full_name'], $searchResults)
                : $this->emptyEntityData();

        } catch (\Exception $e) {
            Log::warning('PEP check failed', ['error' => $e->getMessage()]);
            $entityData = $this->emptyEntityData();
        }

        return ['status' => count($results) > 0 ? 'MATCH' : 'NO_MATCH', 'data' => $entityData ?? $this->emptyEntityData(), 'matches' => $results];
    }

    private function checkSanctions(array $context): array
    {
        $matches = $this->fetchOfacConsolidated($context);
        return ['status' => count($matches) > 0 ? 'LISTED' : 'NOT_LISTED', 'matches' => $matches];
    }

    private function fetchOfacConsolidated(array $context): array
    {
        $results = [];
        $name = $context['full_name'];
        $cachePath = storage_path('app/CONS_ENHANCED.XML');

        try {
            if (!file_exists(dirname($cachePath))) mkdir(dirname($cachePath), 0755, true);
            if (!file_exists($cachePath) || (time() - filemtime($cachePath)) > 86400) {
                $response = Http::timeout(60)->withoutVerifying()
                    ->get('https://sanctionslistservice.ofac.treas.gov/api/PublicationPreview/exports/CONS_ENHANCED.XML');
                if ($response->successful()) file_put_contents($cachePath, $response->body());
            }
            if (!file_exists($cachePath)) return [];

            $xml = @simplexml_load_file($cachePath, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml === false || !isset($xml->entities->entity)) return [];

            foreach ($xml->entities->entity as $entity) {
                $allNames = [];
                foreach ($entity->names->name ?? [] as $nameNode) {
                    foreach ($nameNode->translations->translation ?? [] as $translation) {
                        if ((string) $translation->isPrimary !== 'true') continue;
                        $fn = (string) $translation->formattedFullName;
                        if ($fn) $allNames[] = $fn;
                    }
                }

                $matched = collect($allNames)->contains(fn($n) => stripos($n, $name) !== false || stripos($name, $n) !== false);
                if (!$matched) continue;

                $results[] = [
                    'name' => $allNames[0] ?? $name,
                    'entity_type' => (string) ($entity->generalInfo->entityType ?? ''),
                    'source' => 'ofac_consolidated',
                    'source_ref' => (string) ($entity['id'] ?? ''),
                    'found_at' => now()->toISOString(),
                ];
            }
        } catch (\Exception $e) {
            Log::warning('OFAC check failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    private function checkAdverseMedia(array $context): array
    {
        $name = $context['full_name'];
        $country = $context['country'] ? strtoupper($this->countryToGl($context['country'])) : 'NG';
        $keywords = config('screening.adverse_media_keywords', []);

        $articles = array_merge(
            $this->screenGoogleRSS("{$name} fraud OR corruption OR crime", $country, $keywords),
            $this->screenGoogleRSS("{$name} arrest OR conviction OR sanction", $country, $keywords),
        );

        $seen = [];
        $articles = collect($articles)->filter(function ($a) use (&$seen) {
            if (in_array($a['url'], $seen)) return false;
            $seen[] = $a['url'];
            return true;
        })->values()->toArray();

        return ['status' => count($articles) > 0 ? 'MATCH' : 'NO_MATCH', 'articles' => $articles];
    }

    private function screenGoogleRSS(string $query, string $country, array $keywords = []): array
    {
        $results = [];
        try {
            $response = Http::timeout(15)->get('https://news.google.com/rss/search', [
                'q' => $query, 'hl' => 'en', 'gl' => $country, 'ceid' => "{$country}:en",
            ]);
            if (!$response->successful()) return [];
            $xml = @simplexml_load_string($response->body());
            if ($xml === false || !isset($xml->channel->item)) return [];

            foreach ($xml->channel->item as $item) {
                $title = (string) $item->title;
                $snippet = (string) $item->description;

                if (!empty($keywords)) {
                    $found = collect($keywords)->contains(fn($kw) => stripos($title, $kw) !== false || stripos($snippet, $kw) !== false);
                    if (!$found) continue;
                }

                $results[] = [
                    'type' => 'ADVERSE_MEDIA', 'title' => $title, 'snippet' => $snippet,
                    'url' => (string) $item->link,
                    'published_at' => date('Y-m-d', strtotime((string) $item->pubDate)),
                    'source' => 'google_news_' . strtolower($country),
                    'source_name' => $this->extractGoogleNewsSource($title),
                    'found_at' => now()->toISOString(),
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Google RSS check failed', ['error' => $e->getMessage()]);
        }
        return $results;
    }

    private function extractGoogleNewsSource(string $title): ?string
    {
        $parts = explode(' - ', $title);
        return count($parts) > 1 ? trim(end($parts)) : null;
    }

    private function computeRisk(array $pep, array $sanction, array $media): string
    {
        if ($sanction['status'] === 'LISTED') return 'CRITICAL';
        $score = 0;
        if ($pep['status'] === 'MATCH') $score += 2;
        if ($media['status'] === 'MATCH') $score += 1;
        return match (true) { $score >= 3 => 'HIGH', $score === 2 => 'MEDIUM', $score === 1 => 'LOW', default => 'LOW' };
    }

    private function countryToGl(?string $country): string
    {
        if (empty($country)) return 'us';
        return ['nigeria' => 'ng', 'ghana' => 'gh', 'kenya' => 'ke', 'south africa' => 'za', 'united states' => 'us', 'united kingdom' => 'gb'][strtolower($country)] ?? 'us';
    }
}
