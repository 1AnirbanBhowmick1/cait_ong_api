# Bulk Loading All Oil & Gas Companies - Strategy Guide

Loading all oil & gas companies can take **several hours** due to SEC API rate limits. Here are the best approaches:

## Time Estimates

- **Total companies in SEC**: ~10,000+ companies
- **Oil & Gas companies**: ~200-300 companies (estimated)
- **Time per company check**: ~0.5-1 second (due to API rate limits)
- **Total estimated time**: **2-4 hours** for all oil & gas companies

## Approach 1: Run in Background (Recommended)

### Using nohup (Linux/Mac)
```bash
# Run in background, output to log file
nohup php artisan companies:load-from-sec --oil-gas-only > storage/logs/oil-gas-load.log 2>&1 &

# Check progress
tail -f storage/logs/oil-gas-load.log
```

### Using screen (Better for monitoring)
```bash
# Start screen session
screen -S oil-gas-load

# Run command
php artisan companies:load-from-sec --oil-gas-only

# Detach: Press Ctrl+A, then D
# Reattach: screen -r oil-gas-load
```

### Using tmux
```bash
# Start tmux session
tmux new -s oil-gas-load

# Run command
php artisan companies:load-from-sec --oil-gas-only

# Detach: Ctrl+B, then D
# Reattach: tmux attach -t oil-gas-load
```

## Approach 2: Run in Chunks (Best for Control)

Load companies in smaller batches over time:

```bash
# Load first 50 oil & gas companies
php artisan companies:load-from-sec --oil-gas-only --limit=50

# Wait a bit, then load next 50 (offset handled automatically)
php artisan companies:load-from-sec --oil-gas-only --limit=50

# Continue until all loaded
```

**Advantages:**
- Can stop/resume anytime
- Monitor progress easily
- Less risk of timeout

## Approach 3: Use Laravel Queue Jobs (Best for Production)

Create queue jobs to process companies in background:

```bash
# Create job
php artisan make:job LoadOilGasCompanyJob

# Run queue worker
php artisan queue:work --queue=oil-gas-load
```

## Approach 4: Scheduled Task (Automated)

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Run daily at 2 AM to update oil & gas companies
    $schedule->command('companies:load-from-sec --oil-gas-only')
             ->dailyAt('02:00')
             ->withoutOverlapping()
             ->emailOutputOnFailure('admin@example.com');
}
```

## Current Command Usage

### Basic Usage
```bash
# Load all oil & gas companies (will take hours)
php artisan companies:load-from-sec --oil-gas-only
```

### With Limits (Recommended for First Run)
```bash
# Test with 10 companies first
php artisan companies:load-from-sec --oil-gas-only --limit=10

# Then load 50 at a time
php artisan companies:load-from-sec --oil-gas-only --limit=50
```

### Monitor Progress
```bash
# In another terminal, check database
php artisan tinker --execute="echo 'Loaded: ' . App\Models\Company::where('extraction_flag', true)->count() . PHP_EOL;"
```

## Optimization Tips

1. **Run during off-peak hours** (late night/early morning)
2. **Use smaller batches** initially to test
3. **Monitor SEC API responses** for any rate limiting
4. **Check logs** regularly for errors

## Progress Tracking

The command shows:
- Total companies found
- Total companies checked
- Progress bar
- Final count of loaded/updated/errors

## Resume Capability

If the process stops, you can:
- Re-run the command (it will skip existing companies)
- Use `--limit` to continue from where it stopped
- Check database for already loaded companies

## Troubleshooting

### Command Times Out
- Use `screen` or `tmux` to keep session alive
- Run in smaller chunks with `--limit`

### SEC API Rate Limiting
- The command already has built-in delays (120ms between requests)
- If you see 429 errors, increase delay in `SecCompanyLookupService.php`

### Database Connection Issues
- Check database is accessible
- Verify connection timeout settings

## Recommended Workflow

1. **First**: Test with small batch
   ```bash
   php artisan companies:load-from-sec --oil-gas-only --limit=10
   ```

2. **Then**: Load in chunks (50 at a time)
   ```bash
   php artisan companies:load-from-sec --oil-gas-only --limit=50
   ```

3. **Or**: Run full load in background
   ```bash
   screen -S load
   php artisan companies:load-from-sec --oil-gas-only
   # Ctrl+A, D to detach
   ```

4. **Monitor**: Check progress periodically
   ```bash
   php artisan tinker --execute="echo App\Models\Company::where('extraction_flag', true)->count();"
   ```

