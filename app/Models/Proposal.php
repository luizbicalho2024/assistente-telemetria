<?php

namespace App\Models;

class Proposal extends MongoModel
{
    protected $table = 'proposals';
    protected $casts = [
        'items' => 'array',
        'financial_snapshot' => 'array',
        'data_geracao' => 'datetime',
        'approved_at' => 'datetime',
    ];
}
