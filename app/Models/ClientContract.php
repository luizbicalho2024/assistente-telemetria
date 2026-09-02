<?php

namespace App\Models;

class ClientContract extends MongoModel
{
    protected $table = 'client_contracts';
    protected $casts = [
        'prices' => 'array',
        'precos_por_tipo' => 'array',
    ];
}
