<?php

namespace App\Models;

class BillingMonthClosure extends MongoModel
{
    protected $table = 'billing_month_closures';
    protected $casts = [
        'closed_at' => 'datetime',
    ];
}
