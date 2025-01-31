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
    public function handle() { 
        
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
