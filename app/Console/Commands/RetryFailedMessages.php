<?php

namespace App\Console\Commands;

use App\Models\DatabaseConfig;
use App\Models\DatabaseTable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class RetryFailedMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:retry-failed-messages';

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
        $databases = DatabaseConfig::all();

        foreach ($databases as $db) {
            if ($this->isDatabaseOnline($db)) {
                $retryKey = "retry:{$db->name}";

                while (Redis::llen($retryKey) > 0) {
                    $message = json_decode(Redis::lpop($retryKey), true);
                    $table = DatabaseTable::where('table_name', $message['table'])->first();

                    if ($table) {
                        $this->insertData($table, $message['data']);
                    }
                }
            }
        }
    }
}
