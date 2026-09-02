<?php

namespace App\Models;

class BillingHistory extends MongoModel
{
    protected $table = 'billing_history';
    protected $casts = [
        'itens_detalhados' => 'array',
        'data_geracao' => 'datetime',
    ];
}
