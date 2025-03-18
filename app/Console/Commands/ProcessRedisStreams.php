<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Models\DatabaseConfig;
use App\Models\DatabaseTable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Models\Log as appLog;

use Predis\Client as PredisClient;
use PDO;

class ProcessRedisStreams extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:process-redis-streams';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
    //protected $log = new appLog();
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $logData = [];
        //$log = new appLog();
        $this->info('Starting Redis stream processing...');

        while (true) {
            try {
                // Get all tables with relationships
                $tables = DatabaseTable::with('tableFields', 'application', 'database')->get();
                // Log::info($tables->toArray());
                foreach ($tables as $table) {
                    $db = $table->database;
                    $application = $table->application;
                    $dataToLog = [
                        'source' => $application->name,
                        'destination' => $table->database->name,
                    ];

                    try {
                        $streamKey = "app:{$application->api_key}:stream";
                        $groupName = $db->consumer_group;

                        // Create consumer group if not exists
                        try {
                            Redis::command('xgroup', ['CREATE', $streamKey, $groupName, '$', 'MKSTREAM']);
                        } catch (\Exception $e) {
                            // Group may already exist - that's fine
                        }

                        // Read new messages using XREADGROUP
                        $messages = Redis::xreadgroup(
                            $groupName,
                            "consumer:{$db->id}",
                            [$streamKey => '>'],
                            1,
                            5000
                        );

                        var_dump($messages);

                        if (!$messages) {
                            continue;
                        }
                        dump($messages[$streamKey]);
                        $dataToLog['data_sent'] = json_encode($messages[$streamKey]);
                        $dataToLog['sent_at'] = now();

                        $this->processMessages($messages, $table, $streamKey, $groupName, $dataToLog);
                    } catch (\Throwable $th) {

                        sleep(1);
                    }
                }
            } catch (\Throwable $th) {

                sleep(1);
            }
        }
    }

    private function processMessages($messages, $table, $streamKey, $groupName, $dataToLog)
    {
        $log = new appLog();
        var_dump("process message");

        foreach ($messages[$streamKey] ?? [] as $messageId => $data) {
            try {
                if (!$table->tableFields) {
                    //$log->log = "No table fields found for table {$table->table_name}";
                    //$log->save();
                    continue;
                }

                // Get fields this table wants from this app
                $wantedFields = $table->tableFields
                    ->filter(function ($field) use ($table) {
                        return $field->application_id == $table->application_id;
                    })
                    ->pluck('field_name')
                    ->toArray();

                //var_dump($wantedFields);

                if (empty($wantedFields)) {
                    continue;
                }

                $filteredData = array_intersect_key($data, array_flip($wantedFields));
                $dataToLog['data_received'] = json_encode($filteredData);
                //var_dump($filteredData);

                if (!empty($filteredData)) {
                    var_dump($this->insertData($table, $filteredData));
                    $dataToLog['received_at'] = now();
                    dump($dataToLog);
                    try {
                        \App\Models\Log::create($dataToLog);
                    } catch (\Throwable $th) {
                        //throw $th;
                        dump($th->getMessage());
                    }

                    // Acknowledge message
                    Redis::command('xack', [$streamKey, $groupName, [$messageId]]);

                    var_dump("Processed message {$messageId} for table {$table->table_name}");
                }
            } catch (\Exception $e) {

            }
        }
    }

    public function insertData($table, $data)
    {
        //var_dump("insert data");

        //var_dump($data);

        $db = $table->database;

        $pdo = new PDO(
            "{$db->connection_type}:host={$db->host};dbname={$db->database_name}",
            $db->username,
            $db->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        //var_dump($pdo);

        // Build insert query
        $columns = implode(', ', array_keys($data));
        //var_dump($columns);
        //Log::info("column" . $columns);
        $values = implode(', ', array_fill(0, count($data), '?'));
        //var_dump($values);
        //Log::info("column" . $values);


        $sql = "INSERT INTO {$table->table_name} ({$columns}) VALUES ({$values})";
        //var_dump($sql);
        $stmt = $pdo->prepare($sql);
        //var_dump($stmt);
        if ($stmt->execute(array_values($data))) {
            //var_dump("Insert success");
        } else {
            //var_dump($stmt->errorInfo());
        }

        return $pdo->lastInsertId();
    }
}
