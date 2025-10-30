<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('uid', 64)->nullable()->after('id');
            $table->text('synopsis')->nullable()->after('category');
            $table->string('primary_platform_family', 32)->nullable()->after('platform');
            $table->decimal('popularity_score', 8, 3)->default(0)->after('metadata');
            $table->decimal('rating', 5, 2)->default(0)->after('popularity_score');
            $table->decimal('freshness_score', 6, 3)->default(0)->after('rating');
            $table->json('external_ids')->nullable()->after('metadata');

            $table->unique('uid');
            $table->index('primary_platform_family');
            $table->index(['popularity_score', 'rating']);
        });

        Schema::table('product_media', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('license_url');
            $table->unsignedInteger('width')->nullable()->after('is_primary');
            $table->unsignedInteger('height')->nullable()->after('width');
            $table->decimal('quality_score', 6, 3)->default(0)->after('height');
        });

        Schema::create('game_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_game_id', 128);
            $table->string('alias_title');
            $table->timestamps();

            $table->unique(['provider', 'provider_game_id']);
            $table->index(['product_id', 'provider']);
        });

        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('family', 32);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('family');
        });

        Schema::create('game_platform', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['product_id', 'platform_id']);
        });

        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('genres')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('game_genre', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('genre_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['product_id', 'genre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_genre');
        Schema::dropIfExists('genres');
        Schema::dropIfExists('game_platform');
        Schema::dropIfExists('platforms');
        Schema::dropIfExists('game_aliases');

        Schema::table('product_media', function (Blueprint $table) {
            $table->dropColumn(['is_primary', 'width', 'height', 'quality_score']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'uid',
                'synopsis',
                'primary_platform_family',
                'popularity_score',
                'rating',
                'freshness_score',
                'external_ids',
            ]);
        });
    }
};
