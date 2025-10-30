<?php

namespace App\Console\Commands;

use App\Jobs\FetchProductMediaJob;
use App\Models\Product;
use App\Services\Media\ProductMediaAggregator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HydrateProductMediaCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'media:hydrate
        {--product=* : Target specific product IDs or slugs}
        {--missing : Only process products without stored media}
        {--limit=24 : Maximum number of products to process}
        {--queue : Dispatch jobs to the queue instead of running synchronously}';

    /**
     * @var string
     */
    protected $description = 'Fetch product media assets from configured provider APIs.';

    public function handle(ProductMediaAggregator $aggregator): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $queueDispatch = (bool) $this->option('queue');
        $productFilters = collect((array) $this->option('product'));

        $query = Product::query()
            ->when($productFilters->isNotEmpty(), function (Builder $builder) use ($productFilters): void {
                $ids = $productFilters
                    ->filter(fn (string $value): bool => is_numeric($value))
                    ->map(fn (string $value): int => (int) $value)
                    ->values();

                $slugs = $productFilters
                    ->filter(fn (string $value): bool => ! is_numeric($value))
                    ->values();

                if ($ids->isEmpty() && $slugs->isEmpty()) {
                    return;
                }

                $builder->where(function (Builder $inner) use ($ids, $slugs): void {
                    if ($ids->isNotEmpty()) {
                        $inner->orWhereIn('id', $ids);
                    }

                    if ($slugs->isNotEmpty()) {
                        $inner->orWhereIn('slug', $slugs);
                    }
                });
            })
            ->when((bool) $this->option('missing'), fn (Builder $builder): Builder => $builder->whereDoesntHave('media'))
            ->orderByDesc('updated_at')
            ->limit($limit);

        /** @var Collection<int, Product> $products */
        $products = $query->get();

        if ($products->isEmpty()) {
            $this->components->warn('No products matched the selection criteria.');

            return self::SUCCESS;
        }

        $providers = collect(config('media.providers', []))
            ->keys()
            ->map(fn (string $key): string => $key)
            ->values();

        if ($providers->isEmpty()) {
            $this->components->warn('No media providers are configured.');
        } else {
            $this->components->info(sprintf(
                'Using %d provider(s): %s',
                $providers->count(),
                $providers->implode(', ')
            ));
        }

        $summary = collect();

        $this->withProgressBar($products, function (Product $product) use (&$summary, $queueDispatch, $aggregator): void {
            if ($queueDispatch) {
                FetchProductMediaJob::dispatch($product->id);

                $summary->push([
                    'product' => $product->name,
                    'status' => 'queued',
                    'assets' => '—',
                ]);

                return;
            }

            $results = $aggregator->fetchAndStore($product);

            $summary->push([
                'product' => $product->name,
                'status' => $results->isNotEmpty() ? 'fetched' : 'empty',
                'assets' => $results->count(),
            ]);
        });

        $this->newLine(2);

        if ($summary->isNotEmpty()) {
            $this->table(
                ['Product', 'Status', 'Assets'],
                $summary->map(fn (array $row): array => [
                    $row['product'],
                    ucfirst($row['status']),
                    $row['assets'],
                ])->all()
            );
        }

        return self::SUCCESS;
    }
}