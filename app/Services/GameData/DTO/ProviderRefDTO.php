<?php

namespace App\Services\GameData\DTO;

final class ProviderRefDTO
{
    public function __construct(
        public readonly string $providerKey,
        public readonly string $providerGameId,
    ) {}
}
