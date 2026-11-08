<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Portfolio extends Model
{
    protected $fillable = [
        'account_number',
        'account_name',
        'ob',
        'days_handled',
        'exp_date',
        'product_line',
        'client',
        'service_type',
        'classification',
        'address',
        'contact',
        'latest_status',
        'cluster',
        'region',
        'province',
        'city',
        'barangay',
        'verification_status',
        'assigned_agent_id',
        'is_active',
        'last_updated_at',
        'on_hold',
        'has_task',
        'date_endorsed',
        'expired',
        'monthly_amort',
        'dpd',
        'particulars',
        'upcoming_task',
        'imported_area',
        'source_agent_name',
        'location',
        'assignment_status',
        'match_notes',
        'is_current',
        'last_seen_import_id',
    ];

    protected $casts = [
        'exp_date' => 'date',
        'last_updated_at' => 'datetime',
        'is_active' => 'boolean',
        'date_endorsed' => 'date',
        'is_current' => 'boolean',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }
}
