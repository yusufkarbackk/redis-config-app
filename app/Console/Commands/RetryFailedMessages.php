<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDO;
use App\Models\ApplicationTableSubscription;

class RetryFailedMessages extends Command
{
    protected $signature = 'redis:retry-failed-messages';
    protected $description = 'Retry messages that previously failed per subscription/table';

    public function handle()
    {
        Log::info('RetryFailedMessages started at ' . now());

        // Eager-load relationships to avoid N+1
        $subscriptions = ApplicationTableSubscription::with([
            'application',
            'databaseTable.database',
        ])->get();

        foreach ($subscriptions as $sub) {
            // Use a unique retry list per subscription
            $retryKey = "retry:subscription:{$sub->id}";
            $entries = Redis::lrange($retryKey, 0, -1);

            if (empty($entries)) {
                continue;
            }

            $appName = $sub->application->name;
            $dbConfig = $sub->databaseTable->database;      // your DatabaseConfig model
            $tableName = $sub->databaseTable->table_name;

            if (!$this->isDatabaseServerReachable($dbConfig->host, $dbConfig->port)) {
                $this->warn("↻ {$dbConfig->name} still down; skipping retry for subscription #{$sub->id}");
                continue;
            }

            // One PDO per subscription
            $pdo = $this->makePdo($dbConfig);

            foreach ($entries as $json) {
                $payload = json_decode($json, true);
                $data = $payload['data'] ?? [];

                if (empty($data)) {
                    // nothing to re-insert → drop it
                    Redis::lrem($retryKey, 0, $json);
                    continue;
                }

                // Build INSERT
                $cols = array_keys($data);
                $placeholders = array_map(fn($c) => ":{$c}", $cols);
                $sql = sprintf(
                    'INSERT INTO `%s` (%s) VALUES (%s)',
                    $tableName,
                    implode(',', $cols),
                    implode(',', $placeholders)
                );

                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare($sql);
                    foreach ($data as $col => $val) {
                        $stmt->bindValue(":{$col}", $val);
                    }
                    $stmt->execute();
                    $pdo->commit();

                    // Log success
                    \App\Models\Log::create([
                        'source' => $appName,
                        'destination' => $tableName,
                        'data_sent' => json_encode($data),
                        'data_received' => json_encode($data),
                        'sent_at' => now(),
                        'received_at' => now(),
                        'status' => 'OK',
                        'message' => 'retried successfully',
                    ]);

                    // Remove from retry list
                    Redis::lrem($retryKey, 0, $json);
                    $this->info("✔ Retried subscription #{$sub->id} → {$tableName}");
                } catch (\Throwable $e) {
                    $pdo->rollBack();
                    $this->error("✖ Retry failed for subscription #{$sub->id} on {$tableName}: " . $e->getMessage());
                }
            }

            // Clear the list key when empty
            if (empty(Redis::lrange($retryKey, 0, -1))) {
                Redis::del($retryKey);
                $this->info("🗑️ Cleared retry list for subscription #{$sub->id}");
            }
        }

        Log::info('RetryFailedMessages finished at ' . now());
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

    protected function makePdo($db): PDO
    {
        return new PDO(
            "{$db->connection_type}:host={$db->host};dbname={$db->database_name}",
            $db->username,
            $db->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
}