<?php

namespace App\Services\GameData\DTO;

final class MediaScopeDTO
{
    public function __construct(
        public readonly bool $includeCover = true,
        public readonly bool $includeGallery = true,
        public readonly bool $includeVideo = false,
    ) {}
}
