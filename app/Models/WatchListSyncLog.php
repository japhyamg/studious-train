<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WatchListSyncLog extends Model
{
    protected $table = 'watchlist_sync_logs';

    protected $fillable = [
        'source', 'status', 'record_count', 'version', 'last_updated',
        'message', 'synced_at',
    ];

    protected $casts = [
        'last_updated' => 'date',
        'synced_at' => 'datetime',
    ];

    public function getSourceLabelAttribute(): string
    {
        $sources = config('sanctions.sources', []);
        return $sources[$this->source]['name'] ?? $this->source;
    }
}
