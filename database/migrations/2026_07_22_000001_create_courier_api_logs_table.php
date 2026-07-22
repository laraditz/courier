<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 100)->index();
            $table->string('action', 100);
            $table->string('reference', 191)->nullable()->index();
            $table->string('waybill_number', 191)->nullable()->index();
            $table->string('method', 10);
            $table->text('url');
            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('response_headers')->nullable();
            $table->json('response_body')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->boolean('successful')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_api_logs');
    }
};
