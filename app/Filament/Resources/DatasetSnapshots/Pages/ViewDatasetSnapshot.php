<?php

namespace App\Filament\Resources\DatasetSnapshots\Pages;

use App\Filament\Resources\DatasetSnapshots\DatasetSnapshotResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDatasetSnapshot extends ViewRecord
{
    protected static string $resource = DatasetSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
