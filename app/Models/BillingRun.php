<?php

namespace App\Models;

class BillingRun extends MongoModel
{
    protected $table = 'billing_runs';
    protected $casts = [
        'data_geracao' => 'datetime',
    ];
}
