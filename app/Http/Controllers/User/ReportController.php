<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\ManagementInformationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    protected $service;

    public function __construct()
    {
        $this->service = new ManagementInformationService();
    }

    public function index(Request $request)
    {
        $data = $this->service->build($request->from, $request->to);

        return view('users.reports.mi', compact('data'));
    }

    public function export(Request $request)
    {
        $data = $this->service->build($request->from, $request->to);
        $format = strtolower($request->input('format', 'csv'));

        $rows = $this->flatten($data);

        if ($format === 'pdf') {
            if (!class_exists('\\Barryvdh\\DomPDF\\Facade\\Pdf')) {
                return back()->with('error', 'PDF export is unavailable.');
            }

            $html = '<h4>Management Information Report</h4>';
            $html .= '<p>Period: ' . ($data['period']['from'] ?? 'all time') . ' → ' . ($data['period']['to'] ?? 'now') . '</p>';
            $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:11px;">';
            $html .= '<thead><tr>' . implode('', array_map(fn($h) => '<th>' . e(Str::headline($h)) . '</th>', array_keys($rows[0]))) . '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>' . implode('', array_map(fn($h) => '<td>' . e((string) ($row[$h] ?? '')) . '</td>', array_keys($row))) . '</tr>';
            }
            $html .= '</tbody></table>';

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'landscape');
            return $pdf->download('mi_report_' . now()->format('Y-m-d_H-i-s') . '.pdf');
        }

        $filename = 'mi_report_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $handle = fopen('php://temp', 'w');
        fputcsv($handle, array_map(fn($h) => Str::headline($h), array_keys($rows[0])));
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        activity()->log('Management Information report exported (CSV)');

        return response($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Flatten the nested MI payload into a single flat table for export.
     */
    private function flatten(array $data): array
    {
        $rows = [];

        foreach ($data['customers'] as $k => $v) $rows[] = ['section' => 'Customers', 'metric' => $k, 'value' => $v];
        foreach ($data['cases'] as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $src => $cnt) $rows[] = ['section' => 'Cases by Source', 'metric' => $src, 'value' => $cnt];
            } else {
                $rows[] = ['section' => 'Cases', 'metric' => $k, 'value' => $v];
            }
        }
        foreach ($data['filings'] as $k => $v) $rows[] = ['section' => 'Filings', 'metric' => $k, 'value' => $v];
        foreach ($data['sla'] as $k => $v) $rows[] = ['section' => 'SLA', 'metric' => $k, 'value' => $v];
        foreach ($data['screening'] as $k => $v) $rows[] = ['section' => 'Screening', 'metric' => $k, 'value' => $v];
        foreach ($data['trend'] as $m) {
            $rows[] = ['section' => 'Trend', 'metric' => $m['label'] . ' STR', 'value' => $m['STR']];
            $rows[] = ['section' => 'Trend', 'metric' => $m['label'] . ' CTR', 'value' => $m['CTR']];
        }

        return $rows;
    }
}
