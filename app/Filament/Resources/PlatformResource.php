<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlatformResource\Pages;
use App\Models\Platform;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlatformResource extends Resource
{
    protected static ?string $model = Platform::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')->required()->maxLength(120),
            Forms\Components\TextInput::make('code')->required()->maxLength(60)->unique(ignoreRecord: true),
            Forms\Components\Select::make('family')
                ->options([
                    'pc' => 'PC',
                    'playstation' => 'PlayStation',
                    'xbox' => 'Xbox',
                    'nintendo' => 'Nintendo',
                    'mobile' => 'Mobile',
                    'sega' => 'Sega',
                    'arcade' => 'Arcade',
                    'retro' => 'Retro',
                ])->required(),
            Forms\Components\KeyValue::make('metadata')->keyLabel('Key')->valueLabel('Value'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('code')->badge()->sortable(),
                TextColumn::make('family')->label('Family')->formatStateUsing(fn ($state) => ucfirst($state))->badge()->color('info'),
                TextColumn::make('products_count')->counts('products')->label('Games'),
                TextColumn::make('updated_at')->since()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('family')->options([
                    'pc' => 'PC',
                    'playstation' => 'PlayStation',
                    'xbox' => 'Xbox',
                    'nintendo' => 'Nintendo',
                    'mobile' => 'Mobile',
                    'sega' => 'Sega',
                    'arcade' => 'Arcade',
                    'retro' => 'Retro',
                ]),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatforms::route('/'),
            'create' => Pages\CreatePlatform::route('/create'),
            'edit' => Pages\EditPlatform::route('/{record}/edit'),
        ];
    }
}
