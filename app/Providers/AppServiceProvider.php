<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use App\Queue\RedisStreamConnector;
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
    public function boot(): void
    {
        Model::unguard();
        Queue::extend('redis-stream', fn() => new RedisStreamConnector);
    }
}
