<?php

namespace App\Console\Commands;

use App\Models\DatabaseConfig;
use DB;
use Illuminate\Console\Command;

class DatabaseHeartbeat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:database-heartbeat';

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
        $databases = DatabaseConfig::all();

        foreach ($databases as $database) {
            $password = decrypt($database->password);
            $config = [
                'driver' => $database->connection_type === 'pgsql' ? 'pgsql' : 'mysql',
                'host' => $database->host,
                'port' => $database->port,
                'database' => $database->database_name,
                'username' => $database->username,
                'password' => $password,
            ];

            try {
                config(['database.connections.heartbeat_check' => $config]);
                DB::connection('heartbeat_check')->getPdo(); // Hanya cek PDO
                DB::purge('heartbeat_check');
                DatabaseConfig::where('id', $database->id)->update(['status' => 'up']);
                return true;
            } catch (\Throwable $e) {
                DB::purge('heartbeat_check');
                DatabaseConfig::where('id', $database->id)->update(['status' => 'down']);
                return false;
                // Atau return $e->getMessage(); kalau mau detail error
            }
        }
    }
}
