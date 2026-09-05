<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A daily, durable snapshot of the ai-memory statistics (see the migration and
 * docs/durable-history.md). It survives an ai-memory reset: this is the
 * long-term history behind the dashboard's evolution chart.
 */
class AiMemoryStatSnapshot extends Model
{
    protected $fillable = [
        'captured_on',
        'workspaces', 'projects', 'pages', 'sessions', 'observations',
        'embeddings', 'handoffs_open', 'proposals_pending',
        'raw_json',
    ];

    protected $casts = [
        'captured_on' => 'date',
        'raw_json' => 'array',
    ];
}
