<?php

namespace App\Queue;

use Illuminate\Queue\Queue;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Log;
use Redis\Exception;


use App\Jobs\ProcessStreamMessage;

class RedisStreamQueue extends Queue implements QueueContract
{
    protected $client;
    protected string $stream;
    protected string $group;
    protected string $consumer;

    public function __construct(Connection $redis, string $stream, string $group, string $consumer)
    {
        dump('Listener connect to ' . $redis->getHost() . ':' . $redis->getPort());

        // Simpan objek phpredis mentah
        $this->client = $redis->client();
        $this->stream = $stream;
        $this->group = $group;
        $this->consumer = $consumer;

        // Pastikan consumer-group ada
        try {
            $this->client->xGroup('CREATE', $stream, $group, '0', true);
        } catch (RedisException $e) {

            // ignore “BUSYGROUP”
        }
    }

    /** Push raw payload (dipakai Laravel) */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return $this->client->xAdd($this->stream, '*', ['job' => $payload]);
    }

    /** Ambil 1 pesan berikutnya */
    public function pop($queue = null)
    {
        $messages = $this->client->xReadGroup(
            $this->group,
            $this->consumer,
            [$this->stream => '>'],
            10,   // COUNT
            0    // BLOCK ms (0 = blok selamanya)
        );

        if (!$messages || !isset($messages[$this->stream])) {
            return null;
        }

        dump($messages);

        foreach ($messages[$this->stream] as $id => $fields) {
            dump($fields); // Debug: tampilkan fields
            // Kirim ke Job Laravel biasa
            try {
                \Log::info('Dispatching job for message ID: ' . $id);
                dump(ProcessStreamMessage::dispatch($id, $fields)
                    ->onConnection('redis')   // worker redis biasa
                    ->onQueue('redis'));
            } catch (\Throwable $th) {
                //throw $th;
                \Log::error('Failed to dispatch job: ' . $th->getMessage() . $th->getFile() . ':' . $th->getLine());
            }
            // ACK supaya tidak diulang
            $this->client->xAck($this->stream, $this->group, [$id]);
        }

        return null; // Laravel tak perlu Job implisit
    }

    /* --- fungsi lain (size, later, dll) bisa dikosongkan bila tak dipakai --- */

    /**
     * @inheritDoc
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
    }

    /**
     * @inheritDoc
     */
    public function push($job, $data = '', $queue = null)
    {
    }

    /**
     * @inheritDoc
     */
    public function size($queue = null)
    {
    }
}
