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
}
