<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Widgets\DatabaseStatusOverview;
use App\Filament\Widgets\DatabaeOverview;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = 0;

    protected function getHeaderWidgets(): array
    {
        return [
            DatabaseStatusOverview::class,
            DatabaeOverview::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            // Custom footer widgets if needed
        ];
    }

    public function getColumns(): int
    {
        return 2;
    }

    public function getWidgets(): array
    {
        return [
            DatabaseStatusOverview::class,
            DatabaeOverview::class,
        ];
    }

    // Override the default behavior to remove the Filament version widget
    public function getHeaderWidgetsColumns(): int
    {
        return 2;
    }
}