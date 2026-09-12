<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RiskProfileExport implements WithMultipleSheets
{
    protected array $exportData;

    public function __construct(array $exportData)
    {
        $this->exportData = $exportData;
    }

    public function sheets(): array
    {
        $sheets = [];
        foreach ($this->exportData as $dataPoint => $columns) {
            $sheets[] = new RiskProfileSheetExport($dataPoint, $columns);
        }
        return $sheets;
    }
}
