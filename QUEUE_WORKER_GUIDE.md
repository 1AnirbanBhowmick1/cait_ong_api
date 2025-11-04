# How to Run Queue Worker

## Quick Start

### Basic Command (Process All Jobs)
```bash
php artisan queue:work --queue=default --tries=3 --timeout=60 --stop-when-empty
```

This will:
- Process all jobs in the queue
- Stop when queue is empty
- Retry failed jobs up to 3 times
- Timeout after 60 seconds per job

## Common Options

### 1. Process All Jobs Then Stop
```bash
php artisan queue:work --queue=default --stop-when-empty
```

### 2. Process Continuously (Keep Running)
```bash
php artisan queue:work --queue=default
```

This will keep running and process new jobs as they're added.

### 3. With Error Handling
```bash
php artisan queue:work --queue=default --tries=3 --timeout=60
```

### 4. Process Specific Number of Jobs
```bash
php artisan queue:work --queue=default --max-jobs=100
```

Processes 100 jobs then stops.

## For Your Current Situation

You have **112 jobs** in queue. To process them all:

```bash
# Process all 112 jobs (will take ~15-20 minutes)
php artisan queue:work --queue=default --tries=3 --timeout=60 --stop-when-empty
```

## Running in Background

### Windows (Git Bash)
```bash
# Start in background
nohup php artisan queue:work --queue=default --tries=3 --timeout=60 > storage/logs/queue-worker.log 2>&1 &

# Check logs
tail -f storage/logs/queue-worker.log
```

### Linux/Mac
```bash
# Using nohup
nohup php artisan queue:work --queue=default --tries=3 --timeout=60 > storage/logs/queue-worker.log 2>&1 &

# Using screen (better)
screen -S queue-worker
php artisan queue:work --queue=default --tries=3 --timeout=60
# Press Ctrl+A, then D to detach
# Reattach: screen -r queue-worker
```

## Monitor Progress

### While Queue Worker Runs

**Terminal 1**: Run queue worker
```bash
php artisan queue:work --queue=default --tries=3 --timeout=60 --stop-when-empty
```

**Terminal 2**: Check progress
```bash
# Count loaded companies
php artisan tinker --execute="echo App\Models\Company::where('extraction_flag', true)->count();"

# Check remaining jobs
php artisan tinker --execute="echo DB::table('jobs')->count();"
```

## Check Queue Status

```bash
# Count jobs in queue
php artisan tinker --execute="echo 'Jobs: ' . DB::table('jobs')->count();"

# Check failed jobs
php artisan tinker --execute="echo 'Failed: ' . DB::table('failed_jobs')->count();"

# View failed jobs
php artisan queue:failed
```

## Troubleshooting

### Queue Worker Not Processing
- Make sure queue connection is 'database' (check `.env` or `config/queue.php`)
- Check if jobs table exists: `php artisan migrate`

### Jobs Failing
```bash
# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### Stop Queue Worker
Press `Ctrl+C` in the terminal where it's running.

## Expected Processing Time

For 112 jobs:
- **Sequential**: ~15-20 minutes (1 job at a time)
- **With concurrency**: ~2-3 minutes (if processing multiple at once)

## Recommended Command for Your Setup

```bash
php artisan queue:work --queue=default --tries=3 --timeout=60 --stop-when-empty
```

This will process all 112 jobs and then stop automatically.

