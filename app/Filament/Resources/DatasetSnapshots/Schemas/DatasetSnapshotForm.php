<?php

namespace App\Filament\Resources\DatasetSnapshots\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DatasetSnapshotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kind')
                    ->required(),
                TextInput::make('provider'),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                DateTimePicker::make('started_at'),
                DateTimePicker::make('finished_at'),
                TextInput::make('row_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('context')
                    ->columnSpanFull(),
                Textarea::make('error_details')
                    ->columnSpanFull(),
            ]);
    }
}
