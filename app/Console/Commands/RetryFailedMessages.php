<?php

namespace App\Console\Commands;

use App\Models\DatabaseConfig;
use App\Models\DatabaseTable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use PDO;
use Str;

class RetryFailedMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:retry-failed-messages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $redisProcessStream = new ProcessRedisStreams();
        $tables = DatabaseTable::all();
        $keys = Redis::keys('*retry*');
        \Log::info('RetryFailedRedisMessages command executed at ' . now());

        if (empty($keys)) {
            dump("No retry keys found");

        } else {
            foreach ($keys as $key) {
                $consumerGroup = Str::replaceFirst('retry:', '', $key);
                $table = DatabaseTable::where('consumer_group', $consumerGroup)->first();
                if ($table) {
                    dump("Retry key: {$key}");
                    dump("Table: {$table->table_name}");
                    dump("Table: {$table->database->name}");
                    dump("host: {$table->database->host}");
                    dump("port: {$table->database->port}");
                    dump("Table: {$table->table_name}");
                    dump("Consumer group: {$consumerGroup}");

                    if ($redisProcessStream->isDatabaseServerReachable($table->database->host, $table->database->port)) {

                        dump("Database {$table->database->name} is up");
                        $raw = Redis::lrange($key, 0, -1);

                        foreach ($raw as $data) {
                            $decoded = json_decode($data, true);

                            $destinationTable = $decoded['table'];
                            $data = $decoded['data'];

                            try {
                                $pdo = new PDO(
                                    "{$table->database->connection_type}:host={$table->database->host};dbname={$table->database->database_name}",
                                    $table->database->username,
                                    $table->database->password,
                                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                                );

                                // Build insert query
                                $columns = array_keys($data);
                                //var_dump($columns);
                                $placeholders = array_map(fn($col) => ":$col", $columns);                                //var_dump($values);

                                dump($columns);
                                dump($placeholders);

                                $sql = "INSERT INTO `$destinationTable` (" . implode(',', $columns) . ") 
                                    VALUES (" . implode(',', $placeholders) . ")";
                                //var_dump($sql);
                                $stmt = $pdo->prepare($sql);

                                // Bind values
                                foreach ($data as $key => $value) {
                                    $stmt->bindValue(":$key", $value);
                                }

                                //var_dump($stmt);
                                if ($stmt->execute()) {
                                    var_dump("Insert success");
                                } else {
                                    var_dump($stmt->errorInfo());
                                }

                                return $pdo->lastInsertId();
                            } catch (\Exception $th) {
                                dump($th->getMessage() . $th->getLine());
                                //throw $th;
                            }
                        }

                    } else {
                        dump("Database {$table->database->name} is still down. Holding message...");

                    }
                } else {
                    dump("No table found for consumer group: {$consumerGroup}");
                }
            }
        }
    }
}
