<?php

namespace Tests\Stubs;

class FakePriceProvider
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function fetchDeals(array $options = []): array
    {
        return [
            'results' => [],
            'meta' => [
                'options' => $options,
            ],
        ];
    }
}
