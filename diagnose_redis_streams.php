<?php

/**
 * Redis Stream Diagnosis Script
 *
 * This script helps diagnose why Redis stream messages aren't being processed.
 * Run this script to check your current setup and identify the issue.
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

echo "🔍 Redis Stream Diagnosis Tool\n";
echo "============================\n\n";

// Test 1: Check Redis Connection
echo "1️⃣ Testing Redis Connection\n";
echo "---------------------------\n";

try {
    $redis = Redis::connection();
    $pong = $redis->ping();
    echo "✅ Redis Connection: " . ($pong ? 'OK' : 'FAILED') . "\n";

    // Check stream keys
    $streamKey = 'app:data:stream';
    $updateStreamKey = 'app:data:update:stream';

    $streamLength = $redis->xlen($streamKey);
    $updateStreamLength = $redis->xlen($updateStreamKey);

    echo "✅ Stream '$streamKey': $streamLength messages pending\n";
    echo "✅ Update Stream '$updateStreamKey': $updateStreamLength messages pending\n";

} catch (Exception $e) {
    echo "❌ Redis Connection Failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// Test 2: Check Consumer Groups
echo "2️⃣ Checking Consumer Groups\n";
echo "---------------------------\n";

try {
    $streamGroups = $redis->xinfo('GROUPS', $streamKey);
    $updateStreamGroups = $redis->xinfo('GROUPS', $updateStreamKey);

    echo "📊 Consumer Groups for '$streamKey':\n";
    if ($streamGroups) {
        foreach ($streamGroups as $group) {
            echo "  - Group: {$group['name']}\n";
            echo "    Consumers: {$group['consumers']}\n";
            echo "    Pending: {$group['pending']}\n";
            echo "    Last-delivered-id: {$group['last-delivered-id']}\n\n";
        }
    } else {
        echo "  - No consumer groups found\n";
    }

    echo "📊 Consumer Groups for '$updateStreamKey':\n";
    if ($updateStreamGroups) {
        foreach ($updateStreamGroups as $group) {
            echo "  - Group: {$group['name']}\n";
            echo "    Consumers: {$group['consumers']}\n";
            echo "    Pending: {$group['pending']}\n";
            echo "    Last-delivered-id: {$group['last-delivered-id']}\n\n";
        }
    } else {
        echo "  - No consumer groups found\n";
    }

} catch (Exception $e) {
    echo "❌ Failed to check consumer groups: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Check Queue Configuration
echo "3️⃣ Checking Queue Configuration\n";
echo "------------------------------\n";

$queueConfig = config('queue');
$defaultConnection = config('queue.default');

echo "Default Queue Connection: $defaultConnection\n";
echo "Redis Stream Queue: " . ($queueConfig['connections']['redis-stream']['dispatch_queue'] ?? 'NOT SET') . "\n";
echo "Redis Update Stream Queue: " . ($queueConfig['connections']['redis-update-stream']['dispatch_queue'] ?? 'NOT SET') . "\n";
echo "Stream Consumer Group: " . ($queueConfig['connections']['redis-stream']['group'] ?? 'NOT SET') . "\n";
echo "Update Consumer Group: " . ($queueConfig['connections']['redis-update-stream']['group'] ?? 'NOT SET') . "\n\n";

// Test 4: Check Running Processes
echo "4️⃣ Checking Running Processes\n";
echo "-----------------------------\n";

exec("ps aux | grep 'queue:work'", $processes);
exec("ps aux | grep 'stream:listen'", $streamListeners);

if (!empty($processes)) {
    echo "🏃 Queue Workers Running:\n";
    foreach ($processes as $process) {
        if (strpos($process, 'grep') === false) {
            echo "  - $process\n";
        }
    }
} else {
    echo "❌ No queue workers running\n";
}

if (!empty($streamListeners)) {
    echo "📡 Stream Listeners Running:\n";
    foreach ($streamListeners as $listener) {
        if (strpos($listener, 'grep') === false) {
            echo "  - $listener\n";
        }
    }
} else {
    echo "❌ No stream listeners running\n";
}

echo "\n";

// Test 5: Try to Add a Test Message
echo "5️⃣ Testing Message Addition\n";
echo "----------------------------\n";

try {
    $testMessage = [
        'api_key' => 'test-key-' . uniqid(),
        'name' => 'Test User',
        'email' => 'test@example.com',
        'enqueued_at' => now()->toIso8601String(),
        'processing_started_at' => now()->toIso8601String(),
    ];

    $messageId = $redis->xadd($streamKey, '*', $testMessage);
    echo "✅ Test message added to stream (ID: $messageId)\n";

    // Wait a moment and check if it was consumed
    sleep(2);

    $newStreamLength = $redis->xlen($streamKey);
    if ($newStreamLength == $streamLength) {
        echo "❌ Test message was NOT consumed (still pending)\n";
        echo "   This means no active consumer is processing the stream\n";
    } else {
        echo "✅ Test message was consumed and processed\n";
    }

} catch (Exception $e) {
    echo "❌ Failed to add test message: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 6: Diagnosis and Recommendations
echo "6️⃣ Diagnosis & Recommendations\n";
echo "------------------------------\n";

$hasCustomWorkers = !empty($processes) && strpos(implode(' ', $processes), 'redis-stream') !== false;
$hasStreamListener = !empty($streamListeners);
$hasStreamWorkers = !empty($processes) && strpos(implode(' ', $processes), 'stream:listen') !== false;

echo "🎯 Diagnosis:\n";

if ($streamLength > 0 && !$hasCustomWorkers && !$hasStreamListener) {
    echo "❌ ISSUE: Messages are in Redis stream but NO workers are consuming them\n";
    echo "💡 SOLUTION: Start the appropriate workers\n";
    echo "\n";
    echo "   Option 1 (Recommended):\n";
    echo "   php artisan queue:work redis-stream --queue=stream-insert\n";
    echo "   php artisan queue:work redis-update-stream --queue=stream-update\n";
    echo "\n";
    echo "   Option 2 (Manual):\n";
    echo "   php artisan stream:listen\n";
    echo "   php artisan queue:work redis --queue=redis\n";
} elseif ($hasCustomWorkers && $hasStreamListener) {
    echo "⚠️  WARNING: Both custom queue workers AND stream listener are running\n";
    echo "💡 SOLUTION: Choose ONE method to avoid conflicts\n";
    echo "   Stop either the custom workers or the stream listener\n";
} elseif ($hasCustomWorkers) {
    echo "✅ Custom queue workers are running\n";
    echo "   Check logs: tail -f storage/logs/laravel.log | grep 'Processing stream message'\n";
} elseif ($hasStreamListener) {
    echo "✅ Stream listener is running\n";
    echo "   Check logs: tail -f storage/logs/laravel.log | grep 'jobs dispatched'\n";
} else {
    echo "❌ No active processors found\n";
    echo "💡 SOLUTION: Start either custom queue workers or stream listener\n";
}

echo "\n🚀 Next Steps:\n";
echo "1. Start the appropriate workers for your chosen method\n";
echo "2. Test with real data to verify processing\n";
echo "3. Monitor logs for timing improvements\n";
echo "4. Update supervisor configuration for production\n";

echo "\n📖 For detailed fix instructions, see: REDIS_STREAM_FIX_GUIDE.md\n";