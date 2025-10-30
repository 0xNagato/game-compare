<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_sync_states', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->unique();
            $table->timestamp('last_full_sync_at')->nullable();
            $table->timestamp('last_incremental_sync_at')->nullable();
            $table->string('vendor_token')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_sync_states');
    }
};
