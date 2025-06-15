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
        Log::info('Retry start ' . now());

        // 1) Cari semua key retry:subscription:*
        $keys = Redis::keys('retry:subscription:*');
        if (empty($keys)) {
            $this->info('No retry keys found');
            return;
        }

        // 2) Ekstrak subscription IDs dan eager-load
        $subIds = array_map(fn($k) => (int) last(explode(':', $k)), $keys);
        $subs = ApplicationTableSubscription::with([
            'application',
            'databaseTable.database',
            'fieldMappings.applicationField',
        ])
            ->findMany($subIds)
            ->keyBy('id');

        foreach ($keys as $key) {
            $id = (int) last(explode(':', $key));
            $sub = $subs->get($id);
            if (!$sub) {
                Redis::del($key);
                continue;
            }

            $appName = $sub->application->name;
            $dbConf = $sub->databaseTable->database;
            $table = $sub->databaseTable->table_name;
            $mapping = $sub->fieldMappings
                ->pluck('mapped_to', 'applicationField.name')
                ->toArray();

            // 3) Ambil semua entry JSON
            $entries = Redis::lrange($key, 0, -1);
            if (empty($entries)) {
                Redis::del($key);
                continue;
            }

            // 4) Siapkan PDO + sekali prepare statement
            $pdo = new PDO(
                "{$dbConf->connection_type}:host={$dbConf->host};dbname={$dbConf->database_name}",
                $dbConf->username,
                $dbConf->password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $successCount = 0;
            $total = count($entries);

            // 5) Loop setiap entry
            foreach ($entries as $json) {
                $payload = json_decode($json, true);
                $data = [];

                // filter & rename fields
                foreach ($mapping as $appField => $col) {
                    if (isset($payload['data'][$appField])) {
                        $data[$col] = $payload['data'][$appField];
                    }
                }

                if (empty($data)) {
                    $successCount++;
                    continue;
                }

                // build SQL & bind sekali per subscription
                $cols = array_keys($data);
                $ph = implode(',', array_fill(0, count($cols), '?'));
                $sql = "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES ($ph)";

                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array_values($data));
                    $pdo->commit();

                    // log success
                    \App\Models\Log::create([
                        'source' => $appName,
                        'destination' => $table,
                        'host' => $dbConf->host,
                        'data_sent' => json_encode($payload['data']),
                        'data_received' => json_encode($data),
                        'sent_at' => now(),
                        'received_at' => now(),
                        'status' => 'OK',
                        'message' => 'retried',
                    ]);

                    $successCount++;
                } catch (\Throwable $e) {
                    //$pdo->rollBack();
                    Log::error("Retry failed sub {$id} table {$table}: {$e->getMessage()}");
                    // tidak increment successCount
                }
            }

            // 6) Hapus entry yg sukses, sisanya dipertahankan
            if ($successCount === $total) {
                Redis::del($key);
                $this->info("✅ Cleared full retry list for sub {$id}");
            } else {
                // simpan hanya sisa yang gagal
                Redis::ltrim($key, $successCount, -1);
                $this->info("🔁 Trimmed retry list for sub {$id}, kept " . ($total - $successCount) . " entries");
            }
        }

        Log::info('Retry end ' . now());
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
