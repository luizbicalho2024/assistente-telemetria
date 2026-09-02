<?php

namespace App\Models;

class ActivityLog extends MongoModel
{
    protected $table = 'activity_logs';
    protected $casts = [
        'details' => 'array',
        'timestamp' => 'datetime',
    ];
}
