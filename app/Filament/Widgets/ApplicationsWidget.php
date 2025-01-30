<?php

namespace App\Filament\Widgets;

use App\Models\Application;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Log;

class ApplicationsWidget extends Widget
{
    protected static string $view = 'filament.widgets.applications-widget';

    protected function getStats(): array
    {
        // Get all applications with their table counts
        $applications = Application::withCount('tables')->with('tables')->get();
        Log::info($applications);
        return $applications->map(function ($application) {
            return Stat::make($application->name, $application->tables_count)
                ->description('Connected Tables')
                ->color('success');  // You can customize the color
        })->toArray();
    }
}
