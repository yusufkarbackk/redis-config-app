<?php

namespace App\Filament\Widgets;

use App\Models\DatabaseConfig;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DatabaseStatusOverview extends BaseWidget
{
    protected ?string $heading = 'Database Status Overview';    

    protected function getStats(): array
    {

        $databases = DatabaseConfig::all();

        $up = 0;
        $down = 0;

        foreach ($databases as $database) {
            if ($database->status === 'up') {
                $up++;
            } else {
                $down++;
            }
        }

        return [
            Stat::make('Database UP', $up)
                ->description('up')
                ->color('success'),

            Stat::make('Database DOWN', $down)
                ->description('down')
                ->color('danger'),
        ];

    }
}
