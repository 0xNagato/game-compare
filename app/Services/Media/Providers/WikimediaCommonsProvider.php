<?php

namespace App\Services\Media\Providers;

use App\Models\Product;
use App\Services\Media\Contracts\ProductMediaProvider;
use App\Services\Media\DTOs\ProductMediaData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class WikimediaCommonsProvider implements ProductMediaProvider
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(private readonly array $options = []) {}

    public function enabled(): bool
    {
        return ($this->options['enabled'] ?? true) === true;
    }

    public function getName(): string
    {
        return 'wikimedia_commons';
    }

    public function fetch(Product $product, array $context = []): Collection
    {
        if (! $this->enabled()) {
            return collect();
        }

        $query = $context['query'] ?? $this->buildQuery($product);

        if (blank($query)) {
            return collect();
        }

        $baseUrl = $this->options['base_url'] ?? 'https://commons.wikimedia.org/w/api.php';

        $response = Http::timeout(config('media.http_timeout', 10))
            ->acceptJson()
            ->get($baseUrl, [
                'action' => 'query',
                'format' => 'json',
                'formatversion' => 2,
                'prop' => 'imageinfo',
                'iiprop' => 'url|extmetadata',
                'generator' => 'search',
                'gsrlimit' => 5,
                'gsrsearch' => $query,
                'origin' => '*',
            ]);

        if ($response->failed()) {
            return collect();
        }

        $pages = data_get($response->json(), 'query.pages', []);

        if (! is_array($pages) || empty($pages)) {
            return collect();
        }

        return collect($pages)
            ->filter(fn (array $page) => ! empty($page['imageinfo'][0]['url'] ?? null))
            ->map(function (array $page) {
                $imageInfo = $page['imageinfo'][0];
                $meta = $imageInfo['extmetadata'] ?? [];

                $license = $meta['LicenseShortName']['value'] ?? ($this->options['default_license'] ?? null);
                $licenseUrl = $meta['LicenseUrl']['value'] ?? null;
                $artist = $meta['Artist']['value'] ?? null;
                $credit = strip_tags($meta['Credit']['value'] ?? 'Wikimedia Commons contributors');

                return new ProductMediaData(
                    source: $this->getName(),
                    externalId: isset($page['pageid']) ? (string) $page['pageid'] : null,
                    mediaType: 'image',
                    title: $page['title'] ?? null,
                    caption: strip_tags($meta['ImageDescription']['value'] ?? ''),
                    url: $imageInfo['url'],
                    thumbnailUrl: $imageInfo['url'],
                    attribution: $artist ? strip_tags($artist) : $credit,
                    license: $license,
                    licenseUrl: $licenseUrl,
                    metadata: [
                        'credit' => $credit,
                        'attribution_required' => true,
                        'source_url' => data_get($meta, 'SourceUrl.value'),
                    ]
                );
            })
            ->values();
    }

    protected function buildQuery(Product $product): string
    {
        $keywords = [$product->name];

        $category = strtolower((string) ($product->category ?? ''));

        if (str_contains($category, 'console') || str_contains($product->platform ?? '', 'Console')) {
            $keywords[] = 'console photo';
        } else {
            $keywords[] = 'video game box art';
        }

        return implode(' ', array_filter($keywords));
    }
}
