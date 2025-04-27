<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Exception;

class RedisService
{
    public function get($key)
    {
        try {
            // Try master first
            return Redis::connection('master')->get($key);
        } catch (Exception $e) {
            // Fallback to slave if master fails
            try {
                return Redis::connection('slave')->get($key);
            } catch (Exception $e) {
                \Log::error('Redis error: ' . $e->getMessage());
                return null;
            }
        }
    }

    public function set($key, $value)
    {
        try {
            // Try writing to master
            return Redis::connection('master')->set($key, $value);
        } catch (Exception $e) {
            // Log the error but don't failover for writes
            \Log::error('Redis error: ' . $e->getMessage());
            return false;
        }
    }

    // Add other Redis methods as needed
}