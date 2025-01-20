<?php

namespace App\Console\Commands;

require 'vendor/autoload.php';

use App\Models\DatabaseConfig;
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
        $redis = new Redis();

        while (true) {
            try {
                // Get all active database configurations
                $databases = DatabaseConfig::with('fields')->get();
                // Log::info('Databases Query Result:', $databases->toArray());
                // die();
                // Process each database's subscriptions
                foreach ($databases as $db) {
                    // Get all applications this database subscribes to
                    $applications = $db->fields
                        ->pluck('application')
                        ->unique('id');
                    // Log::info('Databases Query Result:', $applications->toArray());
                    // die();
                    foreach ($applications as $app) {
                        try {
                            $streamKey = "app:{$app->id}:stream";
                            $groupName = $db->consumer_group;
                        } catch (\Throwable $th) {
                            Log::error("Stream processing error: " . $th->getMessage() . " " . $th->getFile() . " " . $th->getLine());
                            sleep(1);
                        }
                        // Create consumer group if not exists
                        try {
                            Redis::command('xgroup', ['CREATE', $streamKey, $groupName, '0', 'MKSTREAM']);
                        } catch (\Exception $e) {
                            Log::error("Create group error: " . $th->getMessage() . " " . $th->getFile() . " " . $th->getLine());
                            sleep(1);
                        }

                        // Read new messages
                        try {
                            $messages = Redis::command('xreadgroup', [
                                $groupName,
                                "consumer:{$db->id}",
                                [$streamKey => '>'],
                                1, //count
                                0 //block timeout
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
                                // Get fields this database wants from this app
                                $wantedFields = $db->fields
                                    ->where('application_id', $app->id)
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
                                    $this->insertData($pdo, $filteredData);
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
                }
                $id = array_keys($messages[$streamKey])[0];
                Redis::command('xdel', [$streamKey, [$messageId]]);
            } catch (\Throwable $th) {
                Log::error("Stream processing error: " . $th->getMessage() . " " . $th->getFile() . " " . $th->getLine());
                sleep(1); // Prevent tight loop on error
            }
        }
    }

    private function insertData(PDO $pdo, array $data)
    {
        $fields = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($fields), '?');

        $sql = sprintf(
            "INSERT INTO incoming_data (%s) VALUES (%s)",
            implode(',', $fields),
            implode(',', $placeholders)
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
    }
}
