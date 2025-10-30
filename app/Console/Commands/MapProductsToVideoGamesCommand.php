<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class MapProductsToVideoGamesCommand extends Command
{
    protected $signature = 'catalogue:map-video-games
        {--product=* : Limit mapping to specific product IDs or slugs}
        {--chunk=100 : Number of products processed per chunk}
        {--dry-run : Output changes without persisting them}
        {--fresh : Force-update existing rows even when unchanged}';

    protected $description = 'Ensure every product classified as a game has a corresponding entry in the video_games table.';

    public function handle(): int
    {
        $chunkSize = max((int) $this->option('chunk'), 10);
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('fresh');

        $filters = collect((array) $this->option('product'))
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->values();

        $query = Product::query()
            ->where(function (Builder $builder): void {
                $builder->where('category', 'Game')
                    ->orWhereRaw('LOWER(category) = ?', ['game']);
            })
            ->when($filters->isNotEmpty(), function (Builder $builder) use ($filters): void {
                $ids = $filters
                    ->filter(fn (string $value): bool => is_numeric($value))
                    ->map(fn (string $value): int => (int) $value)
                    ->values();

                $slugs = $filters
                    ->filter(fn (string $value): bool => ! is_numeric($value))
                    ->values();

                $builder->where(function (Builder $inner) use ($ids, $slugs): void {
                    if ($ids->isNotEmpty()) {
                        $inner->orWhereIn('id', $ids->all());
                    }

                    if ($slugs->isNotEmpty()) {
                        $inner->orWhereIn('slug', $slugs->all());
                    }
                });
            })
            ->orderBy('id');

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->components->warn('No game products found to map.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            'Preparing to map %d product(s) into video_games%s.',
            $total,
            $dryRun ? ' (dry run)' : ''
        ));

        $progress = $this->output->createProgressBar($total);
        $progress->start();

        $summary = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
        ];

        $query->chunkById($chunkSize, function (Collection $products) use ($dryRun, $force, $progress, &$summary): void {
            $products->loadMissing(['genres', 'platforms', 'videoGames']);

            foreach ($products as $product) {
                $payload = $this->buildPayload($product);
                $videoGame = $product->videoGames->first();

                if ($videoGame === null) {
                    if (! $dryRun) {
                        $product->videoGames()->create($payload);
                    }

                    $summary['created']++;
                } else {
                    $videoGame->fill($payload);

                    if ($force || $videoGame->isDirty()) {
                        if (! $dryRun) {
                            $videoGame->save();
                        }

                        $summary['updated']++;
                    } else {
                        $summary['unchanged']++;
                    }
                }

                $progress->advance();
            }
        });

        $progress->finish();
        $this->newLine(2);

        $this->table(
            ['Created', 'Updated', 'Unchanged'],
            [[
                $summary['created'],
                $summary['updated'],
                $summary['unchanged'],
            ]]
        );

        return self::SUCCESS;
    }

    protected function buildPayload(Product $product): array
    {
        $metadata = $product->metadata ?? [];

        $platforms = $product->platforms
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        $genres = $product->genres
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        $payload = array_filter([
            'title' => $product->name,
            'genre' => Arr::first($genres),
            'release_date' => optional($product->release_date)->toDateString(),
            'developer' => $this->resolveDeveloper($metadata),
            'metadata' => $this->buildMetadata($metadata, $platforms, $genres, $product->synopsis, $product->external_ids),
        ], fn ($value) => $value !== null && $value !== '');

        return $payload;
    }

    protected function resolveDeveloper(array $metadata): ?string
    {
        return Arr::get($metadata, 'developer')
            ?? Arr::get($metadata, 'developers.0')
            ?? Arr::get($metadata, 'sources.thegamesdb.developer')
            ?? Arr::get($metadata, 'sources.rawg.developer')
            ?? Arr::get($metadata, 'sources.nexarda.developer');
    }

    protected function buildMetadata(array $metadata, array $platforms, array $genres, ?string $synopsis, ?array $externalIds): ?array
    {
        $compiled = array_filter([
            'platforms' => $platforms !== [] ? $platforms : null,
            'genres' => $genres !== [] ? $genres : null,
            'synopsis' => $synopsis,
            'external_ids' => $externalIds,
            'sources' => Arr::get($metadata, 'sources'),
        ], fn ($value) => $value !== null && $value !== [] && $value !== '');

        return $compiled === [] ? null : $compiled;
    }
}
