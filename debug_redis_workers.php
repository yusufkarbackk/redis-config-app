<?php

/**
 * Debug Redis Workers Script
 *
 * This script helps debug why messages aren't being processed
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessStreamMessage;

echo "🔧 Debug Redis Workers\n";
echo "====================\n\n";

// Test 1: Check if workers are actually calling pop()
echo "1️⃣ Testing Worker Activity\n";
echo "-------------------------\n";

echo "Starting a test worker in foreground for 10 seconds...\n";

$startTime = time();
$endTime = $startTime + 10;

// Simulate what the queue worker does
$redis = Redis::connection();
$streamKey = 'app:data:stream';
$groupName = 'stream-workers';
$consumerName = 'debug-consumer-' . getmypid();

echo "Using consumer: $consumerName\n";
echo "Current pending messages: " . Redis::xlen($streamKey) . "\n\n";

try {
    // Read messages with timeout
    while (time() < $endTime) {
        $messages = $redis->xreadgroup(
            $groupName,
            $consumerName,
            [$streamKey => '>'],
            5,
            1000  // Wait 1 second
        );

        if ($messages && isset($messages[$streamKey])) {
            foreach ($messages[$streamKey] as $id => $fields) {
                echo "📦 Found message ID: $id\n";
                echo "   Data: " . json_encode($fields, JSON_PRETTY_PRINT) . "\n";

                // Try to dispatch a job
                try {
                    ProcessStreamMessage::dispatch($id, $fields)
                        ->onConnection('redis')
                        ->onQueue('stream-insert');

                    echo "✅ Job dispatched successfully\n";

                    // ACK the message
                    $redis->xack($streamKey, $groupName, [$id]);
                    echo "✅ Message ACKed\n";

                } catch (Exception $e) {
                    echo "❌ Failed to dispatch job: " . $e->getMessage() . "\n";
                }

                echo "\n";
            }
        } else {
            echo ".";
        }
    }

    echo "\n";

} catch (Exception $e) {
    echo "❌ Error during test: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Check if jobs are being processed
echo "2️⃣ Checking Job Queue\n";
echo "--------------------\n";

try {
    // Check Laravel queue
    $queueLength = Redis::llen('queues:stream-insert');
    echo "Jobs in stream-insert queue: $queueLength\n";

    if ($queueLength > 0) {
        echo "📋 Jobs waiting in queue:\n";

        // Check job details
        $jobs = Redis::lrange('queues:stream-insert', 0, -1);
        foreach (array_slice($jobs, 0, 3) as $index => $job) {
            echo "  Job $index: " . substr($job, 0, 100) . "...\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Error checking job queue: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Check recent logs
echo "3️⃣ Recent Log Entries\n";
echo "-----------------------\n";

$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $recentLogs = `tail -n 20 $logFile`;
    echo "Last 20 lines from Laravel log:\n";
    echo $recentLogs;
} else {
    echo "❌ Laravel log file not found at: $logFile\n";
}

echo "\n";

// Test 4: Check what workers are actually running
echo "4️⃣ Running Workers Check\n";
echo "-------------------------\n";

exec("ps aux | grep 'queue:work'", $workers);
exec("ps aux | grep 'redis-stream'", $streamWorkers);

echo "Queue workers running:\n";
if (!empty($workers)) {
    foreach ($workers as $worker) {
        if (strpos($worker, 'grep') === false) {
            echo "  $worker\n";
        }
    }
} else {
    echo "  No queue workers found\n";
}

echo "\nRedis stream workers:\n";
if (!empty($streamWorkers)) {
    foreach ($streamWorkers as $worker) {
        if (strpos($worker, 'grep') === false) {
            echo "  $worker\n";
        }
    }
} else {
    echo "  No redis-stream workers found\n";
}

echo "\n🎯 Diagnosis:\n";

// Based on the results, provide diagnosis
if (Redis::xlen($streamKey) > 0) {
    echo "❌ ISSUE: Messages are in stream but not being consumed\n";
    echo "💡 SOLUTION: Check if workers are actually calling pop() method\n";
    echo "   Run: php artisan queue:work redis-stream --queue=stream-insert --verbose\n";
} elseif ($queueLength > 0) {
    echo "❌ ISSUE: Jobs are in queue but not being processed\n";
    echo "💡 SOLUTION: Check Laravel queue workers\n";
    echo "   Run: php artisan queue:work redis --queue=stream-insert\n";
} else {
    echo "✅ Both stream and queue are empty\n";
    echo "💡 Try adding a test message to see the flow\n";
}

echo "\n🚀 Next Steps:\n";
echo "1. Run the diagnostic script to see real-time processing\n";
echo "2. Check logs for the specific error messages\n";
echo "3. Verify the queue connection in your worker command\n";
echo "4. Test with fresh data to identify the bottleneck\n";