# 🚨 Redis Stream Processing Issue - Fix Guide

## **Problem Identified**
Your data reaches Redis streams but isn't processed because **two different consumer groups are competing** for the same stream:

1. **Custom Queue System** (new architecture): Uses `stream-workers` and `update-workers` groups
2. **Manual Stream Listener** (old architecture): Uses `integrator_group` group

**Both consume from `app:data:stream`, but you only have workers running for one system!**

## 🔧 **Quick Fix - Choose ONE Architecture**

### **Option 1: Use Custom Queue System (Recommended)**

**Step 1: Start the correct workers**
```bash
# Start workers for custom stream queues
php artisan queue:work redis-stream --queue=stream-insert --daemon &
php artisan queue:work redis-update-stream --queue=stream-update --daemon &

# Or run them in foreground to see logs
php artisan queue:work redis-stream --queue=stream-insert --verbose
```

**Step 2: Update supervisor configuration**
Replace your current supervisor config with:

```ini
[program:redis-stream-insert]
process_name=%(program_name)s_%(process_num)02d
numprocs=2
command=php /var/www/html/redis-config-app/artisan queue:work redis-stream --queue=stream-insert --sleep=1 --tries=3 --timeout=60
directory=/var/www/html/redis-config-app
user=www-data
autostart=true
autorestart=true
stdout_logfile=/var/www/html/redis-config-app/storage/logs/redis-stream-insert.log
stderr_logfile=/var/www/html/redis-config-app/storage/logs/redis-stream-insert-error.log

[program:redis-stream-update]
process_name=%(program_name)s_%(process_num)02d
numprocs=2
command=php /var/www/html/redis-config-app/artisan queue:work redis-update-stream --queue=stream-update --sleep=1 --tries=3 --timeout=60
directory=/var/www/html/redis-config-app
user=www-data
autostart=true
autorestart=true
stdout_logfile=/var/www/html/redis-config-app/storage/logs/redis-stream-update.log
stderr_logfile=/var/www/html/redis-config-app/storage/logs/redis-stream-update-error.log
```

**Step 3: Stop the old stream listener**
```bash
# If the old command is running, stop it
pkill -f "stream:listen"
```

### **Option 2: Use Manual Stream Listener (Old Method)**

**Step 1: Change queue connection**
Add to your `.env` file:
```env
QUEUE_CONNECTION=sync
```

**Step 2: Start the stream listener**
```bash
php artisan stream:listen
```

**Step 3: Start regular queue workers**
```bash
php artisan queue:work redis --queue=redis --daemon
```

**Step 4: Update supervisor**
```ini
[program:redis-stream-listener]
process_name=%(program_name)s_%(process_num)02d
numprocs=1
command=php /var/www/html/redis-config-app/artisan stream:listen
directory=/var/www/html/redis-config-app
user=www-data
autostart=true
autorestart=true
stdout_logfile=/var/www/html/redis-config-app/storage/logs/redis-stream-listener.log
stderr_logfile=/var/www/html/redis-config-app/storage/logs/redis-stream-listener-error.log

[program:redis-queue-worker]
process_name=%(program_name)s_%(process_num)02d
numprocs=2
command=php /var/www/html/redis-config-app/artisan queue:work redis --queue=redis --sleep=3 --tries=3 --timeout=60
directory=/var/www/html/redis-config-app
user=www-data
autostart=true
autorestart=true
stdout_logfile=/var/www/html/redis-config-app/storage/logs/redis-queue-worker.log
stderr_logfile=/var/www/html/redis-config-app/storage/logs/redis-queue-worker-error.log
```

## 🧪 **Debugging Commands**

### **Check Redis Streams Status**
```bash
php artisan tinker
>>> Redis::xlen('app:data:stream')          # Check stream length
>>> Redis::xlen('app:data:update:stream')   # Check update stream
>>> Redis::xinfo('groups', 'app:data:stream')  # Check consumer groups
>>> Redis::xreadgroup('GROUPS', 'stream-workers', 'app:data:stream', '>')  # Test custom queue
```

### **Check Queue Workers**
```bash
php artisan queue:monitor
php artisan queue:failed
php artisan queue:clear
```

### **Test Custom Queue System**
```bash
# Test in foreground to see logs
php artisan queue:work redis-stream --queue=stream-insert --verbose

# Test with timeout to see if messages arrive
timeout 30s php artisan queue:work redis-stream --queue=stream-insert
```

## 🔍 **Verify Your Current Setup**

### **Check which consumer groups exist:**
```bash
redis-cli
> XINFO GROUPS app:data:stream
> XINFO GROUPS app:data:update:stream
> quit
```

### **Check what's in your supervisor config:**
```bash
sudo supervisorctl status
```

### **Check if workers are running:**
```bash
ps aux | grep "queue:work"
ps aux | grep "stream:listen"
```

## ⚡ **Immediate Test**

1. **Add a test message:**
```bash
php artisan tinker
>>> Redis::xadd('app:data:stream', '*', ['api_key' => 'test', 'name' => 'test', 'enqueued_at' => now()->toIso8601String()]);
```

2. **Start a worker in foreground:**
```bash
php artisan queue:work redis-stream --queue=stream-insert --verbose
```

3. **Check if the message gets processed**

## 📊 **Expected Results**

**With Option 1 (Custom Queues):**
- ✅ Messages from `app:data:stream` → `redis-stream` consumer → `ProcessStreamMessage` jobs → `stream-insert` workers
- ✅ Messages from `app:data:update:stream` → `redis-update-stream` consumer → `UpdateStreamMessage` jobs → `stream-update` workers
- ✅ Accurate timing with `sent_at` reflecting actual processing start

**With Option 2 (Manual Listener):**
- ✅ Messages from `app:data:stream` → `stream:listen` command → `ProcessStreamMessage` jobs → `redis` workers
- ❌ No update stream processing (unless you create a separate listener)
- ✅ Works but doesn't use the new timing improvements

## 🎯 **Recommendation**

**Use Option 1** (Custom Queue System) because:
1. It provides the timing improvements we implemented
2. It properly separates insert and update operations
3. It's more scalable and maintainable
4. It provides better error handling and logging

The core issue was **consumer group conflict** - choose one system and stick with it!