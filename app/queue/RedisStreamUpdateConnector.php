<?php
namespace App\Queue;

use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;
use App\queue\RedisStreamQueue;
use App\Queue\RedisStreamUpdateQueue;
class RedisStreamUpdateConnector implements ConnectorInterface
{
    public function connect(array $config)
    {
        $redis = app('redis')->connection(Arr::get($config, 'connection'));
        //dump($config);
        return new RedisStreamUpdateQueue(
            $redis,
            Arr::get($config, 'stream'),
            Arr::get($config, 'group'),
            Arr::get($config, 'consumer')
        );
    }
}
