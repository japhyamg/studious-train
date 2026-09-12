<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Storage;

/**
 * Archival of the append-only audit trail (CBN 5.9(a)(iii)).
 *
 * The activity log is never deleted. This command exports entries older than
 * the configured retention window to a dated CSV archive on local storage and
 * writes an audit entry recording the archival run, so end-to-end traceability
 * is preserved without unbounded growth of the live table.
 */
class ArchiveAuditLogsCommand extends Command
{
    protected $signature = 'audit:archive {--days= : Override the retention window}';

    protected $description = 'Archive audit log entries older than the retention window to CSV';

    public function handle(): int
    {
        $retentionDays = (int) ($this->option('days')
            ?: settings('audit_retention_days', config('governance.audit.retention_days', 1825)));

        $cutoff = Carbon::now()->subDays($retentionDays);

        $activities = Activity::with('causer')
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->limit(100000)
            ->get();

        if ($activities->isEmpty()) {
            $this->info("No audit entries older than {$cutoff->toDateString()} to archive.");
            return self::SUCCESS;
        }

        $directory = 'archives/audit/' . now()->format('Y/m');
        $filename = 'audit_archive_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $handle = fopen('php://temp', 'w');
        fputcsv($handle, ['Date', 'User', 'Event', 'Subject Type', 'Subject ID', 'Properties']);

        foreach ($activities as $activity) {
            fputcsv($handle, [
                $activity->created_at?->format('Y-m-d H:i:s'),
                $activity->causer?->name ?? 'System',
                $activity->description,
                class_basename($activity->subject_type ?? ''),
                $activity->subject_id ?? '',
                json_encode($activity->properties ?? []),
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('local')->put("{$directory}/{$filename}", $content);

        activity()->withProperties([
            'count' => $activities->count(),
            'retention_days' => $retentionDays,
            'cutoff' => $cutoff->toDateString(),
            'file' => "{$directory}/{$filename}",
        ])->log('Audit trail archived');

        $this->info("Archived {$activities->count()} audit entries to {$directory}/{$filename}.");
        return self::SUCCESS;
    }
}
