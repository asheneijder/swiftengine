<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class SwiftMessage extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'swift_messages';

    // Allow mass assignment for all parsed columns
    protected $guarded = [];

    // Optional: If you want dates to be proper Mongo Dates
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}