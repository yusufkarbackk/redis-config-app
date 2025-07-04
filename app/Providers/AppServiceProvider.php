<?php

namespace App\Providers;

use App\queue\RedisStreamConnector;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Queue;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(QueueManager $manager): void
    {
        Model::unguard();
        
        $manager->addConnector('redis-stream', function () {
            return new RedisStreamConnector;
        });
    }
}
