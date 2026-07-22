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

    public function scopeForReference($query, string $reference)
    {
        return $query->where('reference', $reference);
    }

    public function scopeForDriver($query, string $driver)
    {
        return $query->where('driver', $driver);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('successful', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('successful', false);
    }
}
