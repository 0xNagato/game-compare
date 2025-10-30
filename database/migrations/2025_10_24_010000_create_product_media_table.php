<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('external_id')->nullable();
            $table->string('media_type', 32)->default('image');
            $table->string('title')->nullable();
            $table->string('caption')->nullable();
            $table->string('url');
            $table->string('thumbnail_url')->nullable();
            $table->string('attribution')->nullable();
            $table->string('license')->nullable();
            $table->string('license_url')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'source', 'external_id']);
            $table->index(['product_id', 'source']);
            $table->index('media_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_media');
    }
};
