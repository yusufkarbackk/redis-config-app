# Redis Stream Worker Configuration Guide

## 🚀 **Improved Architecture Overview**

The Redis stream system has been significantly improved to provide accurate timing, proper worker separation, and enhanced error handling.

## 🔧 **Queue Configuration**

### **Separated Queues**

The system now uses **separate Laravel queues** for different job types:

- **Stream Insert Queue**: `stream-insert` - for ProcessStreamMessage jobs
- **Stream Update Queue**: `stream-update` - for UpdateStreamMessage jobs

### **Consumer Names**

Consistent consumer names using hostname and process ID:
- **Stream Insert**: `stream-insert-{hostname}-{pid}`
- **Stream Update**: `stream-update-{hostname}-{pid}`

## 🏃 **Worker Commands**

### **Start Separate Workers**

```bash
# Start worker for stream insert operations
php artisan queue:work redis --queue=stream-insert --sleep=1 --tries=3

# Start worker for stream update operations
php artisan queue:work redis --queue=stream-update --sleep=1 --tries=3

# Start worker for default operations (if needed)
php artisan queue:work redis --queue=redis --sleep=1 --tries=3
```

### **Worker with Supervisor (Updated Configuration)**

Update your existing `/etc/supervisor/conf.d/redis-stream-workers.conf` (or whatever your config file is called):

```ini
[program:redis-stream-insert]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work redis --queue=stream-insert --sleep=1 --tries=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/stream-insert.log
stopwaitsecs=3600

[program:redis-stream-update]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work redis --queue=stream-update --sleep=1 --tries=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/stream-update.log
stopwaitsecs=3600
```

## 📊 **Timing Improvements**

### **Enhanced Timestamp Tracking**

The system now captures multiple timestamps for accurate timing analysis:

1. **`enqueued_at`**: When HTTP request arrives (original)
2. **`processing_started_at`**: When Redis stream consumer starts processing
3. **`job_started_at`**: When Laravel job is created
4. **`job_processing_started_at`**: When Laravel job begins execution
5. **`received_at`**: When processing completes (existing)

### **Corrected `sent_at` Logic**

- **Old**: Used HTTP request time for all records
- **New**: Uses actual processing start time (`processing_started_at`)

### **Queue Delays Calculation**

```php
$queueDelay = $sentAt->diffInSeconds($enqueuedAt);
$processingDelay = $jobProcessingStartedAt->diffInSeconds($sentAt);
```

## 🛠️ **Environment Variables**

Add these to your `.env` file for consistent consumer naming:

```env
# Optional: Override auto-generated consumer names
REDIS_STREAM_CONSUMER=stream-insert-prod-1
REDIS_UPDATE_STREAM_CONSUMER=stream-update-prod-1

# Optional: Override consumer groups
REDIS_STREAM_GROUP=stream-workers
REDIS_UPDATE_STREAM_GROUP=update-workers
```

## 🔍 **Monitoring & Logging**

### **Key Log Messages**

Watch for these log patterns:

```bash
# Stream consumer activity
grep "Processing stream message" storage/logs/laravel.log

# Timing metrics
grep "Timing metrics calculated" storage/logs/laravel.log

# Queue dispatch
grep "job dispatched successfully" storage/logs/laravel.log

# Batch processing
grep "Batch processing completed" storage/logs/laravel.log
```

### **Performance Metrics**

The system now logs:
- **Queue delay**: Time between HTTP request and stream processing
- **Processing delay**: Time between stream processing and job execution
- **Batch counts**: Number of messages processed in each batch
- **Error tracking**: Detailed error information with context

## ⚡ **Configuration Benefits**

### **Problems Solved**

1. ✅ **Same `sent_at` Issue**: Now uses actual processing start time
2. ✅ **Worker Contention**: Separate queues prevent conflicts
3. ✅ **Consumer Names**: Consistent naming for better monitoring
4. ✅ **Timing Accuracy**: Multiple timestamps for precise measurements
5. ✅ **Error Handling**: Comprehensive logging and graceful failures

### **Architecture Improvements**

- **Queue Isolation**: Insert and update jobs run on separate workers
- **Better Monitoring**: Consistent consumer names enable tracking
- **Accurate Metrics**: Real timing data for performance analysis
- **Enhanced Logging**: Detailed context for debugging
- **Graceful Degradation**: System continues working with partial failures

## 🚨 **Troubleshooting**

### **Common Issues**

1. **Workers not processing jobs**:
   - Check queue names match configuration
   - Verify workers are running for correct queues
   - Check Redis connection

2. **Timing still inaccurate**:
   - Verify system time synchronization
   - Check for processing bottlenecks
   - Review queue worker performance

3. **Consumer name conflicts**:
   - Use unique hostnames
   - Check for multiple processes on same host
   - Consider manual consumer name configuration

### **Health Checks**

```bash
# Check Redis connection
php artisan tinker
>>> Redis::ping()

# Check queue status
php artisan queue:monitor

# Check failed jobs
php artisan queue:failed

# Check consumer groups (Redis CLI)
redis-cli
> XINFO GROUPS app:data:stream
> XINFO GROUPS app:data:update:stream
```

## 📈 **Performance Recommendations**

1. **Worker Count**: Start with 1 worker per queue, scale based on load
2. **Memory Monitoring**: Monitor worker memory usage
3. **Redis Monitoring**: Track Redis memory and connection counts
4. **Log Rotation**: Implement log rotation to prevent disk space issues
5. **Alerting**: Set up alerts for queue depths and processing delays

This improved architecture provides accurate timing, proper worker separation, and enhanced monitoring capabilities for your Redis stream processing system.