<?php

namespace App\Models;

class BillingRunItem extends MongoModel
{
    protected $table = 'billing_run_items';
    protected $casts = [
        'data_ativacao' => 'datetime',
        'data_desativacao' => 'datetime',
    ];
}
