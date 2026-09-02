<?php

namespace App\Models;

class BillingTerminalSnapshot extends MongoModel
{
    protected $table = 'billing_terminal_snapshots';
    protected $casts = [
        'data_ativacao' => 'datetime',
        'data_desativacao' => 'datetime',
    ];
}
