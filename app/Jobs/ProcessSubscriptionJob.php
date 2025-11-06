<?php

namespace App\Jobs;

use App\Models\ApplicationTableSubscription;
use App\Models\ProjectHelper;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log as FacadesLog;
use PDO;

class ProcessSubscriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ApplicationTableSubscription $subscription;
    public array $payload;
    public array $rawData;
    public array $mapped;
    public Carbon $sentAt;
    public string $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        ApplicationTableSubscription $subscription,
        array $payload,
        array $rawData,
        array $mapped,
        Carbon $sentAt
    ) {
        $this->subscription = $subscription;
        $this->payload = $payload;
        $this->rawData = $rawData;
        $this->mapped = $mapped;
        $this->sentAt = $sentAt;
        $this->jobId = 'job_' . uniqid();
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'process-subscription',
            'subscription:' . $this->subscription->id,
            'app:' . $this->subscription->application_id,
            'table:' . $this->subscription->databaseTable->table_name,
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $helper = new ProjectHelper();
        $subscription = $this->subscription;
        $dbConfig = $subscription->databaseTable->database;
        $tableName = $subscription->databaseTable->table_name;
        $appName = $subscription->application->name;

        $jobStartedAt = now();

        FacadesLog::info('Processing subscription job', [
            'job_id' => $this->jobId,
            'subscription_id' => $subscription->id,
            'application' => $appName,
            'table' => $tableName,
            'database_host' => $dbConfig->host,
            'sent_at' => $this->sentAt->toIso8601String(),
            'job_started_at' => $jobStartedAt->toIso8601String()
        ]);

        try {
            if ($helper->isDatabaseServerReachable($dbConfig->host, $dbConfig->port)) {
                $this->insertIntoTable($dbConfig, $tableName, $appName);
            } else {
                FacadesLog::warning('Database unreachable for subscription job, holding for retry', [
                    'job_id' => $this->jobId,
                    'subscription_id' => $subscription->id,
                    'database_host' => $dbConfig->host,
                    'database_port' => $dbConfig->port,
                    'table_name' => $tableName
                ]);

                $this->holdForRetry();
            }
        } catch (\Throwable $e) {
            FacadesLog::error('Subscription job processing failed', [
                'job_id' => $this->jobId,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // Hold for retry on any exception
            $this->holdForRetry($e->getMessage());
        }
    }

    protected function insertIntoTable($db, string $table, string $source): void
    {
        try {
            FacadesLog::info('Inserting into table from subscription job', [
                'job_id' => $this->jobId,
                'subscription_id' => $this->subscription->id,
                'table' => $table,
                'host' => $db->host,
                'connection_type' => $db->connection_type
            ]);

            $dbPassword = $db->password != null ? decrypt($db->password) : '';
            $pdo = new PDO(
                "{$db->connection_type}:host={$db->host};dbname={$db->database_name}",
                $db->username,
                $dbPassword,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Handle column names based on database type
            if ($db->connection_type === 'pgsql') {
                $quotedCols = array_map(fn($c) => "\"{$c}\"", array_keys($this->mapped));
                $colsList = implode(', ', $quotedCols);
                $ph = implode(', ', array_map(fn($c) => ":{$c}", array_keys($this->mapped)));
                $sql = "INSERT INTO \"{$table}\" ({$colsList}) VALUES ({$ph})";
            } else {
                $cols = implode('`, `', array_keys($this->mapped));
                $ph = implode(', ', array_map(fn($c) => ":{$c}", array_keys($this->mapped)));
                $sql = "INSERT INTO `{$table}` (`{$cols}`) VALUES ({$ph})";
            }

            FacadesLog::info('Executing insert query', [
                'job_id' => $this->jobId,
                'sql' => $sql,
                'data' => $this->mapped
            ]);

            $pdo->beginTransaction();
            $stmt = $pdo->prepare($sql);

            foreach ($this->mapped as $col => $val) {
                $stmt->bindValue(":{$col}", $val);
            }

            $stmt->execute();
            $pdo->commit();

            // Log success
            (new ProjectHelper())->createLog(
                $source,
                $table,
                $db,
                $this->rawData,
                $this->mapped,
                $this->sentAt
            );

            FacadesLog::info('Subscription job completed successfully', [
                'job_id' => $this->jobId,
                'subscription_id' => $this->subscription->id,
                'table' => $table,
                'rows_affected' => $stmt->rowCount()
            ]);

        } catch (\Throwable $e) {
            $pdo->rollBack();
            FacadesLog::error('Insert operation failed in subscription job', [
                'job_id' => $this->jobId,
                'subscription_id' => $this->subscription->id,
                'table' => $table,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'data' => $this->mapped
            ]);
            throw $e;
        }
    }

    protected function holdForRetry(string $error = 'database unreachable'): void
    {
        $subscription = $this->subscription;
        $tableName = $subscription->databaseTable->table_name;
        $appName = $subscription->application->name;
        $dbConfig = $subscription->databaseTable->database;

        // Use Redis to store retry data
        $retryKey = "retry:subscription:{$subscription->id}";

        $entry = [
            'table' => $tableName,
            'data' => $this->mapped,
            'source' => $appName,
            'raw_data' => $this->rawData,
            'sent_at' => $this->sentAt->toDateTimeString(),
            'error' => $error,
            'job_id' => $this->jobId,
            'retry_at' => now()->addMinute()->toDateTimeString(),
        ];

        // Push to retry list
        \Illuminate\Support\Facades\Redis::rpush($retryKey, json_encode($entry));

        // Log retry hold
        \App\Models\Log::create([
            'source' => $appName,
            'destination' => $tableName,
            'host' => $dbConfig->host,
            'data_sent' => json_encode($this->rawData),
            'data_received' => json_encode([]),
            'sent_at' => $this->sentAt,
            'received_at' => now(),
            'status' => 'RETRY',
            'message' => "held for retry: {$error}",
        ]);

        FacadesLog::info('Job held for retry', [
            'job_id' => $this->jobId,
            'subscription_id' => $subscription->id,
            'retry_key' => $retryKey,
            'error' => $error,
            'retry_at' => $entry['retry_at']
        ]);
    }
}