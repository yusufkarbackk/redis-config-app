<?php

namespace App\Filament\Resources\DatabaseConfigResource\Pages;

use App\Filament\Resources\DatabaseConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDatabaseConfig extends CreateRecord
{
    protected static string $resource = DatabaseConfigResource::class;
}
