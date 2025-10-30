<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DatasetSnapshotResource\Pages;
use App\Models\DatasetSnapshot;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DatasetSnapshotResource extends Resource
{
    protected static ?string $model = DatasetSnapshot::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $label = 'Job Snapshot';

    protected static ?string $pluralLabel = 'Job Snapshots';

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('kind')->disabled(),
            Forms\Components\TextInput::make('provider')->disabled(),
            Forms\Components\Select::make('status')->options([
                'pending' => 'Pending',
                'running' => 'Running',
                'succeeded' => 'Succeeded',
                'failed' => 'Failed',
            ]),
            Forms\Components\DateTimePicker::make('started_at'),
            Forms\Components\DateTimePicker::make('finished_at'),
            Forms\Components\TextInput::make('row_count')->numeric(),
            Forms\Components\Textarea::make('context')->rows(4)->disabled(),
            Forms\Components\Textarea::make('error_details')->rows(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')->badge()->color('info')->sortable()->searchable(),
                TextColumn::make('provider')->searchable(),
                BadgeColumn::make('status')->colors([
                    'warning' => 'pending',
                    'info' => 'running',
                    'success' => 'succeeded',
                    'danger' => 'failed',
                ]),
                TextColumn::make('row_count')->label('Rows')->sortable(),
                TextColumn::make('started_at')->since()->label('Started'),
                TextColumn::make('finished_at')->since()->label('Finished')->toggleable(),
                TextColumn::make('created_at')->since()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'running' => 'Running',
                    'succeeded' => 'Succeeded',
                    'failed' => 'Failed',
                ]),
                Tables\Filters\SelectFilter::make('kind')->options(fn () => DatasetSnapshot::query()->distinct()->pluck('kind', 'kind')->toArray()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDatasetSnapshots::route('/'),
            'view' => Pages\ViewDatasetSnapshot::route('/{record}'),
        ];
    }
}
