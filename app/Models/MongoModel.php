<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

abstract class MongoModel extends Model
{
    protected $connection = 'mongodb';
    public $timestamps = true;
    protected $guarded = [];
}
