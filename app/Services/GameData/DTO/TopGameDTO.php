<?php

namespace App\Services\GameData\DTO;

final class TopGameDTO
{
    /**
     * @param  list<string>  $platformFamilies
     */
    public function __construct(
        public readonly GameUid $uid,
        public readonly ProviderRefDTO $providerRef,
        public readonly string $title,
        public readonly float $popularityScore,
        public readonly float $rating,
        public readonly ?int $releaseYear,
        public readonly array $platformFamilies,
    ) {}
}
