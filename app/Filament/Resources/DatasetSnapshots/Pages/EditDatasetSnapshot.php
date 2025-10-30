<?php

namespace App\Filament\Resources\DatasetSnapshots\Pages;

use App\Filament\Resources\DatasetSnapshots\DatasetSnapshotResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDatasetSnapshot extends EditRecord
{
    protected static string $resource = DatasetSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
