<?php
namespace App\Queue;

use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Support\Arr;

class RedisStreamConnector implements ConnectorInterface
{
    public function connect(array $config)
    {
        $redis = app('redis')->connection(Arr::get($config, 'connection'));

        return new RedisStreamQueue(
            $redis,
            Arr::get($config, 'stream'),
            Arr::get($config, 'group'),
            Arr::get($config, 'consumer')
        );
    }
}
