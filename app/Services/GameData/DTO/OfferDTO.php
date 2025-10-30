<?php

namespace App\Services\GameData\DTO;

final class OfferDTO
{
    public function __construct(
        public readonly string $retailerCode,
        public readonly string $regionCode,
        public readonly float $price,
        public readonly float $btcValue,
        public readonly ?string $url,
        public readonly bool $verified,
        public readonly ?ProviderRefDTO $providerRef = null,
        public readonly ?array $metadata = null,
    ) {}
}
