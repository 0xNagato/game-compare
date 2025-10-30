<?php

namespace App\Services\GameData\DTO;

final class RegionSnapshotDTO
{
    public function __construct(
        public readonly string $regionCode,
        public readonly float $medianPrice,
        public readonly float $btcValue,
        public readonly ?string $currency = null,
    ) {}
}
