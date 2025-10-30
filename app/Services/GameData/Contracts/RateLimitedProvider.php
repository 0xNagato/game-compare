<?php

namespace App\Services\GameData\Contracts;

use App\Services\GameData\DTO\QuotaProfileDTO;

interface RateLimitedProvider
{
    public function quotaProfile(): QuotaProfileDTO;
}
