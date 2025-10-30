<?php

namespace App\Services\GameData;

final class ProviderHealth
{
    public function __construct(
        public readonly float $mediaQuality,
        public readonly float $successRate,
        public readonly float $quotaRemaining,
    ) {}

    public static function default(): self
    {
        return new self(0.5, 0.8, 0.5);
    }
}
