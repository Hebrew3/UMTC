<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldworkSchedule extends Model
{
    protected $table = 'fieldwork_schedules';

    protected $fillable = [
        'title',
        'portfolio_id',
        'agent_id',
        'schedule_date',
        'status',
        'location',
        'notes',
        'start_time',
        'end_time',
        'created_by',
    ];

    protected $casts = [
        'schedule_date' => 'date',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class, 'portfolio_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }
}
