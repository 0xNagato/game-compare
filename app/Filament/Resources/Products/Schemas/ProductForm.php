<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('platform')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('category'),
                DatePicker::make('release_date'),
                Textarea::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
