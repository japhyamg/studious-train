<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RiskProfileSheetExport implements FromArray, WithTitle, WithHeadings, WithStyles
{
    protected string $dataPoint;
    protected array $columns;

    public function __construct(string $dataPoint, array $columns)
    {
        $this->dataPoint = $dataPoint;
        $this->columns = $columns;
    }

    public function title(): string
    {
        return ucwords(str_replace('_', ' ', $this->dataPoint));
    }

    public function headings(): array
    {
        return ['Type', 'Value'];
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->columns as $col) {
            $rows[] = [
                $col['type'] ?? '',
                $col['value'] ?? 0,
            ];
        }
        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E6F2EC'],
                ],
            ],
        ];
    }
}
