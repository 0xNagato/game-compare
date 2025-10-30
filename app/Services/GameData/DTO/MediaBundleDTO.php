<?php

namespace App\Services\GameData\DTO;

/**
 * @psalm-type MediaCollection = list<MediaAssetDTO>
 * @psalm-type AttributionCollection = list<AttributionDTO>
 */
final class MediaBundleDTO
{
    /**
     * @param  MediaCollection  $gallery
     * @param  MediaCollection  $video
     * @param  AttributionCollection  $attribution
     */
    public function __construct(
        public readonly ?MediaAssetDTO $cover,
        public readonly array $gallery,
        public readonly array $video,
        public readonly array $attribution,
    ) {}
}
