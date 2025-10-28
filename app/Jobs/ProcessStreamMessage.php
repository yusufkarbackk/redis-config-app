<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\ApplicationTableSubscription;
use App\Models\Log;
use App\Models\ProjectHelper;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log as FacadesLog;
use Illuminate\Support\Facades\Redis;
use PDO;
use Str;

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
        $helper = new ProjectHelper();
        dump("▶️  Processing message nih ya {$this->messageId}");

        try {
            //FcadesLog::info('Processing payload nih ges', $this->payload['api_key'] ?? []);
            FacadesLog::info('Processing payload', $this->payload);
            $dataId = Str::random(8);
            $app = Application::where('api_key', $this->payload['api_key'])->first();
            FacadesLog::info('Application found', ['app' => $app]);
            if (!$app) {
                FacadesLog::info('No such app found, dropping message');
                return;
            }

            // 2) Parse the true enqueue time
            $sentAt = Carbon::parse($this->payload['enqueued_at'] ?? now());

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
                //$mapped['data_id'] = $dataId;
                //dd($mapped);
                if (empty($mapped)) {
                    // nothing to insert for this table
                    continue;
                }
                // 6) Attempt to insert (or queue for retry)
                if ($helper->isDatabaseServerReachable($dbConfig->host, $dbConfig->port)) {
                    $this->insertIntoTable($dbConfig, $tableName, $mapped, $app->name, $rawData, $sentAt);
                    // 7) Log success
                    $helper->createLog($app->name, $tableName, $dbConfig, $rawData, $mapped, $sentAt);
                } else {
                    dump('hold for retry');
                    $this->holdForRetry($sub->id, $tableName, $mapped, $app->name, $rawData, $sentAt, $dbConfig->host);
                }
            }
        } catch (\Throwable $e) {
            FacadesLog::info("lah error");
            Facadeslog::error("Job processing failed", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                // Sangat direkomendasikan untuk menyertakan objek exception
                // agar Anda mendapatkan stack trace lengkap di log.
                'exception' => $e
            ]);
            return;
        }
    }

    protected function insertIntoTable($db, string $table, array $mapped, string $source, array $rawData, Carbon $sentAt): void
    {
        FacadesLog::info('Inserting into table ');
        try {
            dump('inserting into table ' . $table . " " . $db->host);
            dump($mapped);
            dump("DB Password: {$db->password}");

            $dbPassword = $db->password != null ? decrypt($db->password) : '';
            FacadesLog::info("Inserting connection type {$db->connection_type}");
            $pdo = new PDO(
                "{$db->connection_type}:host={$db->host};dbname={$db->database_name}",
                $db->username,
                $dbPassword,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $cols = implode(', ', array_keys($mapped));
            $ph = implode(', ', array_map(fn($c) => ":{$c}", array_keys($mapped)));
            dump("Cols: {$cols}");
            dump("Placeholders {$ph}");
            //die();
            //\Log::info("Inserting into table {$table}: ", [$mapped, $cols, $ph]);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO {$table} ({$cols}) VALUES ({$ph})");
            dump($stmt);
            //die();
            foreach ($mapped as $col => $val) {
                $stmt->bindValue(":{$col}", $val);
            }
            $stmt->execute();
            $pdo->commit();
        } catch (\Throwable $e) {
            FacadesLog::info('error insert');
            FacadesLog::error("Error: {$e->getMessage()} {$e->getFile()}:{$e->getLine()}");
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