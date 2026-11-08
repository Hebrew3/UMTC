<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClusterReference extends Model
{
    protected $fillable = [
        'cluster',
        'location',
        'province',
        'region',
        'coverage',
        'assigned_agent_name',
        'zip',
        'import_id',
    ];
}
