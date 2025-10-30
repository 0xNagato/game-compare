<?php

namespace App\Services\GameData\Contracts;

use App\Services\GameData\DTO\FetchContextDTO;
use App\Services\GameData\DTO\GameDetailDTO;
use App\Services\GameData\DTO\GameUid;
use App\Services\GameData\DTO\MediaBundleDTO;
use App\Services\GameData\DTO\MediaScopeDTO;
use App\Services\GameData\DTO\OfferCollectionDTO;
use App\Services\GameData\DTO\OfferFilterDTO;
use App\Services\GameData\DTO\PlatformCollectionDTO;
use App\Services\GameData\DTO\TopQueryDTO;
use App\Services\GameData\DTO\TopResultDTO;

interface GameProvider
{
    public function providerKey(): string;

    public function searchTop(TopQueryDTO $query): TopResultDTO;

    public function fetchGame(GameUid $uid, ?FetchContextDTO $context = null): GameDetailDTO;

    public function fetchMedia(GameUid $uid, ?MediaScopeDTO $scope = null): MediaBundleDTO;

    public function listPlatforms(): PlatformCollectionDTO;

    public function fetchOffers(GameUid $uid, ?OfferFilterDTO $filter = null): OfferCollectionDTO;
}
