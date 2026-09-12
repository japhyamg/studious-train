<?php

namespace App\Services;

use App\Models\InternalWatchList;
use App\Models\NibssWatchList;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class WatchListImportService
{
    /**
     * Import internal watchlist entries from a CSV/XLSX file.
     *
     * Handles workbooks with multiple sheets (e.g. "Watchlisted BVN",
     * "Delisted BVN", "Deceased BVN"), skipping any title/meta rows that
     * precede the real column header.
     */
    public function importInternal(UploadedFile $file): array
    {
        $sheets = $this->readSheets($file);
        if ($sheets === []) {
            return ['created' => 0, 'skipped' => 0, 'errors' => 0, 'message' => 'File is empty.'];
        }

        $aliases = [
            'bvn'         => ['bvn', 'bankverificationnumber'],
            'nin'         => ['nin', 'nationalid', 'nationalidentitynumber'],
            'first_name'  => ['firstname', 'givenname', 'forename'],
            'middle_name' => ['middlename', 'othernames', 'othername'],
            'last_name'   => ['lastname', 'surname', 'familyname'],
            'account_no'  => ['accountno', 'accountnumber', 'account'],
        ];
        // Matches the NIBSS BVN workbook column order.
        $positional = ['bvn', 'nin', 'first_name', 'middle_name', 'last_name', 'account_no'];

        $stats = ['created' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($sheets as $sheet) {
            $status = $this->statusFromTitle($sheet['title']);
            $header = $this->findHeader($sheet['rows'], $aliases);
            $dataRows = $header ? array_slice($sheet['rows'], $header['index'] + 1) : $sheet['rows'];
            $map = $header['map'] ?? null;

            foreach ($dataRows as $row) {
                $data = $map ? $this->mapRow($row, $map) : $this->positionalRow($row, $positional);
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
                    InternalWatchList::create(array_merge(['status' => $status], $data));
                    $stats['created']++;
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    Log::warning('Internal watchlist import row error: ' . $e->getMessage());
                }
            }
        }

        return $stats;
    }

    /**
     * Import NIBSS watchlist entries from a CSV/XLSX file.
     */
    public function importNibss(UploadedFile $file): array
    {
        $sheets = $this->readSheets($file);
        if ($sheets === []) {
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

        $stats = ['created' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($sheets as $sheet) {
            $status = $this->statusFromTitle($sheet['title']);
            $header = $this->findHeader($sheet['rows'], $aliases);
            $dataRows = $header ? array_slice($sheet['rows'], $header['index'] + 1) : $sheet['rows'];
            $map = $header['map'] ?? null;

            foreach ($dataRows as $row) {
                $data = $map ? $this->mapRow($row, $map) : $this->positionalRow($row, $positional);
                $data = $this->clean($data);

                if (empty(array_filter($data))) {
                    $stats['skipped']++;
                    continue;
                }

                if (!$this->hasAny($data, ['bvn', 'first_name', 'last_name'])) {
                    $stats['skipped']++;
                    continue;
                }

                $data['watchlisted_date'] = $this->normalizeDate($data['watchlisted_date'] ?? null);
                if (empty($data['watchlisted_date'])) {
                    $data['watchlisted_date'] = now()->toDateString();
                }

                try {
                    NibssWatchList::create(array_merge(['status' => $status], $data));
                    $stats['created']++;
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    Log::warning('NIBSS watchlist import row error: ' . $e->getMessage());
                }
            }
        }

        return $stats;
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /**
     * Load every worksheet (with its title) as a plain 2D array of rows.
     */
    private function readSheets(UploadedFile $file): array
    {
        $path = $file->getRealPath() ?: $file->getPathname();

        $spreadsheet = IOFactory::load($path);
        $sheets = [];

        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            $sheets[] = [
                'title' => (string) $worksheet->getTitle(),
                'rows'  => $worksheet->toArray(null, true, true, false),
            ];
        }

        return $sheets;
    }

    /**
     * Map a sheet title to a watchlist status. Sheets like "Delisted BVN" and
     * "Deceased BVN" are imported with their matching status.
     */
    private function statusFromTitle(string $title): string
    {
        $title = strtolower($title);

        if (str_contains($title, 'delist')) return 'delisted';
        if (str_contains($title, 'deceas')) return 'deceased';

        return 'watchlisted';
    }

    /**
     * Normalise a watchlisted-date cell to MySQL's Y-m-d. Handles Excel serial
     * numbers and common date layouts (d/m/Y, m/d/Y, d-m-Y, Y-m-d, …). Returns
     * the original value when it cannot be parsed.
     */
    private function normalizeDate(?string $value): ?string
    {
        if ($value === null) return null;

        $value = trim($value);
        if ($value === '') return null;

        // Excel serial date (days since 1899-12-30) when the cell is unformatted.
        if (is_numeric($value) && (float) $value > 1 && (float) $value < 100000) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y', 'd.m.Y', 'Y/m/d'] as $format) {
            try {
                return \Carbon\Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable $e) {
                // try the next format
            }
        }

        return $value;
    }

    /**
     * Scan the leading rows of a sheet for the column header, so title/meta
     * rows above it are skipped. Returns ['index' => int, 'map' => array] or
     * null when no header is recognised (caller falls back to positional).
     */
    private function findHeader(array $rows, array $aliases, int $maxScan = 10): ?array
    {
        foreach ($rows as $index => $row) {
            if ($index >= $maxScan) break;

            $map = $this->detectHeader($row, $aliases);
            if (count($map) >= 2) {
                return ['index' => $index, 'map' => $map];
            }
        }

        return null;
    }

    /**
     * Detect a header row and return a map of column index → field name.
     */
    private function detectHeader(array $row, array $aliases): array
    {
        $map = [];

        foreach ($row as $colIndex => $cell) {
            $norm = $this->normalize((string) $cell);
            if ($norm === '') continue;

            foreach ($aliases as $field => $names) {
                if (in_array($norm, $names, true)) {
                    $map[$colIndex] = $field;
                    break;
                }
            }
        }

        return $map;
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
