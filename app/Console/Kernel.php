<?php

namespace App\Console;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Predis\Configuration\Option\Commands;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */


    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('redis:retry-failed-messages')->everyMinute();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
