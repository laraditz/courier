<?php

use Illuminate\Support\Facades\Route;
use Laraditz\Courier\Http\Controllers\WebhookController;

Route::post('courier/webhook/{driver}', [WebhookController::class, 'handle'])
    ->name('courier.webhook')
    ->middleware('throttle:60,1');
