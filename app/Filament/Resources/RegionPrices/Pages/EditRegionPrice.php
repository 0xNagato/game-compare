<?php

namespace App\Filament\Resources\RegionPrices\Pages;

use App\Filament\Resources\RegionPrices\RegionPriceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRegionPrice extends EditRecord
{
    protected static string $resource = RegionPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
