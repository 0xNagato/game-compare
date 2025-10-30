<?php

namespace App\Filament\Resources\DatasetSnapshots\Pages;

use App\Filament\Resources\DatasetSnapshots\DatasetSnapshotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDatasetSnapshots extends ListRecords
{
    protected static string $resource = DatasetSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
