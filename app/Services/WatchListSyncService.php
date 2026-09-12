<?php

namespace App\Services;

use App\Models\WatchListEntry;
use App\Models\WatchListSyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Downloads, parses and persists sanction/watchlist sources (OFAC, UN
 * Consolidated, Nigerian sanctions) and writes an audit row per refresh.
 */
class WatchListSyncService
{
    /**
     * Refresh every enabled source.
     *
     * @return array<string, array>
     */
    public function syncAll(): array
    {
        $results = [];
        foreach (array_keys(config('sanctions.sources', [])) as $source) {
            $results[$source] = $this->sync($source);
        }
        return $results;
    }

    /**
     * Refresh a single source. Returns a summary array with 'status' one of
     * success | failed | skipped.
     */
    public function sync(string $source): array
    {
        $config = config("sanctions.sources.{$source}");

        if (!$config) {
            return ['source' => $source, 'status' => 'skipped', 'message' => 'Unknown source.', 'count' => 0];
        }

        if (empty($config['enabled']) || empty($config['url'])) {
            return ['source' => $source, 'status' => 'skipped', 'message' => 'Source is disabled or has no URL configured.', 'count' => 0];
        }

        try {
            $xml = $this->fetch($config['url']);
            if ($xml === null || trim($xml) === '') {
                $this->log($source, 'failed', 0, null, 'Download failed or returned an empty response.');
                return ['source' => $source, 'status' => 'failed', 'message' => 'Download failed or returned an empty response.', 'count' => 0];
            }

            $entries = $this->parse($source, $xml);
            $version = $this->extractVersion($source, $xml);

            DB::transaction(function () use ($source, $entries) {
                WatchListEntry::where('source', $source)->delete();
                foreach (array_chunk($entries, 500) as $chunk) {
                    WatchListEntry::insert($chunk);
                }
            });

            $count = count($entries);
            $this->log($source, 'success', $count, $version, null);

            return ['source' => $source, 'status' => 'success', 'message' => "{$count} entries synced.", 'count' => $count, 'version' => $version];
        } catch (\Throwable $e) {
            Log::error("Sanctions sync failed for {$source}: " . $e->getMessage());
            $this->log($source, 'failed', 0, null, $e->getMessage());
            return ['source' => $source, 'status' => 'failed', 'message' => $e->getMessage(), 'count' => 0];
        }
    }

    // ─── Download / parse ─────────────────────────────────────────

    private function fetch(string $url): ?string
    {
        $response = Http::timeout(120)->withoutVerifying()->get($url);
        return $response->successful() ? $response->body() : null;
    }

    private function parse(string $source, string $xml): array
    {
        return match (config("sanctions.sources.{$source}.parser")) {
            'ofac' => $this->parseOfac($xml, $source),
            'un' => $this->parseUn($xml, $source),
            default => [],
        };
    }

    private function parseOfac(string $xml, string $source): array
    {
        $doc = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($doc === false || !isset($doc->entities->entity)) return [];

        $entries = [];
        $now = now();

        foreach ($doc->entities->entity as $entity) {
            $names = [];
            foreach ($entity->names->name ?? [] as $nameNode) {
                foreach ($nameNode->translations->translation ?? [] as $translation) {
                    if ((string) $translation->isPrimary !== 'true') continue;
                    $full = trim((string) $translation->formattedFullName);
                    if ($full !== '') $names[] = $full;
                }
            }
            if (!$names) continue;

            $primary = array_shift($names);

            $entries[] = $this->entry($primary, $names, [
                'source' => $source,
                'entity_type' => strtolower((string) ($entity->generalInfo->entityType ?? 'individual')),
                'reference' => (string) ($entity['id'] ?? uniqid('ofac-', true)),
                'program' => (string) ($entity->programs->program ?? ''),
                'listed_on' => null,
            ], $now);
        }

        return $entries;
    }

    private function parseUn(string $xml, string $source): array
    {
        $doc = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($doc === false) return [];

        $entries = [];
        $now = now();

        $rows = array_merge(
            $doc->xpath('//INDIVIDUAL') ?: [],
            $doc->xpath('//ENTITY') ?: []
        );

        foreach ($rows as $row) {
            $isEntity = $row->getName() === 'ENTITY';

            $full = trim(implode(' ', array_filter([
                (string) ($row->FIRST_NAME ?? ''),
                (string) ($row->SECOND_NAME ?? ''),
                (string) ($row->THIRD_NAME ?? ''),
                (string) ($row->FOURTH_NAME ?? ''),
            ])));
            if ($full === '') continue;

            $aliases = [];
            foreach (['INDIVIDUAL_ALIAS', 'ENTITY_ALIAS'] as $tag) {
                foreach ($row->{$tag} ?? [] as $alias) {
                    $a = trim((string) ($alias->ALIAS_NAME ?? ''));
                    if ($a !== '') $aliases[] = $a;
                }
            }

            $dob = (string) ($row->INDIVIDUAL_DATE_OF_BIRTH ?? '');
            $placeOfBirth = (string) ($row->INDIVIDUAL_PLACE_OF_BIRTH ?? '');

            $entries[] = $this->entry($full, $aliases, [
                'source' => $source,
                'entity_type' => $isEntity ? 'entity' : 'individual',
                'reference' => (string) ($row->REFERENCE_NUMBER ?? uniqid('un-', true)),
                'program' => (string) ($row->UN_LIST_TYPE ?? ''),
                'listed_on' => $this->toDate((string) ($row->LISTED_ON ?? '')),
                'date_of_birth' => $this->toDate($dob),
                'country' => $placeOfBirth !== '' ? $placeOfBirth : null,
            ], $now);
        }

        return $entries;
    }

    /**
     * Build one insertable entry row, splitting the full name into parts.
     */
    private function entry(string $fullName, array $aliases, array $meta, $now): array
    {
        [$first, $middle, $last] = $this->splitName($fullName);

        return [
            'source' => $meta['source'],
            'entity_type' => $meta['entity_type'] ?? 'individual',
            'first_name' => $first ?: null,
            'middle_name' => $middle ?: null,
            'last_name' => $last ?: ($first ?: $fullName),
            'full_name' => $fullName,
            'aliases' => json_encode(array_values(array_unique(array_filter($aliases)))),
            'country' => $meta['country'] ?? null,
            'date_of_birth' => $meta['date_of_birth'] ?? null,
            'reference' => (string) ($meta['reference'] ?? ''),
            'program' => (string) ($meta['program'] ?? ''),
            'listed_on' => $meta['listed_on'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Best-effort split of "SURNAME, Given Middle" or "Given Middle Surname".
     *
     * @return array{0: string, 1: ?string, 2: string}
     */
    private function splitName(string $fullName): array
    {
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName));

        if (str_contains($fullName, ',')) {
            [$last, $given] = array_map('trim', explode(',', $fullName, 2));
            $givenParts = array_values(array_filter(preg_split('/\s+/', $given)));
            $first = array_shift($givenParts) ?? '';
            return [$first, $givenParts ? implode(' ', $givenParts) : null, $last];
        }

        $parts = array_values(array_filter(preg_split('/\s+/', $fullName)));
        $first = array_shift($parts) ?? '';
        $last = $parts ? array_pop($parts) : '';
        return [$first, $parts ? implode(' ', $parts) : null, $last];
    }

    private function extractVersion(string $source, string $xml): ?string
    {
        $doc = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($doc === false) return null;

        if ($source === 'ofac_consolidated') {
            $nodes = $doc->xpath('//Publish_Date');
            $value = $nodes[0] ?? null;
            return $value ? trim((string) $value) : null;
        }

        // UN consolidated list exposes dateGenerated on the root element.
        $value = $doc['dateGenerated'] ?? null;
        return $value ? trim((string) $value) : null;
    }

    private function toDate(?string $value): ?string
    {
        if (!$value) return null;
        $value = trim($value);
        if ($value === '') return null;
        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function log(string $source, string $status, int $count, ?string $version, ?string $message): void
    {
        WatchListSyncLog::create([
            'source' => $source,
            'status' => $status,
            'record_count' => $count,
            'version' => $version,
            'last_updated' => $this->toDate($version),
            'message' => $message,
            'synced_at' => now(),
        ]);

        activity()->log(sprintf(
            'Sanctions list sync [%s]: %s (%d records)%s',
            $source, $status, $count, $message ? " — {$message}" : ''
        ));
    }
}
