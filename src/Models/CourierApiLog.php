<?php

namespace Laraditz\Courier\Models;

use Illuminate\Database\Eloquent\Model;

class CourierApiLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'courier_api_logs';

    protected $guarded = [];

    protected $casts = [
        'request_headers' => 'array',
        'request_body' => 'array',
        'response_headers' => 'array',
        'response_body' => 'array',
        'status_code' => 'integer',
        'duration_ms' => 'integer',
        'successful' => 'boolean',
        'created_at' => 'datetime',
    ];
}
