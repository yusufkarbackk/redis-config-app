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
        $log = new appLog();
        $this->info('Starting Redis stream processing...');

        while (true) {
            try {
                // Get all tables with relationships
                $tables = DatabaseTable::with('tableFields', 'application', 'database')->get();
                // Log::info($tables->toArray());
                foreach ($tables as $table) {
                    $db = $table->database;
                    $application = $table->application;

                    try {
                        $streamKey = "app:{$application->api_key}:stream";
                        $groupName = $db->consumer_group;

                        // Create consumer group if not exists
                        try {
                            Redis::command('xgroup', ['CREATE', $streamKey, $groupName, '$', 'MKSTREAM']);
                        } catch (\Exception $e) {
                            // Group may already exist - that's fine
                            //$log->log = "Error: " . $e->getMessage();
                            //$log->save();

                            //Log::error("Consumer group exists or error: " . $e->getMessage());
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

                        $this->processMessages($messages, $table, $streamKey, $groupName);
                    } catch (\Throwable $th) {
                        // $log->log = "Error: " . $th->getMessage();
                        // $log->save();

                        //Log::error("Stream error for {$table->table_name}: " . $th->getMessage() . $th->getLine());
                        sleep(1);
                    }
                }
            } catch (\Throwable $th) {
                // $log->log = "Error: " . $th->getMessage();
                // $log->save();

                //Log::error("Main loop error: " . $th->getMessage());
                sleep(1);
            }
        }
    }

    private function processMessages($messages, $table, $streamKey, $groupName)
    {
        $log = new appLog();
        var_dump("process message");

        foreach ($messages[$streamKey] ?? [] as $messageId => $data) {
            try {
                if (!$table->tableFields) {
                    //$log->log = "No table fields found for table {$table->table_name}";
                    //$log->save();

                    //Log::error("No table fields found for table {$table->table_name}");
                    continue;
                }

                // Get fields this table wants from this app
                $wantedFields = $table->tableFields
                    ->filter(function ($field) use ($table) {
                        return $field->application_id == $table->application_id;
                    })
                    ->pluck('field_name')
                    ->toArray();

                var_dump($wantedFields);

                if (empty($wantedFields)) {
                    //$log->log = "No fields configured for table {$table->table_name} and application {$table->application_id}";
                    //$log->save();

                    //Log::warning("No fields configured for table {$table->table_name} and application {$table->application_id}");
                    continue;
                }

                $filteredData = array_intersect_key($data, array_flip($wantedFields));
                var_dump($filteredData);

                if (!empty($filteredData)) {
                    // Insert data into target database
                    //Log::info($table->toArray());


                    //var_dump($table->database);

                    var_dump($this->insertData($table, $filteredData));

                    // Acknowledge message
                    Redis::command('xack', [$streamKey, $groupName, [$messageId]]);

                    var_dump("Processed message {$messageId} for table {$table->table_name}");
                    //$log->log = "success proccessing message";
                    //$log->save();
                }
            } catch (\Exception $e) {
                //$log->log = "Error processing message {$messageId}: " . $e->getMessage() . $e->getLine();
                //$log->save();
                //Log::error("Error processing message {$messageId}: " . $e->getMessage() . $e->getLine());
                // Don't acknowledge - message will be reprocessed
            }
        }
    }

    public function insertData($table, $data)
    {
        var_dump("insert data");

        var_dump($data);

        $db = $table->database;

        //var_dump($db->connection_type);
        //var_dump($db->host);
        //var_dump($db->database_name);
        //var_dump($db->username);
        //var_dump($db->password);
        //Log::info($db->toArray());

        $pdo = new PDO(
            "{$db->connection_type}:host={$db->host};dbname={$db->database_name}",
            $db->username,
            $db->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        var_dump($pdo);

        // Build insert query
        $columns = implode(', ', array_keys($data));
        var_dump($columns);
        //Log::info("column" . $columns);
        $values = implode(', ', array_fill(0, count($data), '?'));
        var_dump($values);
        //Log::info("column" . $values);


        $sql = "INSERT INTO {$table->table_name} ({$columns}) VALUES ({$values})";
        var_dump($sql);
        $stmt = $pdo->prepare($sql);
        var_dump($stmt);
        if ($stmt->execute(array_values($data))) {
            var_dump("Insert success");
        } else {
            var_dump($stmt->errorInfo());
        }

        return $pdo->lastInsertId();
    }
}
