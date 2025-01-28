<?php

namespace App\Console\Commands;

use App\Models\Application;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ProcessRedisData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-redis-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $streamKey = 'app:1:stream';
        $messages = Redis::command('xread', ['COUNT', 10, 'STREAMS', $streamKey, '>']);
        foreach ($messages as $message) {
            $data = $message[1][0];
            $application = Application::where('api_key', 1)->first();
            $validFields = $application->applicationFields()->pluck('name')->toArray();
            $filteredData = array_intersect_key($data, array_flip($validFields));
            $application->tables()->create($filteredData);
        }
    }
}
