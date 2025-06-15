<?php
// app/Console/Commands/ProcessRedisStream.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Jobs\ProcessStreamMessage;
use Illuminate\Support\Str;

class ProcessRedisStreams extends Command
{
    protected $signature   = 'redis:process-stream {--stream= : Nama stream yang akan dibaca}';
    protected $description = 'Baca pesan baru dari Redis Stream dan dispatch job';

    public function handle()
    {
        $stream   = $this->option('stream')
                  ?? config('queue.connections.redis-stream.stream');
        $group    = config('queue.connections.redis-stream.group');
        $consumer = config('queue.connections.redis-stream.consumer');
        $client = Redis::connection()->client();


        // Pastikan consumer group ada
        try {
            Redis::xgroup('CREATE', $stream, $group, '$', ['MKSTREAM']);
        } catch (\Throwable $e) {
            // Abaikan jika sudah ada
        }

        // Baca batch 100 pesan (atau sesuaikan)
        $messages = $client->xreadgroup(
            $group,
            $consumer,
            100,       // COUNT
            0,         // BLOCK ms = 0 (infinite)
            false,
            $stream,
            '>'
        );

        $entries = $messages[$stream] ?? [];
        if (empty($entries)) {
            $this->info('– Tidak ada entri baru.');
            return;
        }

        foreach ($entries as $id => $fields) {
            $this->info("→ Memproses message {$id}");

            // Dispatch job Laravel yang akan insert ke DB
            ProcessStreamMessage::dispatch($id, $fields);

            // Acknowledge agar tidak diulang
            Redis::xack($stream, $group, [$id]);
        }
    }
}
