<?php

namespace App\Filament\Resources\RegionPrices\Pages;

use App\Filament\Resources\RegionPrices\RegionPriceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRegionPrice extends ViewRecord
{
    protected static string $resource = RegionPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
