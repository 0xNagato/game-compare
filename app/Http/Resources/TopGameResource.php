<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopGameResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cover = $this->media->sortByDesc(fn ($asset) => [$asset->is_primary, $asset->quality_score, $asset->fetched_at])->first();

        $score = $this->aggregateScore();

        return [
            'uid' => $this->uid,
            'slug' => $this->slug,
            'title' => $this->name,
            'cover_url' => $cover?->url,
            'platforms' => $this->platforms->map(fn ($platform) => [
                'code' => $platform->code,
                'name' => $platform->name,
                'family' => $platform->family,
            ])->values(),
            'platform_badges' => $this->platforms->pluck('family')->unique()->values(),
            'genres' => $this->genres->map(fn ($genre) => [
                'slug' => $genre->slug,
                'name' => $genre->name,
            ])->values(),
            'score' => $score,
            'rating' => (float) $this->rating,
            'popularity' => (float) $this->popularity_score,
            'freshness_score' => (float) $this->freshness_score,
            'release_date' => optional($this->release_date)?->toDateString(),
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
