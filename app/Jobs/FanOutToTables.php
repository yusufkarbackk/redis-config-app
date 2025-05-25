<?php

namespace App\Jobs;

use App\Models\ApplicationTableSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use PDO;

class FanOutToTables implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public string $messageId;
    public array $fields;

    public function __construct(string $messageId, array $fields)
    {
        $this->messageId = $messageId;
        $this->fields = $fields;
    }

    public function handle()
    {
        $stream = env('REDIS_UNIFIED_STREAM');

        ApplicationTableSubscription::pluck('consumer_group')
            ->each(fn($grp) => Redis::xgroup('CREATE', $stream, $grp, '$', 'MKSTREAM', 'MKSTREAM'));

        // 1) extract app ID
        $appId = $this->fields['application_id'] ?? null;
        if (!$appId) {
            // malformed – ack and bail
            Redis::xack($stream, $stream, [$this->messageId]);
            return;
        }
        dump("Processing message for app ID: {$appId}");
        // 2) find every table this app is subscribed to
        $subs = ApplicationTableSubscription::with('databaseTable.database')
            ->where('application_id', $appId)
            ->get();

        foreach ($subs as $sub) {
            $table = $sub->databaseTable->table_name;
            $dbconf = $sub->databaseTable->databaseConfig;
            // remove our metadata
            $data = array_filter($this->fields, fn($k) => $k !== 'application_id', ARRAY_FILTER_USE_KEY);

            // build simple insert
            $cols = array_keys($data);
            $holders = implode(',', array_fill(0, count($cols), '?'));
            $colSql = implode(',', $cols);
            $sql = "INSERT INTO {$table} ({$colSql}) VALUES ({$holders})";

            try {
                $pdo = new PDO(
                    "{$dbconf->connection_type}:host={$dbconf->host};dbname={$dbconf->database_name}",
                    $dbconf->username,
                    $dbconf->password,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $pdo->beginTransaction();
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($data));
                $pdo->commit();
            } catch (\Throwable $e) {
                // you could log & continue or re-throw to retry the entire job
                $pdo->rollBack();
            }
        }

        // 3) acknowledge once, so this message is never redelivered
        Redis::xack($stream, $stream, [$this->messageId]);
    }
}
