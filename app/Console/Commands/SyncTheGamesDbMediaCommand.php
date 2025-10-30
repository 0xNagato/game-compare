<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Media\Providers\TheGamesDbProvider;
use App\Services\Media\Synchronizers\TheGamesDbSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class SyncTheGamesDbMediaCommand extends Command
{
    protected $signature = 'media:sync-thegamesdb
        {--product= : Product ID or slug to sync}
        {--chunk=50 : Chunk size for bulk sync}
        {--use-private-key : Use the private API key instead of the public key for requests}';

    protected $description = 'Sync product media assets from TheGamesDB.';

    public function handle(): int
    {
        $options = config('media.providers.thegamesdb.options', []);
        $provider = app(TheGamesDbProvider::class, ['options' => array_merge($options, ['enabled' => true])]);
        $synchronizer = new TheGamesDbSynchronizer($provider);

        $usePrivateKey = (bool) $this->option('use-private-key');
        $context = ['use_private_key' => $usePrivateKey];

        $chunkSize = max(1, (int) $this->option('chunk'));

        $products = $this->resolveProducts();

        if ($products->isEmpty()) {
            $this->warn('No products matched the provided criteria.');

            return self::SUCCESS;
        }

        $keyLabel = $usePrivateKey ? 'private key' : 'public key';
        $this->info(sprintf('Syncing %d product(s) from TheGamesDB (%s).', $products->count(), $keyLabel));

        $totalSynced = 0;

        $products->chunk($chunkSize)->each(function (Collection $chunk) use ($synchronizer, &$totalSynced, $context) {
            /** @var Collection<int, Product> $chunk */
            $chunk->each(function (Product $product) use ($synchronizer, &$totalSynced, $context) {
                $synced = $synchronizer->syncProduct($product, $context);
                $totalSynced += $synced;
                $this->line(sprintf('- %s (%s): %d asset(s)', $product->name, $product->slug, $synced));
            });
        });

        $this->info(sprintf('Completed TheGamesDB sync. Assets synced: %d', $totalSynced));

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Product>
     */
    protected function resolveProducts(): Collection
    {
        $identifier = $this->option('product');

        if ($identifier) {
            $product = Product::query()
                ->where('id', $identifier)
                ->orWhere('slug', $identifier)
                ->first();

            return $product ? collect([$product]) : collect();
        }

        /** @var EloquentCollection<int, Product> $all */
        $all = Product::query()->orderBy('id')->get();

        return $all;
    }
}
