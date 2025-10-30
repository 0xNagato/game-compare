<?php

namespace App\Filament\Resources\RegionPrices;

use App\Filament\Resources\RegionPrices\Pages\CreateRegionPrice;
use App\Filament\Resources\RegionPrices\Pages\EditRegionPrice;
use App\Filament\Resources\RegionPrices\Pages\ListRegionPrices;
use App\Filament\Resources\RegionPrices\Pages\ViewRegionPrice;
use App\Filament\Resources\RegionPrices\Schemas\RegionPriceForm;
use App\Filament\Resources\RegionPrices\Schemas\RegionPriceInfolist;
use App\Filament\Resources\RegionPrices\Tables\RegionPricesTable;
use App\Models\RegionPrice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RegionPriceResource extends Resource
{
    protected static ?string $model = RegionPrice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'RegionPrice';

    public static function form(Schema $schema): Schema
    {
        return RegionPriceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RegionPriceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RegionPricesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegionPrices::route('/'),
            'create' => CreateRegionPrice::route('/create'),
            'view' => ViewRegionPrice::route('/{record}'),
            'edit' => EditRegionPrice::route('/{record}/edit'),
        ];
    }
}
