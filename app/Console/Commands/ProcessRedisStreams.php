<?php

namespace App\Console\Commands;

use App\Models\ApplicationTableSubscription;
use App\Models\DatabaseFieldSubscription;
use App\Models\DatabaseTable;
use App\Models\ApplicationField;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Predis\Client as PredisClient;
use Illuminate\Support\Facades\Log;
use PDO;
class ProcessRedisStreams extends Command
{
    protected $signature = 'redis:listen-data-streams';
    protected $description = 'Listen to Redis stream and process data';

    public function handle()
    {
        $this->info('Listening to Redis stream...');

        try {
            while (true) {
                $subscriptions = ApplicationTableSubscription::with([
                    'application',
                    'databaseTable.database',
                    'fieldMappings.applicationField'
                ])->get();

                foreach ($subscriptions as $subscription) {
                    $application = $subscription->application;
                    $table = $subscription->databaseTable;
                    $db = $table->database;
                    $streamKey = "app:{$application->api_key}:stream";
                    $groupName = $subscription->consumer_group;

                    try {
                        Redis::command('xgroup', ['CREATE', $streamKey, $groupName, '$', 'MKSTREAM']);
                    } catch (\Exception $e) {
                        // Group may already exist — ignore
                    }

                    $messages = Redis::xreadgroup(
                        $groupName,
                        "consumer:{$subscription->id}",
                        [$streamKey => '>'],
                        1,
                        5000
                    );

                    if (!$messages)
                        continue;

                        foreach ($messages[$streamKey] ?? [] as $messageId => $data) {
                        $mappedData = [];

                        foreach ($subscription->fieldMappings as $mapping) {
                            $appField = $mapping->applicationField->name;
                            if (isset($data[$appField])) {
                                $mappedData[$mapping->mapped_to] = $data[$appField];
                            }
                        }

                        if (!empty($mappedData)) {
                            $this->insertData($table, $db, $mappedData, $groupName, $application->name, $messages[$streamKey][$messageId]);
                            Redis::xack($streamKey, $groupName, [$messageId]);                                                                      
                        } else {
                            $this->warn("⚠ No mappable fields found for [$messageId]");
                        }
                    }
                }

                usleep(500_000); // 0.5 sec delay
            }
        } catch (\Throwable $th) {
            $this->error("Error on line {$th->getLine()}: {$th->getMessage()} {$th->getFile()}");
        }
    }

    protected function insertData($table, $db, $data, $groupName, $source, $rawData)
    {
        if (!$this->isDatabaseServerReachable($db->host, $db->port)) {
            $this->holdMessageForRetry($table->table_name, $data, $groupName, $source, $rawData);
            dump("database is not reachable");
            return;
        }

        try {
            $pdo = new PDO(
                "{$db->connection_type}:host={$db->host};dbname={$db->database_name}",
                $db->username,
                $db->password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $sql = "INSERT INTO {$table->table_name} ($columns) VALUES ($placeholders)";

            $pdo->beginTransaction();
            $stmt = $pdo->prepare($sql);

            if ($stmt->execute(array_values($data))) {
                $pdo->commit();
               
                $formatedSentAt = Carbon::parse($rawData['enqueued_at']);
                unset($rawData['enqueued_at']);
                \App\Models\Log::create([
                    'source' => $source,
                    'destination' => $table->table_name,
                    'data_sent' => json_encode($rawData),              
                    'data_received' => json_encode($data),        
                    'sent_at' => $formatedSentAt, 
                    'received_at' => now(),
                    'message' => 'data sent from redis stream'
                ]);
            } else {
                $pdo->rollBack();
            }

        } catch (\Throwable $e) {
            dump($e->getMessage());
        }
    }

    protected function isDatabaseServerReachable($host, $port): bool
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, 2);
        return (bool) $connection;
    }

    protected function holdMessageForRetry($tableName, $data, $group, $source, $rawData)
    {
        $retryKey = "retry:{$group}";
        Redis::rpush($retryKey, json_encode(['table' => $tableName, 'data' => $data]));
        $this->warn("🔁 Message held for retry: $retryKey");
        $formatedSentAt = Carbon::parse($rawData['enqueued_at']);

        \App\Models\Log::create([
            'source' => $source,
            'destination' => $tableName,
            'data_sent' => json_encode($rawData),              // the raw $data you sent into the query
            'data_received' => [],        // the final array you inserted
            'sent_at' => $formatedSentAt,              // or the timestamp from Redis message if you embedded it
            'received_at' => now(),
            'Database not reachable, Hold on retry'
        ]);
    }
}