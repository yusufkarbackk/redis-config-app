<?php

namespace App\Queue;

use Illuminate\Queue\Queue;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Log;
use Redis\Exception;


use App\Jobs\ProcessStreamMessage;
use Exception as GlobalException;

class RedisStreamQueue extends Queue implements QueueContract
{
    protected $client;
    protected string $stream;
    protected string $group;
    protected string $consumer;
    protected string $dispatchQueue;

    public function __construct(Connection $redis, string $stream, string $group, string $consumer, string $dispatchQueue = 'stream-insert')
    {
        Log::info('RedisStreamQueue initialized', [
            'host' => $redis->getHost(),
            'port' => $redis->getPort(),
            'stream' => $stream,
            'group' => $group,
            'consumer' => $consumer,
            'dispatch_queue' => $dispatchQueue
        ]);

        // Simpan objek phpredis mentah
        $this->client = $redis->client();
        $this->stream = $stream;
        $this->group = $group;
        $this->consumer = $consumer;
        $this->dispatchQueue = $dispatchQueue;

        // Pastikan consumer-group ada
        try {
            $this->client->xGroup('CREATE', $stream, $group, '0', true);
            Log::info('Consumer group created or already exists', [
                'stream' => $stream,
                'group' => $group
            ]);
        } catch (GlobalException $e) {
            // ignore "BUSYGROUP"
            Log::debug('Consumer group already exists', [
                'stream' => $stream,
                'group' => $group,
                'error' => $e->getMessage()
            ]);
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
        try {
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

            $processedCount = 0;
            $failedCount = 0;

            foreach ($messages[$this->stream] as $id => $fields) {
                try {
                    Log::info('Processing stream message', [
                        'message_id' => $id,
                        'stream' => $this->stream,
                        'consumer' => $this->consumer
                    ]);

                    // Add processing timestamp to payload
                    $fields['processing_started_at'] = now()->toIso8601String();

                    // Kirim ke Job Laravel biasa dengan separate queue
                    ProcessStreamMessage::dispatch($id, $fields)
                        ->onConnection('redis')
                        ->onQueue($this->dispatchQueue); // Use separate queue

                    $processedCount++;

                    Log::info('Job dispatched successfully', [
                        'message_id' => $id,
                        'dispatch_queue' => $this->dispatchQueue
                    ]);

                } catch (\Throwable $th) {
                    $failedCount++;
                    Log::error('Failed to dispatch job', [
                        'message_id' => $id,
                        'error' => $th->getMessage(),
                        'file' => $th->getFile(),
                        'line' => $th->getLine(),
                        'trace' => $th->getTraceAsString()
                    ]);

                    // Continue processing other messages even if one fails
                    continue;
                }

                // ACK supaya tidak diulang - acknowledge each message individually
                $this->client->xAck($this->stream, $this->group, [$id]);
            }

            Log::info('Batch processing completed', [
                'stream' => $this->stream,
                'consumer' => $this->consumer,
                'processed_count' => $processedCount,
                'failed_count' => $failedCount,
                'total_messages' => count($messages[$this->stream])
            ]);

            return null; // Laravel tak perlu Job implisit

        } catch (\Throwable $e) {
            Log::error('Critical error in RedisStreamQueue::pop()', [
                'stream' => $this->stream,
                'consumer' => $this->consumer,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Sleep briefly to prevent tight error loops
            sleep(1);
            return null;
        }
    }

    /* --- fungsi lain (size, later, dll) bisa dikosongkan bila tak dipakai --- */

    /**
     * @inheritDoc
     */
    public function later($delay, $job, $data = '', $queue = null) {}

    /**
     * @inheritDoc
     */
    public function push($job, $data = '', $queue = null) {}

    /**
     * @inheritDoc
     */
    public function size($queue = null) {}
}
