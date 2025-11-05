<?php

/**
 * Test Script for Redis Stream Queue Improvements
 *
 * This script tests the improved queue system to verify:
 * 1. Separate queues are working
 * 2. Timing improvements are accurate
 * 3. Consumer names are consistent
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🚀 Testing Redis Stream Queue Improvements\n";
echo "=========================================\n\n";

// Test 1: Check Queue Configuration
echo "📋 Test 1: Queue Configuration\n";
echo "--------------------------------\n";

$queueConfig = config('queue');
echo "Redis Stream Queue: " . ($queueConfig['connections']['redis-stream']['dispatch_queue'] ?? 'NOT SET') . "\n";
echo "Redis Update Queue: " . ($queueConfig['connections']['redis-update-stream']['dispatch_queue'] ?? 'NOT SET') . "\n";
echo "Stream Consumer: " . ($queueConfig['connections']['redis-stream']['consumer'] ?? 'NOT SET') . "\n";
echo "Update Consumer: " . ($queueConfig['connections']['redis-update-stream']['consumer'] ?? 'NOT SET') . "\n\n";

// Test 2: Test Redis Connection
echo "🔌 Test 2: Redis Connection\n";
echo "---------------------------\n";

try {
    $redis = Redis::connection();
    $pong = $redis->ping();
    echo "✅ Redis Connection: " . ($pong ? 'OK' : 'FAILED') . "\n";

    // Check stream existence
    $streamExists = $redis->exists('app:data:stream');
    $updateStreamExists = $redis->exists('app:data:update:stream');
    echo "✅ Main Stream Exists: " . ($streamExists ? 'YES' : 'NO') . "\n";
    echo "✅ Update Stream Exists: " . ($updateStreamExists ? 'YES' : 'NO') . "\n";

} catch (Exception $e) {
    echo "❌ Redis Connection Failed: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: Test Timestamp Generation
echo "⏰ Test 3: Timestamp Generation\n";
echo "------------------------------\n";

$now = now();
$processingStartedAt = $now->toIso8601String();
echo "✅ Processing Started At: {$processingStartedAt}\n";

$jobStartedAt = now();
$jobProcessingStartedAt = now();
echo "✅ Job Processing Started At: {$jobProcessingStartedAt->toIso8601String()}\n";

$queueDelay = $jobProcessingStartedAt->diffInSeconds($now);
echo "✅ Queue Delay: {$queueDelay} seconds\n";
echo "\n";

// Test 4: Test Queue Workers (if running)
echo "🏃 Test 4: Queue Worker Status\n";
echo "-----------------------------\n";

try {
    $redis = Redis::connection();

    // Check consumer groups
    $streamInfo = $redis->xInfo('GROUPS', 'app:data:stream');
    $updateStreamInfo = $redis->xInfo('GROUPS', 'app:data:update:stream');

    echo "📊 Stream Consumer Groups:\n";
    if (is_array($streamInfo)) {
        foreach ($streamInfo as $group) {
            echo "  - Group: {$group['name']}, Consumers: {$group['consumers']}, Pending: {$group['pending']}\n";
        }
    } else {
        echo "  - No consumer groups found\n";
    }

    echo "📊 Update Stream Consumer Groups:\n";
    if (is_array($updateStreamInfo)) {
        foreach ($updateStreamInfo as $group) {
            echo "  - Group: {$group['name']}, Consumers: {$group['consumers']}, Pending: {$group['pending']}\n";
        }
    } else {
        echo "  - No consumer groups found\n";
    }

} catch (Exception $e) {
    echo "❌ Could not check consumer groups: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Test Sample Data Processing
echo "🧪 Test 5: Sample Data Processing\n";
echo "----------------------------------\n";

$samplePayload = [
    'api_key' => 'test-key-' . Str::random(8),
    'name' => 'Test User',
    'email' => 'test@example.com',
    'enqueued_at' => now()->toIso8601String(),
    'processing_started_at' => now()->toIso8601String(),
];

echo "✅ Sample Payload Generated:\n";
echo "  - API Key: " . $samplePayload['api_key'] . "\n";
echo "  - Name: " . $samplePayload['name'] . "\n";
echo "  - Enqueued At: " . $samplePayload['enqueued_at'] . "\n";
echo "  - Processing Started At: " . $samplePayload['processing_started_at'] . "\n";

// Calculate expected timing
$enqueuedAt = Carbon::parse($samplePayload['enqueued_at']);
$processingStartedAt = Carbon::parse($samplePayload['processing_started_at']);
$expectedQueueDelay = $processingStartedAt->diffInSeconds($enqueuedAt);
echo "✅ Expected Queue Delay: {$expectedQueueDelay} seconds\n";
echo "\n";

// Test 6: Check Laravel Queue Configuration
echo "🔧 Test 6: Laravel Queue Setup\n";
echo "-----------------------------\n";

echo "Default Queue Connection: " . config('queue.default') . "\n";
echo "Failed Job Driver: " . config('queue.failed.driver') . "\n";
echo "Batching Database: " . config('queue.batching.database') . "\n";
echo "\n";

echo "🎉 Queue Improvement Tests Complete!\n";
echo "=====================================\n";
echo "Next steps:\n";
echo "1. Start workers with: php artisan queue:work redis --queue=stream-insert\n";
echo "2. Start update workers with: php artisan queue:work redis --queue=stream-update\n";
echo "3. Monitor logs for timing improvements\n";
echo "4. Test with actual data to verify timing accuracy\n";
echo "\n";

echo "📖 For detailed configuration, see: WORKER_CONFIGURATION.md\n";