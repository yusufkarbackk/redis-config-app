<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\ApplicationTableSubscription;
use App\Models\Log;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use PDO;

class ProcessStreamMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $messageId;
    public array $payload;
    /**
     * Create a new job instance.
     */
    public function __construct(string $messageId, array $payload)
    {
        // Initialize the job with the message ID and payload
        $this->messageId = $messageId;
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            \Log::info('Processing payload', $this->payload);
            $app = Application::where('api_key', $this->payload['api_key'] ?? null)->first();
            if (!$app) {
                return;
            }

            $sentAt = Carbon::parse($this->payload['enqueued_at'] ?? now());

            $rawData = collect($this->payload)
                ->except(['api_key', 'enqueued_at'])
                ->toArray();

            $subscriptions = ApplicationTableSubscription::with([
                'databaseTable.database',
                'fieldMappings.applicationField',
            ])->where('application_id', $app->id)->get();
            //dump($subscriptions->count());
            foreach ($subscriptions as $sub) {
                $dbConfig = $sub->databaseTable->database;
                $tableName = $sub->databaseTable->table_name;
                $consumer = $sub->consumer_group;
                //dump("sub id: {$sub->id}");
                $mapped = [];
                foreach ($sub->fieldMappings as $mapping) {
                    $appFieldName = $mapping->applicationField->name;
                    if (isset($this->payload[$appFieldName])) {
                        $mapped[$mapping->mapped_to] = $this->payload[$appFieldName];
                    }
                }

                if (empty($mapped)) {
                    // nothing to insert for this table
                    continue;
                }
                //dump($mapped);
                if ($this->isDatabaseServerReachable($dbConfig->host, $dbConfig->port)) {
                    $this->insertIntoTable($dbConfig, $tableName, $mapped, $app->name, $rawData, $sentAt);
                } else {
                    \Log::warning("Database unreachable for sub {$sub->id} - holding for retry", [
                        'db_host' => $dbConfig->host,
                        'db_port' => $dbConfig->port,
                    ]);
                    $this->holdForRetry($consumer, $tableName, $mapped, $app->name, $rawData, $sentAt, $sub->id);
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
            \Log::error("Error processing message {$this->messageId}: {$th->getMessage()}", [
                'payload' => $this->payload,
                'trace' => $th->getTraceAsString(),
            ]);
        }
    }

    protected function insertIntoTable($db, string $table, array $mapped, string $source, array $rawData, Carbon $sentAt): void
    {
        $pdo = new PDO(
            "{$db->connection_type}:host={$db->host};dbname={$db->database_name}",
            $db->username,
            $db->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        try {
            $cols = implode(', ', array_keys($mapped));
            $ph = implode(', ', array_map(fn($c) => ":{$c}", array_keys($mapped)));

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO {$table} ({$cols}) VALUES ({$ph})");
            foreach ($mapped as $col => $val) {
                $stmt->bindValue(":{$col}", $val);
            }
            $stmt->execute();
            $pdo->commit();

            // 7) Log success
            Log::create([
                'source' => $source,
                'destination' => $table,
                'data_sent' => json_encode($rawData),
                'data_received' => json_encode($mapped),
                'sent_at' => $sentAt,
                'received_at' => now(),
                'status' => 'OK',
                'message' => 'inserted successfully',
            ]);
        } catch (\Throwable $e) {
            \Log::info("Error: {$e->getMessage()}");
        }
    }

    protected function holdForRetry(
        int $subscriptionId,
        string $table,
        array $mapped,
        string $source,
        array $rawData,
        Carbon $sentAt,
        string $error = 'database unreachable',
    ): void {
        // 1) key per‐subscription
        $retryKey = "retry:subscription:{$subscriptionId}";

        // 2) isi yang akan kita retry nanti
        $entry = [
            'table' => $table,
            'data' => $mapped,
            'source' => $source,
            'raw_data' => $rawData,
            'sent_at' => $sentAt->toDateTimeString(),
            'error' => $error,
        ];

        // 3) push ke list
        Redis::rpush($retryKey, json_encode($entry));

        // 4) log ke DB
        \App\Models\Log::create([
            'source' => $source,
            'destination' => $table,
            'data_sent' => json_encode($rawData),
            'data_received' => json_encode([]),
            'sent_at' => $sentAt,
            'received_at' => now(),
            'status' => 'RETRY',
            'message' => "held for retry: {$error}",
        ]);
    }


    protected function isDatabaseServerReachable(string $host, int $port): bool
    {
        $conn = @fsockopen($host, $port, $errno, $errstr, 2);
        if ($conn) {
            fclose($conn);
            return true;
        }
        return false;
    }

}
