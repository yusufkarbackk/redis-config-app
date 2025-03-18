<?php

namespace App\Filament\Resources\LogResource\Pages;

use App\Filament\Resources\LogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewLog extends ViewRecord
{
    protected static string $resource = LogResource::class;

    protected function getActions(): array
    {
        return []; // Removes Edit/Delete buttons to make it read-only
    }
}