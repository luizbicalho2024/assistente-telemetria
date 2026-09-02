<?php

namespace App\Models;

class Role extends MongoModel
{
    protected $table = 'roles';
    protected $casts = [
        'permissions' => 'array',
        'system' => 'boolean',
    ];
}
