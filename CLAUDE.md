# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel-based Redis configuration application that acts as a middleware for database operations. The application processes Redis stream messages and handles database configurations for multiple applications with queue-based job processing.

## Core Architecture

### Redis Stream Queue System
The application uses custom Redis stream queues instead of standard Laravel queues:
- **RedisStreamQueue** (`app/queue/RedisStreamQueue.php`): Main queue connector for processing Redis streams
- **RedisStreamUpdateQueue** (`app/queue/RedisStreamUpdateQueue.php`): Separate queue for update operations
- **RedisStreamConnector** & **RedisStreamUpdateConnector**: Custom queue connectors

### Job Processing
Two main job types handle different operations:
- **ProcessStreamMessage** (`app/Jobs/ProcessStreamMessage.php`): Handles incoming stream messages and database insertions
- **UpdateStreamMessage** (`app/Jobs/UpdateStreamMessage.php`): Handles update operations based on data_id

### Database Schema
Key models and their relationships:
- **Application**: Central application entity with API keys
- **ApplicationField**: Field definitions for applications
- **DatabaseConfig**: Database connection configurations (MySQL/PostgreSQL)
- **ApplicationTableSubscription**: Table subscriptions with field mappings
- **Log**: Operation logging with data_id tracking for updates

## Development Commands

### Essential Laravel Commands
```bash
# Start development server
php artisan serve

# Run queue workers for Redis streams
php artisan queue:work redis-stream
php artisan queue:work redis-update-stream

# Database operations
php artisan migrate
php artisan db:seed

# Application setup
php artisan key:generate
php artisan storage:link
```

### Frontend Development
```bash
# Install dependencies
npm install

# Development build
npm run dev

# Production build
npm run build
```

### Testing
```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test suites
./vendor/bin/phpunit tests/Unit
./vendor/bin/phpunit tests/Feature
```

### Code Quality
```bash
# Laravel Pint (code formatting)
./vendor/bin/pint

# Check for code issues
./vendor/bin/pint --dry-run
```

## Queue Configuration

The application uses custom queue configurations in `config/queue.php`:
- `redis-stream`: For processing insert operations
- `redis-update-stream`: For processing update operations
- Both use Redis streams with consumer groups for reliable message processing

Environment variables for Redis configuration:
- `REDIS_QUEUE_CONNECTION`: Redis connection for queues
- `REDIS_UNIFIED_STREAM`: Stream name for main operations
- `REDIS_UNIFIED_UPDATE_STREAM`: Stream name for update operations
- `REDIS_STREAM_GROUP`: Consumer group name

## Key Features

### Database Configuration Management
- Support for MySQL and PostgreSQL connections
- Connection testing before saving configurations
- Password encryption and secure storage

### Stream Processing
- Real-time Redis stream message processing
- Automatic field mapping and data transformation
- Error handling and logging with data_id tracking

### Update Operations
- Uses data_id from logs to identify records for updates
- Separate queue system for update operations
- Maintains data consistency across multiple database connections

## Important Notes

- The application uses Indonesian comments and variable names in some areas
- Redis streams require proper consumer group management
- Database connections are validated before processing
- All operations are logged with timestamps and status tracking
- The system supports both insert and update operations through different queue mechanisms