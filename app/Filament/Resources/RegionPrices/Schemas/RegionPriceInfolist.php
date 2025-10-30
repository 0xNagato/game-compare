<?php

namespace App\Filament\Resources\RegionPrices\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RegionPriceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('skuRegion.id')
                    ->label('Sku region'),
                TextEntry::make('recorded_at')
                    ->dateTime(),
                TextEntry::make('fiat_amount')
                    ->numeric(),
                TextEntry::make('btc_value')
                    ->numeric(),
                IconEntry::make('tax_inclusive')
                    ->boolean(),
                TextEntry::make('fx_rate_snapshot')
                    ->numeric(),
                TextEntry::make('btc_rate_snapshot')
                    ->numeric(),
                TextEntry::make('raw_payload')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
