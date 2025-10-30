<?php

namespace App\Services\GameData\DTO;

final class OfferFilterDTO
{
    /**
     * @param  list<string>|null  $regions
     * @param  list<string>|null  $retailers
     */
    public function __construct(
        public readonly ?array $regions = null,
        public readonly ?array $retailers = null,
        public readonly bool $onlyVerified = true,
    ) {}
}
