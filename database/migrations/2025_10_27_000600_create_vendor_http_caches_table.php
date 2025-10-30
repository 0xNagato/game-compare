<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_http_caches', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('endpoint');
            $table->string('etag')->nullable();
            $table->timestamp('last_modified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'endpoint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_http_caches');
    }
};
