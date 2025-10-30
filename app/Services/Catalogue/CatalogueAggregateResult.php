<?php

namespace App\Services\Catalogue;

use Illuminate\Support\Collection;

class CatalogueAggregateResult
{
    public function __construct(
        public readonly Collection $entries,
        public readonly array $sources,
        public readonly int $totalRequested,
    ) {}
}
