<?php

namespace App\Services\GameData\DTO;

use Carbon\CarbonImmutable;

final class GameDetailDTO
{
    /**
     * @param  list<string>  $platformFamilies
     * @param  list<string>  $genres
     * @param  array<string, string>  $externalIds
     */
    public function __construct(
        public readonly GameUid $uid,
        public readonly string $title,
        public readonly ?string $synopsis,
        public readonly ?CarbonImmutable $releaseDate,
        public readonly array $platformFamilies,
        public readonly array $genres,
        public readonly array $externalIds,
        public readonly ProviderRefDTO $providerRef,
        public readonly ?array $metadata = null,
    ) {}
}
