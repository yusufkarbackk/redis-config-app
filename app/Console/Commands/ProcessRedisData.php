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
        $pdo = new PDO("mysql:host=127.0.0.1;dbname=filament_app", "root", "");

        $query = $pdo->query("
            SELECT database_tables.id, database_tables.table_name, database_tables.database_config_id, 
                database_configs.host, database_configs.port, database_configs.name, 
                database_configs.username, database_configs.password,
                database_tables.application_id, 
                GROUP_CONCAT(application_fields.name) as fields
            FROM database_tables
            JOIN database_configs ON database_tables.database_config_id = database_configs.id
            JOIN database_field_subscriptions ON database_tables.id = database_field_subscriptions.table_id
            JOIN application_fields ON database_field_subscriptions.application_field_id = application_fields.id
            GROUP BY database_tables.id
        ");

        $tableConfigs = $query->fetchAll(PDO::FETCH_ASSOC);

        // Prepare the stream names
        $app_streams = [];
        foreach ($tableConfigs as $config) {
            $app_streams[$config['application_id'] . "_stream"] = '$'; // Listen from the latest entry
        }

        echo "Listening to Redis streams: " . implode(", ", array_keys($app_streams)) . "\n";

        // Process data continuously
        while (true) {
            $data = Redis::command('xRead', [$app_streams, null, 5000]); // Blocking read

            if ($data) {
                foreach ($data as $stream => $entries) {
                    $app_id = str_replace("_stream", "", $stream); // Extract app_id

                    foreach ($entries as $entry) {
                        $id = $entry[0];
                        $fields = $entry[1];

                        // Process data for tables subscribed to this app_id
                        foreach ($tableConfigs as $config) {
                            if ($config['application_id'] === $app_id) {
                                // Extract only required fields
                                $requiredFields = explode(",", $config['fields']);
                                $filteredData = array_intersect_key($fields, array_flip($requiredFields));

                                // Insert into the correct database
                                $this->insertIntoDatabase($config, $filteredData);
                            }
                        }

                        echo "Processed ID: $id from $app_id\n";
                    }
                }
            } else {
                echo "No new data. Waiting...\n";
            }

            usleep(100000); // Sleep to reduce CPU usage
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
