<?php

namespace App\Services\GameData\DTO;

use Carbon\CarbonImmutable;

final class FetchContextDTO
{
    /**
     * @param  list<string>|null  $regions
     */
    public function __construct(
        public readonly ?array $regions = null,
        public readonly ?bool $usePrivateKey = null,
        public readonly ?CarbonImmutable $forceFreshAfter = null,
    ) {}
}
