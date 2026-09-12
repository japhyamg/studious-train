<?php

namespace App\Exports;

use App\Models\RiskRatingCustomerResult;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RiskRatingResultsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected int $ratingId;

    public function __construct(int $ratingId)
    {
        $this->ratingId = $ratingId;
    }

    public function title(): string
    {
        return 'Risk Rating Results';
    }

    public function query()
    {
        return RiskRatingCustomerResult::query()
            ->where('risk_rating_id', $this->ratingId)
            ->with('customer');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Customer Name',
            'Account Number',
            'BVN',
            'Customer Type',
            'State',
            'LGA',
            'PEP Status',
            'Risk Score',
            'Risk Level',
            'Due Diligence',
            'Rated At',
        ];
    }

    public function map($result): array
    {
        $level = mapScoreToRiskLevel($result->score);

        return [
            $result->id,
            $result->customer?->name ?? '—',
            $result->customer?->account_number ?? '—',
            $result->customer?->bvn ?? '—',
            ucfirst($result->customer?->customer_type ?? '—'),
            ucfirst($result->customer?->state_of_residence ?? '—'),
            $result->customer?->local_govt_area ?? '—',
            ($result->customer?->isPep === 'yes' || $result->customer?->isPep == 1) ? 'Yes' : 'No',
            $result->score,
            $level?->label ?? 'N/A',
            $level?->diligence_type ?? ($level && strtolower($level->label) === 'low' ? 'CDD' : 'EDD'),
            $result->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E6F2EC'],
                ],
            ],
        ];
    }
}
