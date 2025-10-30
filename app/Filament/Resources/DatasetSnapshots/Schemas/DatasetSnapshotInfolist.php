<?php

namespace App\Filament\Resources\DatasetSnapshots\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DatasetSnapshotInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('kind'),
                TextEntry::make('provider')
                    ->placeholder('-'),
                TextEntry::make('status'),
                TextEntry::make('started_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('finished_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('row_count')
                    ->numeric(),
                TextEntry::make('context')
                    ->formatStateUsing(fn ($state) => self::stringifyState($state))
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('error_details')
                    ->formatStateUsing(fn ($state) => self::stringifyState($state))
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

    private static function stringifyState(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        if (is_array($state) || is_object($state)) {
            return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return (string) $state;
    }
}
