<?php

namespace App\Imports;

use App\Models\RiskProfileTemplateData;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithConditionalSheets;
use Illuminate\Http\UploadedFile;

class RiskProfileTemplateImport implements WithMultipleSheets
{
    protected int $profileId;
    protected UploadedFile $file;

    public function __construct(int $profileId, UploadedFile $file)
    {
        $this->profileId = $profileId;
        $this->file = $file;
    }

    public function sheets(): array
    {
        // Dynamically detect sheet names from the uploaded file
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->file->getPathname());
        $sheetNames = $reader->getSheetNames();

        $sheets = [];
        foreach ($sheetNames as $sheetName) {
            $sheets[$sheetName] = new RiskProfileSheetImport($this->profileId, $sheetName);
        }

        return $sheets;
    }
}
