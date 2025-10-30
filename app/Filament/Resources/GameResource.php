<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GameResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GameResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-fire';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $label = 'Game';

    protected static ?string $pluralLabel = 'Games';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')
                ->label('Title')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('slug')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('uid')
                ->label('Canonical UID')
                ->helperText('Hash of normalized title, release year & platform family.')
                ->maxLength(64),
            Forms\Components\Select::make('primary_platform_family')
                ->label('Primary Platform Family')
                ->options([
                    'pc' => 'PC',
                    'playstation' => 'PlayStation',
                    'xbox' => 'Xbox',
                    'nintendo' => 'Nintendo',
                    'mobile' => 'Mobile',
                    'sega' => 'Sega',
                    'arcade' => 'Arcade',
                    'retro' => 'Retro',
                ])
                ->searchable(),
            Forms\Components\DatePicker::make('release_date'),
            Forms\Components\Textarea::make('synopsis')->rows(4),
            Forms\Components\TextInput::make('popularity_score')
                ->numeric()
                ->label('Popularity')
                ->helperText('0.0 – 1.0 aggregated demand index.'),
            Forms\Components\TextInput::make('rating')
                ->numeric()
                ->label('Rating /100'),
            Forms\Components\TextInput::make('freshness_score')
                ->numeric()
                ->helperText('Recent activity weight (0.0 – 1.0).'),
            Forms\Components\Select::make('platforms')
                ->label('Platforms')
                ->multiple()
                ->relationship('platforms', 'name')
                ->preload()
                ->searchable()
                ->helperText('Platform variants and releases.'),
            Forms\Components\Select::make('genres')
                ->multiple()
                ->relationship('genres', 'name')
                ->preload()
                ->searchable(),
            Forms\Components\KeyValue::make('external_ids')
                ->keyLabel('Provider')
                ->valueLabel('Identifier')
                ->addButtonLabel('Add Provider'),
            Forms\Components\KeyValue::make('metadata')
                ->keyLabel('Attribute')
                ->valueLabel('Value')
                ->addButtonLabel('Add Attribute'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['media', 'skuRegions']))
            ->columns([
                TextColumn::make('name')
                    ->label('Title')
                    ->searchable()
                    ->description(fn (Product $record) => $record->slug)
                    ->toggleable(),
                TextColumn::make('primary_platform_family')
                    ->label('Family')
                    ->formatStateUsing(fn (?string $state) => ucfirst($state ?? ''))
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('rating')
                    ->label('Rating')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('popularity_score')
                    ->label('Popularity')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2))
                    ->sortable(),
                TextColumn::make('freshness_score')
                    ->label('Freshness')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2))
                    ->sortable(),
                BadgeColumn::make('media_count')
                    ->label('Media')
                    ->counts('media')
                    ->colors(['success']),
                BadgeColumn::make('sku_regions_count')
                    ->label('Offers')
                    ->counts('skuRegions')
                    ->colors(['warning']),
                TextColumn::make('updated_at')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform_family')
                    ->attribute('primary_platform_family')
                    ->options([
                        'pc' => 'PC',
                        'playstation' => 'PlayStation',
                        'xbox' => 'Xbox',
                        'nintendo' => 'Nintendo',
                        'mobile' => 'Mobile',
                        'sega' => 'Sega',
                        'arcade' => 'Arcade',
                        'retro' => 'Retro',
                    ])->label('Platform Family'),
                Tables\Filters\SelectFilter::make('genres')
                    ->relationship('genres', 'name'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGames::route('/'),
            'view' => Pages\ViewGame::route('/{record}'),
            'edit' => Pages\EditGame::route('/{record}/edit'),
        ];
    }
}
