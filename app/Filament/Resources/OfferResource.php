<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfferResource\Pages;
use App\Models\SkuRegion;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OfferResource extends Resource
{
    protected static ?string $model = SkuRegion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|\UnitEnum|null $navigationGroup = 'Pricing';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('product_id')
                ->relationship('product', 'name')
                ->searchable()
                ->required(),
            Forms\Components\Select::make('country_id')
                ->relationship('country', 'name')
                ->searchable()
                ->label('Country'),
            Forms\Components\Select::make('currency_id')
                ->relationship('currency', 'code')
                ->searchable()
                ->label('Currency'),
            Forms\Components\TextInput::make('region_code')->required()->maxLength(8),
            Forms\Components\TextInput::make('retailer')->required()->maxLength(120),
            Forms\Components\TextInput::make('sku')->maxLength(120),
            Forms\Components\KeyValue::make('metadata')->keyLabel('Attribute')->valueLabel('Value'),
            Forms\Components\Toggle::make('is_active'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'currency', 'latestPrice']))
            ->columns([
                TextColumn::make('product.name')->label('Game')->searchable(),
                TextColumn::make('retailer')->searchable()->badge()->color('info'),
                BadgeColumn::make('region_code')->label('Region'),
                TextColumn::make('currency.code')->label('Currency'),
                TextColumn::make('sku')->label('SKU')->toggleable(),
                TextColumn::make('latestPrice.fiat_amount')->label('Latest Price')->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 2) : '—'),
                TextColumn::make('metadata->url')->label('URL')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('updated_at')->since()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('region_code')
                    ->label('Region')
                    ->options(fn () => SkuRegion::query()->distinct()->orderBy('region_code')->pluck('region_code', 'region_code')->toArray()),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active?'),
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
            'index' => Pages\ListOffers::route('/'),
            'create' => Pages\CreateOffer::route('/create'),
            'edit' => Pages\EditOffer::route('/{record}/edit'),
        ];
    }
}
