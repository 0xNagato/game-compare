<?php

namespace App\Services\GameData\DTO;

use Carbon\CarbonInterval;

final class TopQueryDTO
{
    /**
     * @param  list<string>|null  $platforms
     * @param  list<string>|null  $genres
     */
    public function __construct(
        public readonly int $limit,
        public readonly ?array $platforms = null,
        public readonly ?array $genres = null,
        public readonly ?CarbonInterval $freshnessWindow = null,
    ) {}
}
