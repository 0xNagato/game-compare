<?php

namespace App\Filament\Resources\RegionPrices\Pages;

use App\Filament\Resources\RegionPrices\RegionPriceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRegionPrices extends ListRecords
{
    protected static string $resource = RegionPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
