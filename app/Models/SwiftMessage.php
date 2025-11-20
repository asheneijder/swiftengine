<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class SwiftMessage extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'swift_messages';

    protected $fillable = [
        'mt_type',
        'sender',
        'receiver',
        'message_date', // Stored as Y-m-d
        'parsed_data',  // The full array of fields
        'raw_content',
        'source_file'
    ];

    protected $casts = [
        'parsed_data' => 'array',
    ];
}