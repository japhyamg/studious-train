<?php

namespace App\Exports;

use App\Models\RiskLevelChange;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RiskLevelChangesExport implements FromQuery, WithHeadings, WithMapping
{
    public function query()
    {
        return RiskLevelChange::query()->with('customer')->latest();
    }

    public function headings(): array
    {
        return [
            'Date',
            'Customer',
            'Account Number',
            'From Level',
            'To Level',
            'Score',
            'Driver',
        ];
    }

    public function map($change): array
    {
        return [
            $change->created_at?->format('Y-m-d H:i:s'),
            $change->customer?->name ?? '—',
            $change->customer?->account_number ?? '—',
            $change->from_level ?? '—',
            $change->to_level ?? '—',
            $change->score,
            str_replace('_', ' ', ucfirst($change->driver ?? 'risk_rating')),
        ];
    }
}
