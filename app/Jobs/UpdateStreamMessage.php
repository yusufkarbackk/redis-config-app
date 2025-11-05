<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\ApplicationTableSubscription;
use App\Models\ProjectHelper;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Log;
use PDO;
use Str;

class UpdateStreamMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $messageId;
    public array $payload;
    public Carbon $jobStartedAt;
    public Carbon $processingStartedAt;
    public ?string $streamProcessingStartedAt;

    /**
     * Create a new job instance.
     */
    public function __construct(string $messageId, array $payload)
    {
        $this->messageId = $messageId;
        $this->payload = $payload;
        $this->jobStartedAt = now();
        $this->processingStartedAt = now();
        $this->streamProcessingStartedAt = $payload['processing_started_at'] ?? null;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $jobProcessingStartedAt = now();
        $helper = new ProjectHelper();

        try {
            Log::info('Processing update stream message', [
                'message_id' => $this->messageId,
                'stream_processing_started_at' => $this->streamProcessingStartedAt,
                'job_started_at' => $this->jobStartedAt->toIso8601String(),
                'processing_started_at' => $this->processingStartedAt->toIso8601String(),
                'job_processing_started_at' => $jobProcessingStartedAt->toIso8601String()
            ]);

            $dataId = $this->payload['data_id'];
            $app = Application::where('api_key', $this->payload['api_key'] ?? null)->first();

            if (!$app) {
                Log::warning('No such app found, dropping update message', [
                    'api_key' => $this->payload['api_key'] ?? 'not_provided',
                    'message_id' => $this->messageId
                ]);
                return;
            }

            Log::info('Application found for update', [
                'app_id' => $app->id,
                'app_name' => $app->name,
                'message_id' => $this->messageId,
                'data_id' => $dataId
            ]);

            // Use multiple timestamps for better timing analysis
            $enqueuedAt = Carbon::parse($this->payload['enqueued_at'] ?? now());
            $sentAt = $this->streamProcessingStartedAt
                ? Carbon::parse($this->streamProcessingStartedAt)
                : $enqueuedAt; // Fallback to enqueued_at if processing_started_at not available

            // Calculate timing metrics
            $queueDelay = $sentAt->diffInSeconds($enqueuedAt);
            $processingDelay = $jobProcessingStartedAt->diffInSeconds($sentAt);

            Log::info('Update timing metrics calculated', [
                'message_id' => $this->messageId,
                'data_id' => $dataId,
                'enqueued_at' => $enqueuedAt->toIso8601String(),
                'sent_at' => $sentAt->toIso8601String(),
                'job_started_at' => $this->jobStartedAt->toIso8601String(),
                'job_processing_started_at' => $jobProcessingStartedAt->toIso8601String(),
                'queue_delay_seconds' => $queueDelay,
                'processing_delay_seconds' => $processingDelay
            ]);

            // 3) Remove meta-fields so data_sent is just business data
            $rawData = collect($this->payload)
                ->except(['api_key', 'enqueued_at'])
                ->toArray();
            //dd($rawData);
            // 4) Load all table subscriptions for this app
            $subscriptions = ApplicationTableSubscription::with([
                'databaseTable.database',
                'fieldMappings.applicationField',
            ])->where('application_id', $app->id)->get();

            foreach ($subscriptions as $sub) {
                $dbConfig = $sub->databaseTable->database;
                $tableName = $sub->databaseTable->table_name;
                // dump("sub id: {$sub->id}");
                // 5) Build the mapped payload for this table
                $mapped = [];
                foreach ($sub->fieldMappings as $mapping) {
                    $appFieldName = $mapping->applicationField->name;
                    if (isset($this->payload[$appFieldName])) {
                        $mapped[$mapping->mapped_to] = $this->payload[$appFieldName];
                    }
                }
                $mapped['data_id'] = $dataId;
                //dd($mapped);
                if (empty($mapped)) {
                    // nothing to insert for this table
                    continue;
                }
                // 6) Attempt to insert (or queue for retry)
                if ($helper->isDatabaseServerReachable($dbConfig->host, $dbConfig->port)) {
                    $this->updateIntoTable($dbConfig, $tableName, $mapped, $app->name, $rawData, $sentAt);
                    // 7) Log success
                    $helper->createLog($app->name, $tableName, $dbConfig, $rawData, $mapped, $sentAt, 'updated successfully');
                } else {
                    Log::warning('Database unreachable for update, holding for retry', [
                        'message_id' => $this->messageId,
                        'data_id' => $dataId,
                        'database_host' => $dbConfig->host,
                        'database_port' => $dbConfig->port,
                        'table_name' => $tableName
                    ]);
                    $this->holdForRetry($sub->id, $tableName, $mapped, $app->name, $rawData, $sentAt, $dbConfig->host);
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    protected function updateIntoTable($db, string $table, array $mapped, string $source, array $rawData, Carbon $sentAt): void
    {
        Log::info('Updating into table ');
        try {
            dump('updating into table ' . $table . " " . $db->host);
            dump($mapped);
            // dump("DB Password: {$db->password}");

            $dbPassword = $db->password != null ? decrypt($db->password) : '';
            $pdo = new PDO(
                "{$db->connection_type}:host={$db->host};dbname={$db->database_name}",
                $db->username,
                $dbPassword,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // $cols = implode(', ', array_keys($mapped));
            // $ph = implode(', ', array_map(fn($c) => ":{$c}", array_keys($mapped)));
            // dump("Cols: {$cols}");
            // dump("Placeholders {$ph}");
            $set = implode(', ', array_map(fn($c) => "{$c} = :{$c}", array_keys($mapped)));

            //die();
            //\Log::info("Inserting into table {$table}: ", [$mapped, $cols, $ph]);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE {$table} SET {$set} WHERE data_id = :data_id");
            //dump($stmt);
            //die();
            foreach ($mapped as $col => $val) {
                $stmt->bindValue(":{$col}", $val);
            }
            $stmt->execute();
            $pdo->commit();
        } catch (\Throwable $e) {
            Log::error("Error: {$e->getMessage()} {$e->getFile()}:{$e->getLine()}");
        }
    }

    protected function holdForRetry(
        int $subscriptionId,
        string $table,
        array $mapped,
        string $source,
        array $rawData,
        Carbon $sentAt,
        string $host,
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
            'host' => $host,
            'data_sent' => json_encode($rawData),
            'data_received' => json_encode([]),
            'sent_at' => $sentAt,
            'received_at' => now(),
            'status' => 'RETRY',
            'message' => "held for retry: {$error}",
        ]);
    }
}
