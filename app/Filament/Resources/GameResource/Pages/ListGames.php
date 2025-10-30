<?php

namespace App\Filament\Resources\GameResource\Pages;

use App\Filament\Resources\GameResource;
use Filament\Resources\Pages\ListRecords;

class ListGames extends ListRecords
{
    protected static string $resource = GameResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\LiveKeyMetrics::class,
            \App\Filament\Widgets\LivePriceSeriesChart::class,
        ];
    }
}
