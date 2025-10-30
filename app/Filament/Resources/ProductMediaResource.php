<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductMediaResource\Pages;
use App\Models\ProductMedia;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductMediaResource extends Resource
{
    protected static ?string $model = ProductMedia::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('product_id')
                ->relationship('product', 'name')
                ->required()
                ->searchable(),
            Forms\Components\TextInput::make('source')->required(),
            Forms\Components\TextInput::make('external_id')->maxLength(255),
            Forms\Components\TextInput::make('url')->required()->url(),
            Forms\Components\TextInput::make('thumbnail_url')->url(),
            Forms\Components\Toggle::make('is_primary'),
            Forms\Components\TextInput::make('width')->numeric(),
            Forms\Components\TextInput::make('height')->numeric(),
            Forms\Components\TextInput::make('quality_score')->numeric(),
            Forms\Components\TextInput::make('attribution'),
            Forms\Components\TextInput::make('license'),
            Forms\Components\TextInput::make('license_url')->url(),
            Forms\Components\DateTimePicker::make('fetched_at'),
            Forms\Components\KeyValue::make('metadata')->keyLabel('Key')->valueLabel('Value'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('url')->label('Preview')->circular()->height(48),
                TextColumn::make('product.name')->label('Game')->searchable(),
                TextColumn::make('source')->badge(),
                TextColumn::make('quality_score')->formatStateUsing(fn ($state) => number_format((float) $state, 2))->label('Quality'),
                IconColumn::make('is_primary')->boolean()->label('Primary'),
                TextColumn::make('fetched_at')->since()->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_primary')->label('Primary only'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
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
            'index' => Pages\ListProductMedia::route('/'),
            'create' => Pages\CreateProductMedia::route('/create'),
            'edit' => Pages\EditProductMedia::route('/{record}/edit'),
        ];
    }
}
