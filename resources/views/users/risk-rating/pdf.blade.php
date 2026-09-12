<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>MoniSurv Risk Rating Report</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #1a1e2c; margin: 20px; }
        h1 { font-size: 18px; color: #0d3b26; margin-bottom: 5px; }
        h2 { font-size: 14px; color: #145234; margin: 20px 0 10px; }
        .subtitle { color: #8896a4; font-size: 11px; margin-bottom: 20px; }
        .summary { display: flex; margin-bottom: 20px; }
        .summary-item { background: #f0f8f4; border: 1px solid #e6f2ec; padding: 10px 15px; margin-right: 10px; border-radius: 6px; text-align: center; }
        .summary-label { font-size: 9px; color: #8896a4; text-transform: uppercase; letter-spacing: 0.5px; font-weight: bold; }
        .summary-value { font-size: 20px; font-weight: 800; color: #145234; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f0f8f4; color: #145234; font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; padding: 8px 10px; border-bottom: 2px solid #e6f2ec; text-align: left; }
        td { padding: 7px 10px; border-bottom: 1px solid #eef1f6; font-size: 10.5px; }
        tr:nth-child(even) { background: #faf8f2; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 9px; font-weight: 600; }
        .badge-low { background: #f0fdf4; color: #16a34a; }
        .badge-medium { background: #fef3c7; color: #b45309; }
        .badge-high { background: #fef2f2; color: #dc2626; }
        .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #e4e8f0; font-size: 9px; color: #8896a4; text-align: center; }
    </style>
</head>
<body>
    <h1>MoniSurv — Risk Rating Report</h1>
    <div class="subtitle">Generated on {{ now()->format('M d, Y H:i') }} | Rating ID: {{ $riskrating->id }}</div>

    <h2>Summary</h2>
    <table style="width:auto;margin-bottom:20px">
        <tr>
            @foreach($chartData as $level => $count)
            <td style="text-align:center;padding:10px 20px;background:#f0f8f4;border:1px solid #e6f2ec;border-radius:6px">
                <div style="font-size:9px;color:#8896a4;text-transform:uppercase;letter-spacing:0.5px;font-weight:bold">{{ $level }}</div>
                <div style="font-size:22px;font-weight:800;color:#145234">{{ $count }}</div>
            </td>
            @endforeach
        </tr>
    </table>

    <h2>Customer Results ({{ $results->count() }})</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Customer Name</th>
                <th>Account No</th>
                <th>Type</th>
                <th>State</th>
                <th>PEP</th>
                <th>Score</th>
                <th>Risk Level</th>
                <th>Diligence</th>
            </tr>
        </thead>
        <tbody>
            @foreach($results as $idx => $r)
            @php $level = mapScoreToRiskLevel($r->score); @endphp
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>{{ $r->customer?->name ?? '—' }}</td>
                <td>{{ $r->customer?->account_number ?? '—' }}</td>
                <td>{{ ucfirst($r->customer?->customer_type ?? '—') }}</td>
                <td>{{ ucfirst($r->customer?->state_of_residence ?? '—') }}</td>
                <td>{{ ($r->customer?->isPep === 'yes' || $r->customer?->isPep == 1) ? 'Yes' : 'No' }}</td>
                <td style="font-weight:bold">{{ $r->score }}</td>
                <td>
                    @if($level)
                    <span class="badge badge-{{ strtolower($level->label) }}">{{ $level->label }}</span>
                    @else
                    N/A
                    @endif
                </td>
                <td>{{ $level?->diligence_type ?? 'CDD' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        MoniSurv AML/CFT Transaction Surveillance & Monitoring Platform — Confidential
    </div>
</body>
</html>
