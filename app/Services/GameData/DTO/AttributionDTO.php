<?php

namespace App\Services\GameData\DTO;

final class AttributionDTO
{
    public function __construct(
        public readonly string $label,
        public readonly ?string $url = null,
        public readonly ?string $license = null,
        public readonly ?string $logoUrl = null,
    ) {}
}
