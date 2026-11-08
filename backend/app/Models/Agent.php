<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Agent extends Model
{
    protected $fillable = [
        'agent_id',
        'agent_name',
        'contact_number',
        'assigned_cluster',
        'assigned_area',
        'status',
        'date_created',
    ];

    protected $casts = [
        'date_created' => 'date',
    ];

    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class, 'assigned_agent_id');
    }

    public function fieldworkSchedules(): HasMany
    {
        return $this->hasMany(FieldworkSchedule::class, 'agent_id');
    }

    public function seniorAgents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'senior_agent_assignments', 'agent_id', 'senior_agent_id')
            ->withTimestamps();
    }
}
