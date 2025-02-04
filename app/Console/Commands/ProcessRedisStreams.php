<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Models\DatabaseConfig;
use App\Models\DatabaseTable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
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

    /**
     * Execute the console command.
     */
    public function handle()
    {
        while (true) {
            try {
                // Get all tables and its fields configurations
                $tables = DatabaseTable::with('tableFields', 'application', 'database')->get();
                //Log::info('Databases Query Result:', $tables->toArray());

                foreach ($tables as $table) {
                    $db = $table->database;
                    // Log::info('Databases Query Result:', $db->toArray());
                    // die();

                    // Get all applications this table subscribes to
                    $application = $table->application;
                    // Log::info('Databases Query Result:', $application->toArray());
                    // die();
                    try {
                        $streamKey = "app:{$application->id}:stream";
                        // Log::info('Messages:' . print_r($streamKey, true));
                        // die();
                        $groupName = $db->consumer_group;
                        // Log::info('Messages:' . print_r($groupName, true));
                        // die();
                    } catch (\Throwable $th) {
                        Log::error("Stream processing error: " . $th->getMessage() . " " . $th->getFile() . " " . $th->getLine());
                        sleep(1);
                    }
                    // Create consumer group if not exists
                    try {
                        Redis::command('xgroup', ['CREATE', $streamKey, $groupName, '$', true]);
                    } catch (\Exception $e) {
                        Log::error("Create group error: " . $th->getMessage() . " " . $th->getFile() . " " . $th->getLine());
                        sleep(1);
                    }

                    // Read new messages
                    try {
                        $messages = Redis::command('xreadgroup', [
                            $groupName,
                            "consumer:{$db->id}",
                            $streamKey,
                            1, //count
                        ]);
                        Log::info('Messages:' . print_r($messages, true));
                        if (!$messages) {
                            continue;
                        }
                    } catch (\Throwable $th) {
                        Log::error("Read messages error: " . $th->getMessage() . " " . $th->getFile() . " " . $th->getLine());
                        sleep(1);
                    }
                    //dd(array_keys($messages[$streamKey])[0]);
                    foreach ($messages as $stream => $entries) {
                        // dd(array_keys($entries)[0]);
                        foreach ($entries as $messageId => $data) {
                            // Get fields this table wants from this app
                            $wantedFields = $table->table_fields
                                ->where('application_id', $application->id)
                                ->pluck('name')
                                ->toArray();

                            // Filter data to only include subscribed fields
                            $filteredData = array_intersect_key($data, array_flip($wantedFields));

                            try {
                                // Connect to target database
                                $pdo = new PDO(
                                    "{$db->connection_type}:host={$db->host};dbname={$db->database_name}",
                                    $db->username,
                                    $db->password
                                );

                                // Insert data
                                $this->insertData($pdo, $filteredData, $table->table_name);
                                Log::info("insert data");

                                // Acknowledge message
                                Redis::command('xack', [$stream, $groupName, [$messageId]]);
                            } catch (\Exception $e) {
                                Log::error("Error processing message {$messageId}: " . $e->getMessage());
                                // Don't acknowledge - message will be reprocessed
                            }
                        }
                    }
                }
                $id = array_keys($messages[$streamKey])[0];
                Redis::command('xdel', [$streamKey, [$messageId]]);
            } catch (\Throwable $th) {
                Log::error("Stream processing error: " . $th->getMessage() . " " . $th->getFile() . " " . $th->getLine());
                sleep(1); // Prevent tight loop on error
            }
        }
    }

    private function insertData(PDO $pdo, array $data, string $table_name)
    {
        $fields = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($fields), '?');

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table_name,
            implode(',', $fields),
            implode(',', $placeholders)
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
    }
}
