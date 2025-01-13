<?php

namespace App\Filament\Resources\DatabaseConfigResource\Pages;

use App\Filament\Resources\DatabaseConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDatabaseConfigs extends ListRecords
{
    protected static string $resource = DatabaseConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
