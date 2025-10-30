<?php

namespace App\Services\GameData\DTO;

final class MediaAssetDTO
{
    public function __construct(
        public readonly string $url,
        public readonly int $width,
        public readonly int $height,
        public readonly float $qualityScore,
        public readonly ?string $type = null,
        public readonly ?array $metadata = null,
    ) {}
}
