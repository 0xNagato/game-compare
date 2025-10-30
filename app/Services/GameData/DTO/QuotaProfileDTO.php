<?php

namespace App\Services\GameData\DTO;

final class QuotaProfileDTO
{
    public function __construct(
        public readonly float $maxRequestsPerSecond,
        public readonly int $burst,
        public readonly int $dailyCap,
        public readonly int $retryAfterSeconds,
        public readonly int $backoffBaseMs,
        public readonly int $backoffCapMs,
        public readonly int $circuitFailureThreshold,
        public readonly int $circuitWindowSeconds,
        public readonly int $circuitCooldownSeconds,
    ) {}
}
