<?php

namespace App\Services\Media\Contracts;

use App\Models\Product;
use Illuminate\Support\Collection;

interface ProductMediaProvider
{
    public function enabled(): bool;

    /**
     * @param  array<string, mixed>  $context
     * @return \Illuminate\Support\Collection<int, \App\Services\Media\DTOs\ProductMediaData>
     */
    public function fetch(Product $product, array $context = []): Collection;

    public function getName(): string;
}
