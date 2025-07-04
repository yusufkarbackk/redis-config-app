<?php

namespace App\Console\Commands;

use App\Models\ProjectHelper;
use Helper;
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
        $helper = new ProjectHelper();

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
            $entries = Redis::lrange($key, 0, -1);
            Log::info("Entries:  " . json_encode($entries));
            // Log::info("Processing retry for key {$key} with subscription ID {$id}");
            // Log::info("Processing retry for {$sub}");
            if (!$sub) {
                Redis::del($key);
                continue;
            }

            $appName = $sub->application->name;
            $dbConf = $sub->databaseTable->database;
            Log::info("Processing retry for connection type {$dbConf->connection_type}");
            $table = $sub->databaseTable->table_name;
            $mapping = $sub->fieldMappings
                ->pluck('mapped_to', 'applicationField.name')
                ->toArray();

            if ($helper->isDatabaseServerReachable($dbConf->host, $dbConf->port)) {
                try {
                    // 3) Ambil semua entry JSON
                    $entries = Redis::lrange($key, 0, -1);
                    Log::info("Entries:  " . count($entries));
                    if (empty($entries)) {
                        Redis::del($key);
                        continue;
                    }
                    $password = decrypt($dbConf->password);

                    // 4) Siapkan PDO + sekali prepare statement
                    $pdo = $helper->makePdo(
                        $dbConf->connection_type,
                        $dbConf->host,
                        $dbConf->database_name,
                        $dbConf->username,
                        $password
                    );
                    if (!$pdo) {
                        Log::error("Failed to connect to database for sub {$id} table {$table}");
                        continue;
                    }

                    $successCount = 0;
                    $total = count($entries);
                    Log::info("Retrying {$total} entries for sub {$id}");

                    // 5) Loop setiap entry
                    foreach ($entries as $json) {
                        $payload = json_decode($json, true);
                        $data = [];

                        Log::info("Processing entry: " . json_encode($payload['data']));

                        // filter & rename fields
                        foreach ($mapping as $appField => $col) {
                            Log::info("Mapping app field {$appField} to column {$col}");
                            //$payload['data'][$appField];
                            if (isset($payload['data'][$col])) {
                                $data[$col] = $payload['data'][$col];
                            }
                        }

                        Log::info("data: ", $data);

                        if (empty($data)) {
                            $successCount++;
                            continue;
                        }

                        // build SQL & bind sekali per subscription
                        $cols = array_keys($data);
                        $ph = implode(',', array_fill(0, count($cols), '?'));
                        $sql = "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES ($ph)";

                        Log::info($sql);
                        Log::info("Retrying sub {$id} table {$table}: " . json_encode($data));
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
                            Log::error("Retry failed sub {$id} table {$table}: {$e->getMessage()} - {$e->getLine()}");
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
                } catch (\Throwable $th) {
                    //throw $th;
                    Log::error("Retry failed for sub {$id} table {$table}: {$th->getMessage()} - {$th->getMessage()} - {$th->getLine()}");
                }
            } else {
                Log::error('Database server is not reachable');
                continue;
            }
        }
        Log::info('Retry end ' . now());
    }
}
