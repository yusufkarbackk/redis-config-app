<?php

namespace App\Console\Commands;

use App\Models\DatabaseConfig;
use App\Models\DatabaseTable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use PDO;

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
        $redisProcessStream = new ProcessRedisStreams();
        $tables = DatabaseTable::all();

        foreach ($tables as $table) {
            $consumerGroup = $table->consumer_group;
            $retryKey = "retry:{$consumerGroup}";

            // Check if messages are waiting for this consumer group
            while (Redis::llen($retryKey) > 0) {

                if ($redisProcessStream->isDatabaseOnline($table->database->host)) {
                    dump("Database {$table->database->name} is still down. Holding message...");
                } else {
                    dump("Database {$table->database->name} is up");
                    $message = json_decode(Redis::lpop($retryKey), true);
                    $targetTable = DatabaseTable::where('table_name', $message['table'])->first();

                    try {
                        $pdo = new PDO(
                            "{$table->database->connection_type}:host={$table->database->host};dbname={$table->database->database_name}",
                            $table->database->username,
                            $table->database->password,
                            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                        );

                        //var_dump($pdo);

                        // Build insert query
                        $columns = implode(', ', array_keys($message['table']));
                        //var_dump($columns);
                        //Log::info("column" . $columns);
                        $values = implode(', ', array_fill(0, count($message['table']), '?'));
                        //var_dump($values);
                        //Log::info("column" . $values);


                        $sql = "INSERT INTO {$table->table_name} ({$columns}) VALUES ({$values})";
                        //var_dump($sql);
                        $stmt = $pdo->prepare($sql);
                        //var_dump($stmt);
                        if ($stmt->execute(array_values($message['table']))) {
                            //var_dump("Insert success");
                        } else {
                            //var_dump($stmt->errorInfo());
                        }

                        return $pdo->lastInsertId();
                    } catch (\Exception $th) {
                        dump($th->getMessage());
                        //throw $th;
                    }
                }

                // $message = json_decode(Redis::lpop($retryKey), true);
                // $targetTable = DatabaseTable::where('table_name', $message['table'])->first();

                // if ($targetTable) {
                //     if (!$redisProcessStream->isDatabaseOnline($table->database->host)) {
                //         dump("Database {$table->database->name} is still down. Holding message...");
                //     } else {
                //         $redisProcessStream->insertData($table, $message['data']);
                //     }
                // }
            }
        }
    }
}
