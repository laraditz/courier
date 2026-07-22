<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 100)->index();
            $table->string('reference', 191)->nullable()->index();
            $table->string('waybill_number', 191)->nullable()->index();
            $table->json('headers')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('verified');
            $table->string('status', 20)->index();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_webhook_logs');
    }
};
