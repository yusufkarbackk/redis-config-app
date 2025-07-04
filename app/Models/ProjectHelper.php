<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectHelper extends Model
{
    public function isDatabaseServerReachable(string $host, int $port): bool
    {
        $conn = @fsockopen($host, $port, $errno, $errstr, 2);
        if ($conn) {
            fclose($conn);
            return true;
        }
        return false;
    }

    public function makePdo($connection_type, $host, $database_name, $username, $password): \PDO
    {
        return new \PDO(
            "{$connection_type}:host={$host};dbname={$database_name}",
            $username,
            $password,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }

    public function createLog(string $source, string $table, $db, array $rawData, array $mapped, Carbon $sentAt): void
    {
        Log::create([
            'source' => $source,
            'destination' => $table,
            'host' => $db->host,
            'data_sent' => json_encode($rawData),
            'data_received' => json_encode($mapped),
            'sent_at' => $sentAt,
            'received_at' => now(),
            'status' => 'OK',
            'message' => 'inserted successfully',
        ]);
    }
}
