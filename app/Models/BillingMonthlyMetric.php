<?php

namespace App\Models;

class BillingMonthlyMetric extends MongoModel
{
    protected $table = 'billing_monthly_metrics';
    protected $casts = [
        'updated_at' => 'datetime',
    ];
}
