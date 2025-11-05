# Parallel Processing Guide - Fast Oil & Gas Company Loading

## Overview

The queue-based approach is **much faster** than sequential processing because:
- Multiple companies are processed **simultaneously**
- Jobs run in parallel (limited by queue worker concurrency)
- Can process 10-50 companies at the same time instead of one-by-one

## Quick Start

### Step 1: Dispatch Jobs to Queue

```bash
# Dispatch jobs (fast - just queues them)
php artisan companies:load-from-sec --oil-gas-only --queue --limit=100
```

This will:
- Fetch all companies from SEC (basic info only - fast!)
- Dispatch jobs to queue for each company
- Takes only seconds to dispatch (not hours!)

### Step 2: Start Queue Worker

In **another terminal**, start the queue worker:

```bash
# Basic worker (processes 1 job at a time)
php artisan queue:work --queue=default

# With concurrency (processes multiple jobs in parallel - RECOMMENDED)
php artisan queue:work --queue=default --tries=3 --timeout=60
```

**For faster processing**, use multiple workers or increase concurrency:
```bash
# Process up to 10 jobs simultaneously (respects SEC rate limits)
php artisan queue:work --queue=default --tries=3 --timeout=60 --max-jobs=1000
```

### Step 3: Monitor Progress

While queue worker runs, check progress:

```bash
# Count loaded companies
php artisan tinker --execute="echo 'Loaded: ' . App\Models\Company::where('extraction_flag', true)->count() . PHP_EOL;"

# Check queue status
php artisan queue:monitor default
```

## Performance Comparison

| Method | Speed | Time for 100 companies |
|--------|-------|----------------------|
| **Sequential** | 1 at a time | ~15-20 minutes |
| **Queue (1 worker)** | 1 at a time | ~15-20 minutes |
| **Queue (10 workers)** | 10 parallel | ~2-3 minutes ⚡ |

## Recommended Setup

### For Production (Background Processing)

**Terminal 1**: Dispatch jobs
```bash
php artisan companies:load-from-sec --oil-gas-only --queue
```

**Terminal 2**: Run queue worker with concurrency
```bash
# Process 10 jobs simultaneously
php artisan queue:work --queue=default --tries=3 --timeout=60
```

### Using Supervisor (Auto-restart)

If you have supervisor configured (already in your docker setup), queue workers run automatically.

## Command Options

```bash
# Basic usage
php artisan companies:load-from-sec --oil-gas-only --queue

# With limit (for testing)
php artisan companies:load-from-sec --oil-gas-only --queue --limit=50

# Resume (skip already loaded)
php artisan companies:load-from-sec --oil-gas-only --queue --resume
```

## Queue Worker Options

```bash
# Basic
php artisan queue:work

# With specific queue
php artisan queue:work --queue=default

# Multiple attempts
php artisan queue:work --tries=3

# Timeout per job
php artisan queue:work --timeout=60

# Process specific number of jobs then exit
php artisan queue:work --max-jobs=100
```

## How It Works

1. **Command dispatches jobs** (fast - seconds)
   - Gets all companies from SEC tickers JSON
   - Creates a job for each company
   - Jobs are queued with delays to respect rate limits

2. **Queue worker processes jobs** (parallel - fast!)
   - Multiple workers can process jobs simultaneously
   - Each job:
     - Fetches company metadata from SEC
     - Checks if it's oil & gas
     - Saves to database if it is

3. **Jobs run in parallel** (up to worker concurrency)
   - If you have 10 workers, 10 companies process at once
   - Much faster than sequential!

## Rate Limiting

Jobs are dispatched with delays to respect SEC's rate limits:
- ~8 requests per second (safe margin)
- Each job starts with 120ms delay from previous
- With parallel workers, you can process more companies faster

## Monitoring

### Check Queue Status
```bash
# See pending jobs
php artisan queue:monitor default

# Check failed jobs
php artisan queue:failed
```

### Check Database Progress
```bash
php artisan tinker --execute="
echo 'Total loaded: ' . App\Models\Company::where('extraction_flag', true)->count() . PHP_EOL;
echo 'Recent: ' . PHP_EOL;
App\Models\Company::where('extraction_flag', true)->latest()->take(5)->get(['company_name', 'ticker_symbol'])->each(function(\$c) {
    echo '  - ' . \$c->company_name . ' (' . \$c->ticker_symbol . ')' . PHP_EOL;
});
"
```

## Troubleshooting

### Jobs not processing
- Make sure queue worker is running: `php artisan queue:work`
- Check if jobs are in queue: `php artisan queue:monitor`

### Too slow
- Increase worker concurrency
- Use multiple queue workers
- Check SEC API isn't rate limiting

### Jobs failing
- Check logs: `storage/logs/laravel.log`
- Check failed jobs: `php artisan queue:failed`
- Retry failed jobs: `php artisan queue:retry all`

## Best Practices

1. **Start with small batch** to test:
   ```bash
   php artisan companies:load-from-sec --oil-gas-only --queue --limit=10
   ```

2. **Use resume** if interrupted:
   ```bash
   php artisan companies:load-from-sec --oil-gas-only --queue --resume
   ```

3. **Run workers in background** for large loads:
   ```bash
   nohup php artisan queue:work > storage/logs/queue-worker.log 2>&1 &
   ```

