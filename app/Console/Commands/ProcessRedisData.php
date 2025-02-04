<?php

namespace App\Console\Commands;

use App\Models\Application;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use PDO;
use PDOException;

class ProcessRedisData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-redis-data';

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
        $streams = ['app:aDQxvJzyTzhlKDNn3xFxuWqSBcBqmZN5:stream', 'app:d6FdEWZAqAv7Gu78OfZz0dSdQKELUzTm:stream'];
        $group = 'your_consumer_group';
        $consumer = 'your_consumer_name';
        $lastId = '>';

        // Ensure each stream has a consumer group (Run only once per stream)
        foreach ($streams as $stream) {
            try {
                Redis::command('xgroup', ['CREATE', $stream, $group, '$', true]);
            } catch (\Exception $e) {
                // Ignore if the group already exists
            }
        }

        $this->info("Listening to Redis streams: " . implode(', ', $streams));

        while (true) {
            // Prepare streams array with lastId for each
            $streamKeys = [];
            foreach ($streams as $stream) {
                $streamKeys[$stream] = $lastId;
            }

            // Read from multiple streams
            $messages = Redis::command('xreadgroup', [$group, $consumer, $streamKeys, 1]);

            if ($messages) {
                foreach ($messages as $streamName => $messageData) {
                    foreach ($messageData as $id => $message) {
                        $this->info("Stream: $streamName, ID: $id");
                        $this->info("Message Data: " . json_encode($message));
                        $config = [
                            "host" => "127.0.0.1",
                            "port" => 3306,
                            "database_name" => "car_db",
                            "username" => "root",
                            "password" => "",
                            "table_name" => "car_table"
                        ];
                        $this->insertIntoDatabase($config, $message);
                        // Process message for the specific stream...

                        // Acknowledge the message
                        Redis::command('xack', [$streamName, $group, [$id]]);
                    }
                }
            }

            usleep(500000); // Sleep for 0.5 seconds to reduce CPU usage
        }
    }

    function insertIntoDatabase($config, $data)
    {
        try {
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database_name']}";
            $pdo = new PDO($dsn, $config['username'], $config['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Prepare insert statement
            $columns = implode(", ", array_keys($data));
            $values = implode(", ", array_map(fn($val) => "'$val'", array_values($data)));

            $sql = "INSERT INTO {$config['table_name']} ($columns) VALUES ($values)";
            $pdo->exec($sql);

            echo "Inserted into {$config['database_name']}.{$config['table_name']}\n";
        } catch (PDOException $e) {
            echo "Error inserting into {$config['database_name']}: " . $e->getMessage() . "\n";
        }
    }
}
