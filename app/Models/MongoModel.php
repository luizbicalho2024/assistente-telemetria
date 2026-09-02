<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

abstract class MongoModel extends Model
{
    protected $connection = 'mongodb';
    public $timestamps = true;

    /**
     * Nunca permita alteração massiva do identificador Mongo.
     * Modelos sensíveis devem declarar $fillable próprio.
     */
    protected $guarded = ['_id', 'id'];
}
