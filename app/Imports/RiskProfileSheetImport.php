<?php

namespace App\Imports;

use App\Models\RiskProfileTemplateData;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Str;

class RiskProfileSheetImport implements ToModel, WithHeadingRow
{
    protected int $profileId;
    protected string $dataPoint;

    public function __construct(int $profileId, string $sheetName)
    {
        $this->profileId = $profileId;
        // Convert sheet title back to snake_case column name
        $this->dataPoint = Str::snake($sheetName);
    }

    public function model(array $row)
    {
        $type = $row['type'] ?? null;
        $value = $row['value'] ?? 0;

        if (empty($type)) return null;

        return RiskProfileTemplateData::updateOrCreate(
            [
                'risk_profile_id' => $this->profileId,
                'data_point' => $this->dataPoint,
                'type' => $type,
            ],
            [
                'value' => (float) $value,
            ]
        );
    }
}
