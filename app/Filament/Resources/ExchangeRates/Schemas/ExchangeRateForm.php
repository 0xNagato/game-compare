<?php

namespace App\Filament\Resources\ExchangeRates\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExchangeRateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('base_currency')
                    ->required(),
                TextInput::make('quote_currency')
                    ->required(),
                TextInput::make('rate')
                    ->required()
                    ->numeric(),
                DateTimePicker::make('fetched_at')
                    ->required(),
                TextInput::make('provider')
                    ->required(),
                Textarea::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
