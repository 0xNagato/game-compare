<?php

namespace App\Services\GameData\DTO;

use Carbon\CarbonImmutable;

final class OfferCollectionDTO
{
    /**
     * @param  list<OfferDTO>  $offers
     * @param  list<RegionSnapshotDTO>  $regions
     */
    public function __construct(
        public readonly array $offers,
        public readonly array $regions,
        public readonly CarbonImmutable $fetchedAt,
    ) {}
}
