<?php

namespace App\Services\GameData\DTO;

use Carbon\CarbonImmutable;

final class TopResultDTO
{
    /**
     * @param  list<TopGameDTO>  $games
     */
    public function __construct(
        public readonly array $games,
        public readonly string $providerKey,
        public readonly CarbonImmutable $pulledAt,
    ) {}
}
