<?php

namespace App\Models;

class PricingConfig extends MongoModel
{
    protected $table = 'pricing_config';
    protected $casts = [
        'payload' => 'array',
    ];
}
