<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\SkuRegion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerifyLinksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $productId)
    {
        $this->onQueue('verify');
    }

    public function backoff(): int
    {
        return 45;
    }

    public function handle(): void
    {
        $product = Product::query()
            ->with(['media', 'skuRegions'])
            ->find($this->productId);

        if (! $product) {
            Log::notice('verify_links.skipped_missing_product', [
                'product_id' => $this->productId,
            ]);

            return;
        }

        $checked = 0;

        $product->media
            ->take(12)
            ->each(function (ProductMedia $media) use (&$checked): void {
                if (! filter_var($media->url, FILTER_VALIDATE_URL)) {
                    $this->markMedia($media, [
                        'status' => 'invalid_url',
                        'code' => null,
                        'checked_at' => now()->toIso8601String(),
                        'reason' => 'invalid_url_format',
                    ]);

                    return;
                }

                $result = $this->probe($media->url);
                $this->markMedia($media, $result);
                $checked++;
            });

        $product->skuRegions
            ->take(8)
            ->each(function (SkuRegion $region) use (&$checked): void {
                $metadata = (array) ($region->metadata ?? []);
                $url = Arr::get($metadata, 'store_url')
                    ?? Arr::get($metadata, 'url')
                    ?? null;

                if (! is_string($url) || $url === '') {
                    return;
                }

                if (! filter_var($url, FILTER_VALIDATE_URL)) {
                    $this->markRegion($region, [
                        'status' => 'invalid_url',
                        'code' => null,
                        'checked_at' => now()->toIso8601String(),
                        'reason' => 'invalid_url_format',
                    ]);

                    return;
                }

                $result = $this->probe($url);
                $this->markRegion($region, $result);
                $checked++;
            });

        Log::info('verify_links.completed', [
            'product_id' => $product->id,
            'checked' => $checked,
        ]);
    }

    /**
     * @return array{status:string,code:int|null,checked_at:string,reason:?string}
     */
    protected function probe(string $url): array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'GameCompareLinkVerifier/1.0'])
                ->head($url);

            $code = $response->status();

            if ($response->successful()) {
                return [
                    'status' => 'ok',
                    'code' => $code,
                    'checked_at' => now()->toIso8601String(),
                    'reason' => null,
                ];
            }

            if ($code === 405) {
                $fallback = Http::timeout(8)
                    ->withHeaders(['User-Agent' => 'GameCompareLinkVerifier/1.0'])
                    ->get($url);

                return [
                    'status' => $fallback->successful() ? 'ok' : 'failed',
                    'code' => $fallback->status(),
                    'checked_at' => now()->toIso8601String(),
                    'reason' => $fallback->successful() ? null : $fallback->reason(),
                ];
            }

            return [
                'status' => 'failed',
                'code' => $code,
                'checked_at' => now()->toIso8601String(),
                'reason' => $response->reason(),
            ];
        } catch (\Throwable $exception) {
            return [
                'status' => 'error',
                'code' => null,
                'checked_at' => now()->toIso8601String(),
                'reason' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array{status:string,code:int|null,checked_at:string,reason:?string}  $result
     */
    protected function markMedia(ProductMedia $media, array $result): void
    {
        $metadata = (array) ($media->metadata ?? []);
        $metadata['link_check'] = $result;

        $media->metadata = $metadata;
        $media->save();
    }

    /**
     * @param  array{status:string,code:int|null,checked_at:string,reason:?string}  $result
     */
    protected function markRegion(SkuRegion $region, array $result): void
    {
        $metadata = (array) ($region->metadata ?? []);
        $metadata['link_check'] = $result;

        $region->metadata = $metadata;

        if (in_array($result['status'], ['failed', 'error'], true)) {
            $region->is_active = false;
        }

        $region->save();
    }
}
