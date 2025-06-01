<?php

namespace App\Queue;

use App\Jobs\ProcessStreamMessage;
use Illuminate\Queue\Queue;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;

class RedisStreamQueue extends Queue implements QueueContract
{

    protected $redis;
    protected $stream;
    protected $group;
    protected $consumer;
    /**
     * Get the size of the queue.
     */
    public function size($queue = null)
    {
        return $this->redis->xlen($this->stream);
    }

    /**
     * Push a raw payload onto the queue.
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        // return $this->redis->xadd($this->stream, ['job' => $payload], '*');

        return Redis::xadd(
            
                $this->stream,
                '*',
                ['job' => $payload],
            
        );
    }

    /**
     * Push a new job onto the queue after a delay.
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        $payload = $this->createPayload($job, $data);

        // Simulate delay by scheduling the job with a timestamp
        $this->redis->zadd(
            "{$this->stream}:delayed",
            ['NX'],
            time() + $this->secondsUntil($delay),
            $payload
        );
    }


    /**
     * @param  \Illuminate\Redis\Connections\Connection  $redis
     * @param  string  $stream     The Redis stream key
     * @param  string  $group      The consumer group name
     * @param  string  $consumer   This consumer’s name
     */
    public function __construct($redis, string $stream, string $group, string $consumer)
    {
        $this->redis = $redis;
        $this->stream = $stream;
        $this->group = $group;
        $this->consumer = $consumer;

        // Ensure the consumer group exists (MKSTREAM creates the stream if needed)
        try {
            // $this->redis->xgroup('CREATE', $this->stream, $this->group, '$', true);

            Redis::command('xgroup', ['CREATE', $this->stream, $this->group, '$', true]);

        } catch (\Throwable $e) {
            // Group probably already exists
        }
    }

    /**
     * Pop the next job off of the queue.
     */
    public function pop($queue = null)
    {
        // Block forever until we get at least 1 message
        // $messages = $this->redis->xreadgroup(
        //     $this->group,
        //     $this->consumer,
        //     [$this->stream => '>'],
        //     1,
        //     0
        // );

        $messages = Redis::xreadgroup(
            $this->group,
            $this->consumer,
            [$this->stream => '>'],
            1,
            0
        );

        $entries = $messages[$this->stream] ?? [];
        dump($entries);
        foreach ($entries as $id => $fields) {
            // 1) Hand it off to a normal Laravel queue job,
            //    forcing it onto the "redis" connection:
            ProcessStreamMessage::dispatch($id, $fields)
                ->onConnection('redis')
                ->onQueue(config('default')); // or a named queue
            // 2) Acknowledge it in the stream so it won't be re-delivered
            $this->redis->xack($this->stream, $this->group, [$id]);
        }

        // We handled dispatch & ack; nothing else for Laravel to do here
        return null;
    }

    /**
     * Delete a reserved job.
     */
    public function deleteMessage(string $id)
    {
        $this->redis->xack($this->stream, $this->group, [$id]);
    }

    /**
     * Push a new job onto the queue.
     * (Used if you dispatch jobs into this connection.)
     */
    public function push($job, $data = '', $queue = null)
    {
        $payload = $this->createPayload($job, $data);

        // $this->redis->xadd($this->stream, ['job' => $payload], '*');
        Redis::command(
            'xadd',
            [
                $this->stream,
                '*',
                ['job' => $payload],
            ]
        );
    }
}
