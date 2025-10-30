<?php

namespace App\Filament\Resources\Alerts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AlertForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Select::make('product_id')
                    ->relationship('product', 'name')
                    ->required(),
                TextInput::make('region_code')
                    ->required(),
                TextInput::make('threshold_btc')
                    ->required()
                    ->numeric(),
                TextInput::make('comparison_operator')
                    ->required()
                    ->default('below'),
                TextInput::make('channel')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
                DateTimePicker::make('last_triggered_at'),
                Textarea::make('settings')
                    ->columnSpanFull(),
            ]);
    }
}
