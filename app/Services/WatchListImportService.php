<?php

namespace App\Services;

use App\Imports\SheetToArray;
use App\Models\InternalWatchList;
use App\Models\NibssWatchList;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class WatchListImportService
{
    /**
     * Import internal watchlist entries from a CSV/XLSX file.
     *
     * Header-aware with positional fallback.
     */
    public function importInternal(UploadedFile $file): array
    {
        $rows = $this->readRows($file);
        if (empty($rows)) {
            return ['created' => 0, 'skipped' => 0, 'errors' => 0, 'message' => 'File is empty.'];
        }

        $aliases = [
            'first_name'  => ['firstname', 'givenname', 'forename'],
            'middle_name' => ['middlename', 'othernames', 'othername'],
            'last_name'   => ['lastname', 'surname', 'familyname'],
            'account_no'  => ['accountno', 'accountnumber', 'account'],
            'bvn'         => ['bvn', 'bankverificationnumber'],
            'nin'         => ['nin', 'nationalid', 'nationalidentitynumber'],
        ];
        $positional = ['first_name', 'middle_name', 'last_name', 'account_no', 'bvn', 'nin'];

        $headerMap = $this->detectHeader($rows[0] ?? [], $aliases);
        $dataRows  = $headerMap ? array_slice($rows, 1) : $rows;

        $stats = ['created' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($dataRows as $row) {
            $data = $headerMap
                ? $this->mapRow($row, $headerMap)
                : $this->positionalRow($row, $positional);

            $data = $this->clean($data);

            if (empty(array_filter($data))) {
                $stats['skipped']++;
                continue;
            }

            // Require at least a name or an identifier.
            if (!$this->hasAny($data, ['first_name', 'last_name', 'account_no', 'bvn', 'nin'])) {
                $stats['skipped']++;
                continue;
            }

            try {
                InternalWatchList::create(array_merge(['status' => 'watchlisted'], $data));
                $stats['created']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::warning('Internal watchlist import row error: ' . $e->getMessage());
            }
        }

        return $stats;
    }

    /**
     * Import NIBSS watchlist entries from a CSV/XLSX file.
     */
    public function importNibss(UploadedFile $file): array
    {
        $rows = $this->readRows($file);
        if (empty($rows)) {
            return ['created' => 0, 'skipped' => 0, 'errors' => 0, 'message' => 'File is empty.'];
        }

        $aliases = [
            'bvn'              => ['bvn', 'bankverificationnumber'],
            'first_name'       => ['firstname', 'givenname', 'forename'],
            'middle_name'      => ['middlename', 'othernames', 'othername'],
            'last_name'        => ['lastname', 'surname', 'familyname'],
            'category'         => ['category'],
            'reason'           => ['reason', 'remarks', 'narration', 'description'],
            'requesting_bank'  => ['requestingbank', 'bank', 'institution', 'bankname'],
            'watchlisted_date' => ['watchlisteddate', 'date', 'datewatchlisted'],
        ];
        $positional = ['bvn', 'first_name', 'middle_name', 'last_name', 'category', 'reason', 'requesting_bank', 'watchlisted_date'];

        $headerMap = $this->detectHeader($rows[0] ?? [], $aliases);
        $dataRows  = $headerMap ? array_slice($rows, 1) : $rows;

        $stats = ['created' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($dataRows as $row) {
            $data = $headerMap
                ? $this->mapRow($row, $headerMap)
                : $this->positionalRow($row, $positional);

            $data = $this->clean($data);

            if (empty(array_filter($data))) {
                $stats['skipped']++;
                continue;
            }

            if (!$this->hasAny($data, ['bvn', 'first_name', 'last_name'])) {
                $stats['skipped']++;
                continue;
            }

            if (empty($data['watchlisted_date'])) {
                $data['watchlisted_date'] = now()->toDateString();
            }

            try {
                NibssWatchList::create(array_merge(['status' => 'watchlisted'], $data));
                $stats['created']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::warning('NIBSS watchlist import row error: ' . $e->getMessage());
            }
        }

        return $stats;
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function readRows(UploadedFile $file): array
    {
        $sheets = Excel::toArray(new SheetToArray(), $file);
        return $sheets[0] ?? [];
    }

    /**
     * Detect a header row and return a map of column index → field name,
     * or null when no header is recognised (caller falls back to positional).
     */
    private function detectHeader(array $firstRow, array $aliases): ?array
    {
        $map = [];
        $found = false;

        foreach ($firstRow as $colIndex => $cell) {
            $norm = $this->normalize((string) $cell);
            if ($norm === '') continue;

            foreach ($aliases as $field => $names) {
                if (in_array($norm, $names, true)) {
                    $map[$colIndex] = $field;
                    $found = true;
                    break;
                }
            }
        }

        return $found ? $map : null;
    }

    private function mapRow(array $row, array $headerMap): array
    {
        $data = [];
        foreach ($headerMap as $colIndex => $field) {
            $data[$field] = $this->cell($row, $colIndex);
        }
        return $data;
    }

    private function positionalRow(array $row, array $positional): array
    {
        $data = [];
        foreach ($positional as $i => $field) {
            $data[$field] = $this->cell($row, $i);
        }
        return $data;
    }

    private function cell(array $row, int $index): ?string
    {
        $value = $row[$index] ?? null;
        if ($value === null) return null;
        return trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    private function clean(array $data): array
    {
        return array_map(fn($v) => $v === null ? null : trim((string) $v), $data);
    }

    private function hasAny(array $data, array $fields): bool
    {
        foreach ($fields as $field) {
            if (!empty($data[$field] ?? null)) return true;
        }
        return false;
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim($value))) ?? '';
    }
}
