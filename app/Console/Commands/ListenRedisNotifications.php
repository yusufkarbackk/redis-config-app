<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ListenRedisNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Memanggil: php artisan redis:listen-notify
     */
    protected $signature = 'redis:listen-notify';

    /**
     * The console command description.
     */
    protected $description = 'Listen for Redis XADD events via Keyspace Notifications';

    public function handle()
    {
        $this->info('🔔 Menunggu XADD event pada stream…');

        // 1) Ambil instance PhpRedis dari facade Redis
        /** @var Redis $client */
        $client = Redis::connection()->client();
        dump($client);
        // 2) Pilih database-0
        $client->select(0);

        // 3) (Opsional) Aktifkan keyspace notifications
        //    Idealnya sudah di redis.conf, tapi ini untuk jaga‐jaga:
        $client->config('SET', 'notify-keyspace-events', 'Egx');
        $this->info('Keyspace notifications set: OK');

        // 4) Nama stream yang akan kita dengarkan
        $streamKey = config('queue.connections.redis-stream.stream');

        // 5) Lakukan PSUBSCRIBE blocking
        //    Callback menerima 4 argumen: ($redis, $pattern, $channel, $message)

        $client->psubscribe(
            ["__keyevent@0__:xadd"],
            function ($redis, $pattern, $channel, $message) use ($streamKey) {
                // $message berisi nama key yang di‐XADD
                if ($message === $streamKey) {
                    $this->info("▶ XADD terdeteksi pada “{$message}”");

                    // 6) Panggil command untuk baca & proses XREADGROUP
                    \Artisan::call('redis:process-stream', [
                        '--stream' => $message,
                    ]);

                    // 7) (Opsional) tampilkan output process-stream
                    $output = trim(\Artisan::output());
                    if ($output !== '') {
                        $this->info($output);
                    }
                }
            }
        );


        // Kode tidak akan pernah lanjut di sini, karena PSUBSCRIBE blocking
    }
}
