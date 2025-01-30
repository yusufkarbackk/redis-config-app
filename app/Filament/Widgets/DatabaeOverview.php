<?php

namespace App\Filament\Widgets;

use App\Models\Application;
use App\Models\DatabaseConfig;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DatabaeOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Databases', DatabaseConfig::query()->count()),
            Stat::make('Applications', Application::query()->count()),

        ];
    }
}
