<?php

namespace App\Console\Commands;

use App\Jobs\FetchProductMediaJob;
use App\Models\GameAlias;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HydrateProductVideosCommand extends Command
{
    protected $signature = 'media:hydrate-videos
        {--limit=200 : Max number of products to process}
        {--chunk=100 : Chunk size for dispatch}
        {--families= : Optional comma list to restrict by primary_platform_family}
        {--queries-per-product=3 : Max query variants per product}
        {--queue : Dispatch to queue instead of inline sync}
    ';

    protected $description = 'Fetch video trailers for products missing video media using provider searches by name/aliases.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $chunk = max(10, (int) $this->option('chunk'));
        $maxQueries = max(1, (int) $this->option('queries-per-product'));
        $families = collect(array_filter(array_map('trim', explode(',', (string) $this->option('families') ?: ''))))
            ->map(fn ($v) => strtolower($v))
            ->filter();

        $query = Product::query()
            ->when($families->isNotEmpty(), fn (Builder $q) => $q->whereIn('primary_platform_family', $families->all()))
            ->whereDoesntHave('media', function (Builder $q) {
                $q->where('media_type', 'video');
            })
            ->orderByDesc('popularity_score')
            ->orderByDesc('updated_at')
            ->limit($limit);

        /** @var Collection<int, Product> $products */
        $products = $query->get();

        if ($products->isEmpty()) {
            $this->components->warn('No products are missing video media for the current filters.');
            return self::SUCCESS;
        }

        $queue = (bool) $this->option('queue');

        $this->info(sprintf('Hydrating videos for %d product(s)...', $products->count()));

        $dispatched = 0;
        $this->withProgressBar($products->chunk($chunk), function (Collection $batch) use (&$dispatched, $maxQueries, $queue) {
            /** @var Collection<int, Product> $batch */
            foreach ($batch as $product) {
                $queries = $this->buildQueries($product)->unique()->take($maxQueries)->values();

                foreach ($queries as $q) {
                    $context = ['query' => $q, 'prefer_videos' => true];
                    if ($queue) {
                        FetchProductMediaJob::dispatch($product->id, $context);
                    } else {
                        // Inline: run job handle to respect idempotency and shared aggregator
                        (new FetchProductMediaJob($product->id, $context))->handle(app(\App\Services\Media\ProductMediaAggregator::class));
                    }
                    $dispatched++;
                }
            }
        });

        $this->newLine();
        $this->components->info(sprintf('Dispatched %d provider searches across %d products.', $dispatched, $products->count()));

        return self::SUCCESS;
    }

    /**
     * Build prioritized list of search queries for a product.
     *
     * @return Collection<int, string>
     */
    protected function buildQueries(Product $product): Collection
    {
        $name = trim((string) $product->name);
        $family = trim((string) ($product->primary_platform_family ?? ''));
        $platform = trim((string) ($product->platform ?? ''));
        $year = null;
        try {
            $d = $product->getAttribute('release_date');
            if ($d instanceof \DateTimeInterface) {
                $year = $d->format('Y');
            } elseif (is_string($d) && $d !== '' && preg_match('/^\\d{4}/', $d)) {
                $year = substr($d, 0, 4);
            }
        } catch (\Throwable) {}

        $base = collect([$name])
            ->merge($family ? [$name.' '.$family] : [])
            ->merge($platform ? [$name.' '.$platform] : [])
            ->merge([$name.' trailer', $name.' gameplay'])
            ->merge($year ? [$name.' '.$year.' trailer', $name.' official trailer '.$year] : []);

        $aliases = GameAlias::query()
            ->where('product_id', $product->id)
            ->orderBy('id')
            ->limit(3)
            ->pluck('alias_title');

        $aliasQueries = $aliases
            ->flatMap(fn ($t) => [
                $t,
                $t.' trailer',
                $year ? ($t.' '.$year.' trailer') : null,
            ]);

        return $base->merge($aliasQueries)
            ->filter(fn ($q) => is_string($q) && $q !== '')
            ->map(fn ($q) => trim($q));
    }
}
