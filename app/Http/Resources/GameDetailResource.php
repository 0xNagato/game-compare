<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameDetailResource extends JsonResource
{
    /**
     * @param  \App\Models\Product  $resource
     */
    public function __construct($resource, protected array $supplemental = [])
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cover = $this->media->sortByDesc(fn ($asset) => [$asset->is_primary, $asset->quality_score, $asset->fetched_at])->first();
        $gallery = $this->media->reject(fn ($asset) => $asset->id === optional($cover)->id)->values();

        return [
            'uid' => $this->uid,
            'slug' => $this->slug,
            'title' => $this->name,
            'synopsis' => $this->synopsis,
            'release_date' => optional($this->release_date)?->toDateString(),
            'platforms' => $this->platforms->map(fn ($platform) => [
                'code' => $platform->code,
                'name' => $platform->name,
                'family' => $platform->family,
            ])->values(),
            'genres' => $this->genres->map(fn ($genre) => [
                'slug' => $genre->slug,
                'name' => $genre->name,
            ])->values(),
            'cover' => $cover ? [
                'url' => $cover->url,
                'width' => $cover->width,
                'height' => $cover->height,
                'quality_score' => (float) $cover->quality_score,
                'attribution' => [
                    'source' => $cover->source,
                    'license' => $cover->license,
                    'license_url' => $cover->license_url,
                ],
            ] : null,
            'gallery' => $gallery->map(fn ($asset) => [
                'url' => $asset->url,
                'width' => $asset->width,
                'height' => $asset->height,
                'quality_score' => (float) $asset->quality_score,
            ])->values(),
            'score' => $this->aggregateScore(),
            'rating' => (float) $this->rating,
            'popularity' => (float) $this->popularity_score,
            'freshness_score' => (float) $this->freshness_score,
            'price_series' => $this->supplemental['price_series'] ?? [],
            'region_compare' => $this->supplemental['region_compare'] ?? [],
            'offers' => $this->supplemental['offers'] ?? [],
            'external_ids' => $this->external_ids ?? [],
        ];
    }

    protected function aggregateScore(): float
    {
        return round(
            (0.5 * (float) $this->popularity_score)
            + (0.3 * (float) $this->rating)
            + (0.2 * (float) $this->freshness_score),
            3
        );
    }
}
