<?php

namespace Laraditz\Courier\Models;

use Illuminate\Database\Eloquent\Model;

class CourierWebhookLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'courier_webhook_logs';

    protected $guarded = [];

    protected $casts = [
        'headers' => 'array',
        'payload' => 'array',
        'verified' => 'boolean',
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

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
