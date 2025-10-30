<?php

namespace App\Filament\Resources\RegionPrices\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RegionPriceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sku_region_id')
                    ->relationship('skuRegion', 'id')
                    ->required(),
                DateTimePicker::make('recorded_at')
                    ->required(),
                TextInput::make('fiat_amount')
                    ->required()
                    ->numeric(),
                TextInput::make('btc_value')
                    ->required()
                    ->numeric(),
                Toggle::make('tax_inclusive')
                    ->required(),
                TextInput::make('fx_rate_snapshot')
                    ->required()
                    ->numeric(),
                TextInput::make('btc_rate_snapshot')
                    ->required()
                    ->numeric(),
                Textarea::make('raw_payload')
                    ->columnSpanFull(),
            ]);
    }
}
