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
    protected $description = 'Retry messages that failed to insert into downstream databases';

    public function handle()
    {
        Log::info('RetryFailedMessages started at ' . now());

        // Load all subscriptions (app ↔ table)
        $subscriptions = ApplicationTableSubscription::with([
            'databaseTable.database',
            'application.name',
        ])->get();
        foreach ($subscriptions as $sub) {
            $group = $sub->consumer_group;
            $retryKey = "retry:{$group}";
            $source = $sub->application->name;
            $rawData = $sub->fieldMappings->pluck('applicationField.name')->toArray();
            

            // Grab all held entries for this group
            $entries = Redis::lrange($retryKey, 0, -1);
            if (empty($entries)) {
                continue;
            }

            $db = $sub->databaseTable->database;
            $table = $sub->databaseTable->table_name;

            // Is the DB back up?
            if (!$this->isDatabaseServerReachable($db->host, $db->port)) {
                $this->warn("↻ {$db->name} still down, skipping retry for group “{$group}”");
                continue;
            }

            // Open PDO once per subscription
            $pdo = $this->makePdo($db);

            foreach ($entries as $json) {
                $payload = json_decode($json, true);
                $data = $payload['data'] ?? [];

                if (empty($data)) {
                    // nothing to insert, drop it
                    Redis::lrem($retryKey, 0, $json);
                    continue;
                }

                // Build INSERT query
                $cols = array_keys($data);
                $placeholders = array_map(fn($c) => ":{$c}", $cols);
                $sql = sprintf(
                    'INSERT INTO `%s` (%s) VALUES (%s)',
                    $table,
                    implode(',', $cols),
                    implode(',', $placeholders),
                );

                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare($sql);
                    foreach ($data as $col => $val) {
                        $stmt->bindValue(":{$col}", $val);
                    }
                    $stmt->execute();
                    $pdo->commit();

                    \App\Models\Log::create([
                        'source' => $source,
                        'destination' => $table,
                        'data_sent' => $data,              // the raw $data you sent into the query
                        'data_received' => $data,        // the final array you inserted
                        'sent_at' => now(),              // or the timestamp from Redis message if you embedded it
                        'received_at' => now(),
                        'message' => 'data sent from retry'
                    ]);

                    // on success, remove this entry
                    Redis::lrem($retryKey, 0, $json);
                    $this->info("✔ Retried and inserted into {$table}");
                } catch (\Throwable $e) {
                    $pdo->rollBack();
                    $this->error("✖ Retry failed for {$table}: " . $e->getMessage());
                }
            }

            // If we've cleared all entries, delete the list key entirely
            if (empty(Redis::lrange($retryKey, 0, -1))) {
                Redis::del($retryKey);
                $this->info("🗑️ Cleared retry list for group “{$group}”");
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
