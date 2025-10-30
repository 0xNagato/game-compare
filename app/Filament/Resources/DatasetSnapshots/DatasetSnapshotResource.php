<?php

namespace App\Filament\Resources\DatasetSnapshots;

use App\Filament\Resources\DatasetSnapshots\Pages\CreateDatasetSnapshot;
use App\Filament\Resources\DatasetSnapshots\Pages\EditDatasetSnapshot;
use App\Filament\Resources\DatasetSnapshots\Pages\ListDatasetSnapshots;
use App\Filament\Resources\DatasetSnapshots\Pages\ViewDatasetSnapshot;
use App\Filament\Resources\DatasetSnapshots\Schemas\DatasetSnapshotForm;
use App\Filament\Resources\DatasetSnapshots\Schemas\DatasetSnapshotInfolist;
use App\Filament\Resources\DatasetSnapshots\Tables\DatasetSnapshotsTable;
use App\Models\DatasetSnapshot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DatasetSnapshotResource extends Resource
{
    protected static ?string $model = DatasetSnapshot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'DatasetSnapshot';

    public static function form(Schema $schema): Schema
    {
        return DatasetSnapshotForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DatasetSnapshotInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DatasetSnapshotsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDatasetSnapshots::route('/'),
            'create' => CreateDatasetSnapshot::route('/create'),
            'view' => ViewDatasetSnapshot::route('/{record}'),
            'edit' => EditDatasetSnapshot::route('/{record}/edit'),
        ];
    }
}
